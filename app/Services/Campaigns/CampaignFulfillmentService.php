<?php

namespace App\Services\Campaigns;

use App\Enums\EngagementMetric;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformProduct;
use App\Services\Engagement\EngagementProbeManager;
use App\Services\Engagement\TargetUrlValidator;
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
        $agentReward = $product->agent_reward_per_completion;
        if ($agentReward === null || (float) $agentReward <= 0) {
            $agentReward = 0;
        }

        $targetUrl = TargetUrlValidator::normalize($options['target_url'] ?? $options['campaign_url'] ?? null);
        $metric = EngagementMetric::fromProductSlug($product->slug);
        $platform = EngagementMetric::platformFromProductSlug($product->slug);

        // Likes/comments always use count_change mode even if baseline probe fails now.
        $verificationMode = $metric?->usesCountChangeVerification() ? 'count_change' : 'manual';

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
            'quantity' => max(1, (int) $item->quantity),
            'completed_count' => 0,
            'locked_creator_price' => $item->unit_price,
            'locked_agent_reward' => $agentReward,
            'status' => Campaign::STATUS_ACTIVE,
            'estimated_minutes' => $product->estimated_minutes,
            'meta' => [
                'product_title' => $product->title,
                'product_slug' => $product->slug,
                'variant_id' => $item->platform_product_variant_id,
                'options' => $options,
                'platform' => $platform,
            ],
        ]);

        if ($metric?->usesCountChangeVerification() && $targetUrl && $platform) {
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

    private function productIsCampaign(PlatformProduct $product): bool
    {
        if (! Schema::hasColumn('platform_products', 'is_campaign')) {
            return false;
        }

        return (bool) $product->is_campaign;
    }
}
