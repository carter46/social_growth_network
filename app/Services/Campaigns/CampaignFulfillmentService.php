<?php

namespace App\Services\Campaigns;

use App\Enums\EngagementMetric;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Services\Engagement\EngagementProbeManager;
use App\Services\Engagement\TargetUrlValidator;
use App\Support\ProductTaskProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CampaignFulfillmentService
{
    public function __construct(
        private EngagementProbeManager $probes,
    ) {}

    public function createFromPaidOrder(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $this->createFromOrderItem($order, $item);
        }
    }

    private function createFromOrderItem(Order $order, OrderItem $item): void
    {
        if ($item->item_type !== 'platform_product' || ! $item->item_id) {
            return;
        }

        if (Campaign::query()->where('order_item_id', $item->id)->exists()) {
            return;
        }

        $product = PlatformProduct::query()->find($item->item_id);
        if (! $product || ! $this->productIsCampaign($product)) {
            return;
        }

        $options = $item->options ?? [];
        $agentReward = $this->agentReward($product, $item, $options);

        $targetUrl = TargetUrlValidator::normalize($options['target_url'] ?? $options['campaign_url'] ?? null);
        $profile = ProductTaskProfile::forOrderOptions($product->slug, $options);
        $metric = $profile->legacyMetric();
        $platform = EngagementMetric::platformFromProductSlug($product->slug);

        // Likes (and legacy single-task comments) use count_change even if the baseline probe fails now.
        $verificationMode = match (true) {
            $profile->isMultiTask() => 'tasks',
            $profile->usesCountChangeVerification() => 'count_change',
            default => 'manual',
        };

        $channelUrl = $profile->needsChannelUrl()
            ? TargetUrlValidator::normalize($options['channel_url'] ?? null)
            : ($profile->targetIsChannel() ? $targetUrl : null);
        $channelRef = TargetUrlValidator::extractYoutubeChannelRef($channelUrl);
        $channelId = $options['channel_id'] ?? (($channelRef['type'] ?? null) === 'id' ? $channelRef['value'] : null);
        $videoId = $options['video_id'] ?? TargetUrlValidator::extractYoutubeVideoId($targetUrl);

        $completionMinutes = (int) (array_key_exists('completion_minutes', $options)
            ? $options['completion_minutes']
            : $product->completion_minutes);
        $estimatedMinutes = array_key_exists('estimated_minutes', $options)
            ? $options['estimated_minutes']
            : $product->estimated_minutes;

        // Create first without HTTP — checkout may already hold payment locks.
        $campaign = Campaign::query()->create([
            'creator_id' => $order->user_id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $item->platform_product_variant_id,
            'title' => $product->title,
            'target_url' => $targetUrl,
            'engagement_metric' => $metric?->value,
            'baseline_count' => null,
            'baseline_captured_at' => null,
            'last_verified_count' => null,
            'verification_mode' => $verificationMode,
            'quantity' => $this->campaignQuantity($item, $options),
            'completed_count' => 0,
            // Keep order line unit_price (package price for fixed; per-unit rate for per_unit).
            'locked_creator_price' => $item->unit_price,
            'locked_agent_reward' => $agentReward,
            'status' => Campaign::STATUS_ACTIVE,
            'estimated_minutes' => $estimatedMinutes,
            'required_tasks' => $profile->usesStepFlow() && $profile->isMultiTask() ? $profile->taskValues() : null,
            'channel_url' => $channelUrl,
            'channel_id' => $channelId ? substr((string) $channelId, 0, 64) : null,
            'video_id' => $videoId ? substr((string) $videoId, 0, 32) : null,
            'comment_instructions' => $profile->needsCommentInstructions()
                ? mb_substr(trim((string) ($options['comment_instructions'] ?? '')), 0, 1000)
                : null,
            'completion_minutes' => $completionMinutes > 0 ? $completionMinutes : null,
            'meta' => [
                'product_title' => $product->title,
                'product_slug' => $product->slug,
                'variant_id' => $item->platform_product_variant_id,
                'options' => $options,
                'platform' => $platform,
                'match_verified' => (bool) ($options['match_verified'] ?? false),
            ],
        ]);

        app(CampaignBudgetService::class)->holdForCampaign($campaign);

        if ($profile->usesCountChangeVerification() && $targetUrl && $platform) {
            $campaignId = $campaign->id;
            $probe = function () use ($campaignId, $platform, $metric, $targetUrl) {
                try {
                    $baseline = $this->probes->fetchCount($platform, $metric, $targetUrl);
                    if ($baseline === null) {
                        return;
                    }
                    Campaign::query()->whereKey($campaignId)->whereNull('baseline_count')->update([
                        'baseline_count' => $baseline,
                        'baseline_captured_at' => now(),
                        'last_verified_count' => $baseline,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Baseline engagement probe failed', [
                        'campaign_id' => $campaignId,
                        'error' => $e->getMessage(),
                    ]);
                }
            };

            if (DB::transactionLevel() > 0) {
                DB::afterCommit($probe);
            } else {
                $probe();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function isPerUnitLine(array $options): bool
    {
        return ($options['pricing_mode'] ?? null) === PlatformProductVariant::PRICING_PER_UNIT;
    }

    /**
     * Units bought. Legacy fixed-package orders use the package's unit count.
     *
     * @param  array<string, mixed>  $options
     */
    private function campaignQuantity(OrderItem $item, array $options): int
    {
        if ($this->isPerUnitLine($options)) {
            return max(1, (int) ($options['engagement_quantity'] ?? $item->quantity));
        }

        $variant = $item->variant;

        return max(1, (int) ($variant?->included_units ?: $variant?->pricing_units ?: ($options['engagement_quantity'] ?? 1)));
    }

    /**
     * Reward per completed unit = unit price the creator paid × product reward %.
     *
     * @param  array<string, mixed>  $options
     */
    private function agentReward(PlatformProduct $product, OrderItem $item, array $options): string
    {
        $percent = (float) ($product->agent_reward_percent ?? 0);

        if ($percent > 0) {
            // order_items.unit_price is stored at 2 decimals; derive the exact paid unit price from the line total.
            $unitPrice = bcdiv(
                number_format((float) $item->line_total, 2, '.', ''),
                (string) $this->campaignQuantity($item, $options),
                4
            );

            return PlatformProductVariant::agentRewardForUnitPrice($unitPrice, $percent);
        }

        return number_format(max(0, (float) ($product->agent_reward_per_completion ?? 0)), 2, '.', '');
    }

    private function productIsCampaign(PlatformProduct $product): bool
    {
        return Schema::hasTable('campaigns') && $product->isCampaignProduct();
    }
}
