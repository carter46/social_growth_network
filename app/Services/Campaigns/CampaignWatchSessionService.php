<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignTask;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\CampaignWatchSession;
use App\Services\Engagement\YoutubeLookupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CampaignWatchSessionService
{
    public function __construct(
        private CampaignParticipationService $participations,
        private CampaignTaskFlowService $taskFlow,
        private ParticipationExpiryService $expiry,
        private YoutubeLookupService $youtube,
    ) {}

    public function start(CampaignParticipation $participation): CampaignWatchSession
    {
        $this->expireIfOverdue($participation);

        $campaign = $participation->campaign()->with('product')->firstOrFail();
        $campaign->assertAllowsAgentWork();
        $this->assertTimedWatchAllowed($campaign);
        $requiredSeconds = $this->requiredSecondsFor($campaign);

        return DB::transaction(function () use ($participation, $requiredSeconds) {
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

            // Invalidate prior unclaimed sessions for this participation.
            CampaignWatchSession::query()
                ->where('campaign_participation_id', $participation->id)
                ->whereNull('claimed_at')
                ->delete();

            return CampaignWatchSession::query()->create([
                'campaign_participation_id' => $participation->id,
                'token' => Str::random(40),
                'required_seconds' => $requiredSeconds,
                'started_at' => now(),
            ]);
        });
    }

    /**
     * Admin watch minutes, capped to the video length when the video is shorter
     * so agents are not stuck waiting after the video has ended.
     */
    public function requiredSecondsFor(Campaign $campaign): int
    {
        $adminSeconds = max(60, (int) ($campaign->estimated_minutes ?: 1) * 60);
        $videoSeconds = $this->videoDurationSeconds($campaign);

        if ($videoSeconds !== null && $videoSeconds < $adminSeconds) {
            return max(10, $videoSeconds);
        }

        return $adminSeconds;
    }

    private function videoDurationSeconds(Campaign $campaign): ?int
    {
        $cached = $campaign->meta['video_duration_seconds'] ?? null;
        if (is_numeric($cached) && (int) $cached > 0) {
            return (int) $cached;
        }

        $url = (string) ($campaign->target_url ?? '');
        if ($url === '') {
            return null;
        }

        // Empty task list: we only need duration; do not reject for embed/comments rules here.
        $lookup = $this->youtube->video($url, []);
        $seconds = $lookup['video']['duration_seconds'] ?? null;
        if (! is_numeric($seconds) || (int) $seconds < 1) {
            return null;
        }

        $seconds = (int) $seconds;
        $meta = (array) ($campaign->meta ?? []);
        $meta['video_duration_seconds'] = $seconds;
        $campaign->forceFill(['meta' => $meta])->save();

        return $seconds;
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
