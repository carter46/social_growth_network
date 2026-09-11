<?php

namespace App\Services\Campaigns;

use App\Enums\EngagementMetric;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Services\Engagement\EngagementProbeManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single-flight count-change verification per campaign.
 * Concurrent agents may work; only one participation finalizes at a time.
 * HTTP probes run outside DB locks to avoid holding transactions open.
 */
class EngagementVerificationService
{
    public const SETTLE_SECONDS = 120;

    public const MAX_ATTEMPTS = 3;

    public function __construct(
        private EngagementProbeManager $probes,
        private CampaignParticipationService $participations,
    ) {}

    public function processBatch(int $limit = 5): int
    {
        $processed = 0;

        // Include campaigns with verifying rows OR submitted queue.
        $campaignIds = Campaign::query()
            ->where('status', Campaign::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->where('verification_mode', 'count_change')
                    ->orWhere('verification_mode', 'manual');
            })
            ->where(function ($q) {
                $q->whereHas('participations', fn ($p) => $p->where('status', CampaignParticipation::STATUS_SUBMITTED))
                    ->orWhereHas('participations', fn ($p) => $p->where('status', CampaignParticipation::STATUS_VERIFYING));
            })
            ->with('product')
            ->orderBy('id')
            ->limit($limit * 3)
            ->pluck('id');

        foreach ($campaignIds as $campaignId) {
            if ($processed >= $limit) {
                break;
            }
            if ($this->processNextForCampaign((int) $campaignId)) {
                $processed++;
            }
        }

        return $processed;
    }

    public function processNextForCampaign(int $campaignId): bool
    {
        // Phase 1: claim work under lock (no HTTP).
        $claim = DB::transaction(function () use ($campaignId) {
            $campaign = Campaign::query()->whereKey($campaignId)->lockForUpdate()->first();
            if (! $campaign || $campaign->status !== Campaign::STATUS_ACTIVE) {
                return null;
            }

            if ($campaign->verification_locked_participation_id) {
                $locked = CampaignParticipation::query()
                    ->whereKey($campaign->verification_locked_participation_id)
                    ->lockForUpdate()
                    ->first();

                if ($locked && $locked->status === CampaignParticipation::STATUS_VERIFYING) {
                    $started = $locked->verification_started_at;
                    if ($started && $started->copy()->addSeconds(self::SETTLE_SECONDS)->gt(now())) {
                        return null; // still settling
                    }

                    return [
                        'phase' => 'finalize',
                        'campaign_id' => $campaign->id,
                        'participation_id' => $locked->id,
                        'target_url' => $campaign->target_url,
                        'metric' => $campaign->engagement_metric,
                        'platform' => $campaign->meta['platform'] ?? null,
                        'product_slug' => $campaign->product?->slug ?? ($campaign->meta['product_slug'] ?? null),
                        'pre_count' => $locked->pre_count,
                        'attempts' => (int) $locked->verify_attempts,
                    ];
                }

                $campaign->verification_locked_participation_id = null;
                $campaign->save();
            }

            // Promote metric mode if baseline failed at create but product is likes/comments.
            $metric = EngagementMetric::tryFrom((string) $campaign->engagement_metric)
                ?? EngagementMetric::fromProductSlug($campaign->product?->slug ?? ($campaign->meta['product_slug'] ?? null));
            $platform = $campaign->meta['platform']
                ?? EngagementMetric::platformFromProductSlug($campaign->product?->slug ?? ($campaign->meta['product_slug'] ?? null));

            if ($metric?->usesCountChangeVerification() && $campaign->verification_mode !== 'count_change') {
                $campaign->verification_mode = 'count_change';
                $campaign->save();
            }

            if ($campaign->verification_mode !== 'count_change') {
                return null;
            }

            $next = CampaignParticipation::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', CampaignParticipation::STATUS_SUBMITTED)
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $next || ! $metric?->usesCountChangeVerification() || ! $platform || ! $campaign->target_url) {
                return null;
            }

            return [
                'phase' => 'start',
                'campaign_id' => $campaign->id,
                'participation_id' => $next->id,
                'target_url' => $campaign->target_url,
                'metric' => $metric->value,
                'platform' => $platform,
                'product_slug' => $campaign->product?->slug ?? ($campaign->meta['product_slug'] ?? null),
            ];
        });

        if (! $claim) {
            return false;
        }

        $metric = EngagementMetric::tryFrom((string) $claim['metric'])
            ?? EngagementMetric::fromProductSlug($claim['product_slug'] ?? null);
        $platform = $claim['platform']
            ?? EngagementMetric::platformFromProductSlug($claim['product_slug'] ?? null);

        if (! $metric || ! $platform || empty($claim['target_url'])) {
            return false;
        }

        if ($claim['phase'] === 'start') {
            // HTTP outside transaction.
            $pre = $this->probes->fetchCount($platform, $metric, $claim['target_url']);
            if ($pre === null) {
                Log::info('Count-change verify skipped: probe null at start', [
                    'participation' => $claim['participation_id'],
                ]);

                return false;
            }

            return DB::transaction(function () use ($claim, $pre) {
                $campaign = Campaign::query()->whereKey($claim['campaign_id'])->lockForUpdate()->first();
                $participation = CampaignParticipation::query()->whereKey($claim['participation_id'])->lockForUpdate()->first();
                if (! $campaign || ! $participation || $participation->status !== CampaignParticipation::STATUS_SUBMITTED) {
                    return false;
                }
                if ($campaign->verification_locked_participation_id) {
                    return false;
                }

                $participation->update([
                    'status' => CampaignParticipation::STATUS_VERIFYING,
                    'pre_count' => $pre,
                    'verification_started_at' => now(),
                    'verify_attempts' => (int) $participation->verify_attempts + 1,
                ]);
                $campaign->verification_locked_participation_id = $participation->id;
                $campaign->verification_mode = 'count_change';
                $campaign->save();

                return true;
            });
        }

        // finalize
        $post = $this->probes->fetchCount($platform, $metric, $claim['target_url']);

        return DB::transaction(function () use ($claim, $post) {
            $campaign = Campaign::query()->whereKey($claim['campaign_id'])->lockForUpdate()->first();
            $participation = CampaignParticipation::query()->whereKey($claim['participation_id'])->lockForUpdate()->first();
            if (! $campaign || ! $participation || $participation->status !== CampaignParticipation::STATUS_VERIFYING) {
                return false;
            }

            $participation->post_count = $post;
            $participation->save();

            $pre = (int) $participation->pre_count;

            if ($post !== null && $post >= $pre + 1) {
                try {
                    $this->participations->approve($participation, null);
                    $campaign->refresh();
                    $campaign->last_verified_count = $post;
                    if ((int) $campaign->verification_locked_participation_id === (int) $participation->id) {
                        $campaign->verification_locked_participation_id = null;
                    }
                    $campaign->save();
                } catch (\Throwable $e) {
                    Log::error('Count-change approve/payout failed', [
                        'participation' => $participation->id,
                        'error' => $e->getMessage(),
                    ]);

                    // Release lock so the queue is not permanently stuck.
                    $participation->refresh();
                    if ($participation->status === CampaignParticipation::STATUS_VERIFYING) {
                        $participation->update([
                            'status' => CampaignParticipation::STATUS_SUBMITTED,
                            'verification_started_at' => null,
                        ]);
                    }
                    $campaign->refresh();
                    if ((int) $campaign->verification_locked_participation_id === (int) $participation->id) {
                        $campaign->verification_locked_participation_id = null;
                        $campaign->save();
                    }

                    return true;
                }

                return true;
            }

            // Soft retry on null probe OR non-increase while attempts remain.
            if ((int) $participation->verify_attempts < self::MAX_ATTEMPTS) {
                $participation->update([
                    'status' => CampaignParticipation::STATUS_SUBMITTED,
                    'verification_started_at' => null,
                ]);
                $campaign->verification_locked_participation_id = null;
                $campaign->save();

                return true;
            }

            $this->participations->reject(
                $participation,
                null,
                $post === null
                    ? 'Count-change verification could not read the post metric after multiple attempts.'
                    : 'Count-change verification did not observe an increase in the post metric after your submission.'
            );

            return true;
        });
    }
}
