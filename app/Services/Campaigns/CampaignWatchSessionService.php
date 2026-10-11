<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignTask;
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
        private CampaignTaskFlowService $taskFlow,
        private ParticipationExpiryService $expiry,
    ) {}

    public function start(CampaignParticipation $participation): CampaignWatchSession
    {
        $this->expireIfOverdue($participation);

        return DB::transaction(function () use ($participation) {
            $participation = CampaignParticipation::query()->whereKey($participation->id)->lockForUpdate()->firstOrFail();

            if ($participation->status !== CampaignParticipation::STATUS_STARTED || $participation->isPastDeadline()) {
                throw new InvalidArgumentException('Watch session can only start for an active task.');
            }

            $campaign = $participation->campaign()->with('product')->firstOrFail();
            $campaign->assertAllowsAgentWork();
            $this->assertTimedWatchAllowed($campaign);

            if ($campaign->usesStepFlow()) {
                $this->taskFlow->assertWatchStepActive($participation);
            }

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

    /**
     * Standalone Watch Hours / Views pay on claim. On bundled tasks the claim only marks
     * the watch step done; payment waits for the final submission and checks.
     */
    public function claim(CampaignParticipation $participation, string $token): CampaignParticipation
    {
        $this->expireIfOverdue($participation);

        return DB::transaction(function () use ($participation, $token) {
            $participation = CampaignParticipation::query()->whereKey($participation->id)->lockForUpdate()->firstOrFail();

            if ($participation->status !== CampaignParticipation::STATUS_STARTED || $participation->isPastDeadline()) {
                throw new InvalidArgumentException('This task cannot be claimed.');
            }

            $campaign = $participation->campaign()->with('product')->firstOrFail();
            $campaign->assertAllowsAgentWork();
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

            if ($campaign->usesStepFlow()) {
                $this->taskFlow->markWatchDone($participation, (int) $session->required_seconds);
                $session->claimed_at = now();
                $session->save();

                return $participation->fresh();
            }

            $session->claimed_at = now();
            $session->save();

            return $this->participations->approve($participation, null, allowStarted: true);
        });
    }

    private function assertTimedWatchAllowed(Campaign $campaign): void
    {
        if (! $campaign->taskProfile()->has(CampaignTask::Watch)) {
            throw new InvalidArgumentException('This campaign does not use timed watch sessions.');
        }
    }

    private function expireIfOverdue(CampaignParticipation $participation): void
    {
        $fresh = CampaignParticipation::query()->find($participation->id);
        if ($fresh && $fresh->isPastDeadline()) {
            $this->expiry->expire($fresh);

            throw new InvalidArgumentException('Time ran out for this task, so it can no longer be completed.');
        }
    }
}
