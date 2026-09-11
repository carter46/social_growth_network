<?php

namespace App\Services\Campaigns;

use App\Enums\EngagementMetric;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\CampaignWatchSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CampaignWatchSessionService
{
    public function __construct(
        private CampaignParticipationService $participations,
    ) {}

    public function start(CampaignParticipation $participation): CampaignWatchSession
    {
        return DB::transaction(function () use ($participation) {
            $participation = CampaignParticipation::query()->whereKey($participation->id)->lockForUpdate()->firstOrFail();

            if ($participation->status !== CampaignParticipation::STATUS_STARTED) {
                throw new InvalidArgumentException('Watch session can only start for an active task.');
            }

            $campaign = $participation->campaign()->with('product')->firstOrFail();
            $this->assertTimedWatchAllowed($campaign);

            $minutes = max(1, (int) ($campaign->estimated_minutes ?: 1));

            // Invalidate prior unclaimed sessions for this participation.
            CampaignWatchSession::query()
                ->where('campaign_participation_id', $participation->id)
                ->whereNull('claimed_at')
                ->delete();

            return CampaignWatchSession::query()->create([
                'campaign_participation_id' => $participation->id,
                'token' => Str::random(40),
                'required_seconds' => $minutes * 60,
                'started_at' => now(),
            ]);
        });
    }

    public function claim(CampaignParticipation $participation, string $token): CampaignParticipation
    {
        return DB::transaction(function () use ($participation, $token) {
            $participation = CampaignParticipation::query()->whereKey($participation->id)->lockForUpdate()->firstOrFail();

            if ($participation->status !== CampaignParticipation::STATUS_STARTED) {
                throw new InvalidArgumentException('This task cannot be claimed.');
            }

            $campaign = $participation->campaign()->with('product')->firstOrFail();
            $this->assertTimedWatchAllowed($campaign);

            $session = CampaignWatchSession::query()
                ->where('campaign_participation_id', $participation->id)
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw new InvalidArgumentException('Watch session not found.');
            }

            if (! $session->isClaimable()) {
                throw new InvalidArgumentException('Required watch time has not elapsed yet.');
            }

            $session->claimed_at = now();
            $session->save();

            return $this->participations->approve($participation, null, allowStarted: true);
        });
    }

    private function assertTimedWatchAllowed(Campaign $campaign): void
    {
        $metric = EngagementMetric::tryFrom((string) ($campaign->engagement_metric ?? ''))
            ?? EngagementMetric::fromProductSlug($campaign->product?->slug ?? ($campaign->meta['product_slug'] ?? null));
        $platform = $campaign->meta['platform']
            ?? EngagementMetric::platformFromProductSlug($campaign->product?->slug ?? ($campaign->meta['product_slug'] ?? null));

        if (! $metric?->requiresTimedSession($platform)) {
            throw new InvalidArgumentException('This campaign does not use timed watch sessions.');
        }
    }
}
