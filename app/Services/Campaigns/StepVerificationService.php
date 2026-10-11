<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignTask;
use App\Models\AgentYoutubeAction;
use App\Models\AgentYoutubeIdentity;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\CampaignParticipationTask;
use App\Models\CampaignWatchSession;
use App\Models\User;
use App\Services\Engagement\YoutubeChannelApi;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Checks that run only after the final submission. An API failure never accepts a step
 * or pays: it leaves the step "needs_review" and schedules a recheck.
 */
class StepVerificationService
{
    /** Minutes until the 1st and 2nd automatic recheck of a step that couldn't be confirmed. */
    private const RECHECK_MINUTES = [10, 60];

    /** A step can be accepted only after the agent finished it. */
    private const ACCEPTABLE_STEP_STATUSES = [
        CampaignParticipationTask::STATUS_DONE,
        CampaignParticipationTask::STATUS_CHECKING,
        CampaignParticipationTask::STATUS_NEEDS_REVIEW,
        CampaignParticipationTask::STATUS_VERIFIED,
    ];

    public function __construct(
        private YoutubeChannelApi $api,
        private CampaignParticipationService $participations,
    ) {}

    public function runForParticipation(int $participationId): void
    {
        // One run per participation at a time; API calls happen outside DB transactions.
        $lock = Cache::lock('campaign-step-verify:'.$participationId, 120);
        if (! $lock->get()) {
            return;
        }

        try {
            $this->runLocked($participationId);
        } finally {
            $lock->release();
        }
    }

    private function runLocked(int $participationId): void
    {
        $participation = CampaignParticipation::query()->find($participationId);
        if (! $participation || ! in_array($participation->status, [
            CampaignParticipation::STATUS_SUBMITTED,
            CampaignParticipation::STATUS_VERIFYING,
            CampaignParticipation::STATUS_UNDER_REVIEW,
        ], true)) {
            return;
        }

        $campaign = Campaign::query()->find($participation->campaign_id);
        if (! $campaign) {
            return;
        }

        $agentChannelId = AgentYoutubeIdentity::query()
            ->where('user_id', $participation->agent_id)
            ->where('status', AgentYoutubeIdentity::STATUS_VERIFIED)
            ->value('channel_id');

        $steps = CampaignParticipationTask::query()
            ->where('participation_id', $participation->id)
            ->orderBy('position')
            ->get();

        foreach ($steps as $step) {
            $due = $step->status === CampaignParticipationTask::STATUS_CHECKING
                || ($step->status === CampaignParticipationTask::STATUS_NEEDS_REVIEW
                    && $step->next_check_at !== null
                    && now()->greaterThanOrEqualTo($step->next_check_at));

            if ($due) {
                $this->checkStep($step, $participation, $campaign, $agentChannelId);
            }
        }

        $this->settleParticipation($participation->id, $campaign);
    }

    /** Scheduled rechecks (10 minutes, 1 hour) and recovery of checks interrupted mid-way. */
    public function processDue(int $limit = 25): int
    {
        $ids = CampaignParticipationTask::query()
            ->where(fn ($q) => $q
                ->where(fn ($r) => $r->where('status', CampaignParticipationTask::STATUS_NEEDS_REVIEW)
                    ->whereNotNull('next_check_at')
                    ->where('next_check_at', '<=', now()))
                ->orWhere(fn ($r) => $r->where('status', CampaignParticipationTask::STATUS_CHECKING)
                    ->where('updated_at', '<=', now()->subMinutes(2))))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('participation_id')
            ->unique();

        // Every step accepted but payment never ran (for example the run was interrupted).
        $stuck = CampaignParticipation::query()
            ->whereIn('status', [CampaignParticipation::STATUS_SUBMITTED, CampaignParticipation::STATUS_VERIFYING])
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->whereHas('steps')
            ->whereDoesntHave('steps', fn ($q) => $q->where('status', '!=', CampaignParticipationTask::STATUS_ACCEPTED))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
        $ids = $ids->merge($stuck)->unique()->values();

        foreach ($ids as $id) {
            try {
                $this->runForParticipation((int) $id);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $ids->count();
    }

    private function checkStep(CampaignParticipationTask $step, CampaignParticipation $participation, Campaign $campaign, ?string $agentChannelId): void
    {
        $task = $step->taskEnum();
        $from = (string) $step->status;

        [$outcome, $note] = match ($task) {
            CampaignTask::Watch => $this->checkWatch($participation),
            CampaignTask::Subscribe => $this->checkSubscribe($campaign, $agentChannelId),
            CampaignTask::Comment => $this->checkComment($step, $campaign, $agentChannelId),
            default => ['review', 'No automatic check for this step.'],
        };

        $attempts = (int) $step->attempts + 1;
        $meta = (array) ($step->proof_meta ?? []);
        $meta['check_note'] = $note;

        if ($outcome === 'accept') {
            $saved = $this->saveIfUnchanged($step, $from, [
                'status' => CampaignParticipationTask::STATUS_VERIFIED,
                'checked_at' => now(),
                'attempts' => $attempts,
                'proof_meta' => $meta,
                'next_check_at' => null,
            ]);

            if ($saved) {
                $this->accept($step, $participation, $campaign, $agentChannelId, $task === CampaignTask::Watch ? 'timer' : 'api_verified');
            }

            return;
        }

        $recheckIn = self::RECHECK_MINUTES[$attempts - 1] ?? null;
        $this->saveIfUnchanged($step, $from, [
            'status' => CampaignParticipationTask::STATUS_NEEDS_REVIEW,
            'checked_at' => now(),
            'attempts' => $attempts,
            'proof_meta' => $meta,
            'next_check_at' => $recheckIn ? now()->addMinutes($recheckIn) : null,
        ]);
    }

    /**
     * Writes the step only if nobody (an admin, another run) changed its status since it was read.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function saveIfUnchanged(CampaignParticipationTask $step, string $from, array $attributes): bool
    {
        return DB::transaction(function () use ($step, $from, $attributes) {
            $current = CampaignParticipationTask::query()->whereKey($step->id)->lockForUpdate()->first();
            if (! $current || $current->status !== $from) {
                return false;
            }

            $current->forceFill($attributes)->save();
            $step->setRawAttributes($current->getAttributes(), true);

            return true;
        });
    }

    /** Moves the participation only from the given statuses (never out of paid, rejected or expired). */
    private function moveParticipation(int $participationId, array $from, string $to): void
    {
        CampaignParticipation::query()
            ->whereKey($participationId)
            ->whereIn('status', $from)
            ->where('status', '!=', $to)
            ->update(['status' => $to, 'updated_at' => now()]);
    }

    /** @return array{0: 'accept'|'review', 1: string} */
    private function checkWatch(CampaignParticipation $participation): array
    {
        $claimed = CampaignWatchSession::query()
            ->where('campaign_participation_id', $participation->id)
            ->whereNotNull('claimed_at')
            ->exists();

        return $claimed
            ? ['accept', 'Timer completed, viewing not verified.']
            : ['review', 'No completed watch timer found.'];
    }

    /** @return array{0: 'accept'|'review', 1: string} */
    private function checkSubscribe(Campaign $campaign, ?string $agentChannelId): array
    {
        if (! $agentChannelId || ! $campaign->channel_id) {
            return ['review', 'Subscription could not be checked automatically (channel not known).'];
        }

        return match ($this->api->isSubscribed($agentChannelId, $campaign->channel_id)) {
            true => ['accept', 'Public subscriptions show the agent follows the channel.'],
            false => ['review', 'Public subscriptions do not show this channel yet.'],
            null => ['review', 'Verification unavailable: subscriptions are private or the YouTube API did not answer.'],
        };
    }

    /** @return array{0: 'accept'|'review', 1: string} */
    private function checkComment(CampaignParticipationTask $step, Campaign $campaign, ?string $agentChannelId): array
    {
        $commentId = (string) (($step->proof_meta ?? [])['comment_id'] ?? '');
        if ($commentId === '') {
            return ['review', 'No comment link was saved.'];
        }

        $comment = $this->api->comment($commentId);
        if ($comment === null) {
            return ['review', 'Verification unavailable: the YouTube API did not answer.'];
        }
        if (! $comment['found']) {
            return ['review', 'Comment not found. It may be held for review by the channel or deleted.'];
        }
        if ($campaign->video_id && $comment['video_id'] !== $campaign->video_id) {
            return ['review', 'The comment is on a different video.'];
        }
        if (! $agentChannelId || $comment['author_channel_id'] !== $agentChannelId) {
            return ['review', 'The comment was not written by the agent\'s linked channel.'];
        }

        return ['accept', 'Comment found on the campaign video, written by the agent\'s linked channel.'];
    }

    /**
     * Step counts toward payment; the action is recorded (a comment ID can earn only once).
     *
     * @return 'accepted'|'duplicate'|'stale'
     */
    private function accept(CampaignParticipationTask $step, CampaignParticipation $participation, Campaign $campaign, ?string $agentChannelId, string $evidence, ?int $reviewerId = null): string
    {
        $task = $step->taskEnum();

        try {
            return DB::transaction(function () use ($step, $participation, $campaign, $agentChannelId, $evidence, $reviewerId, $task) {
                $current = CampaignParticipationTask::query()->whereKey($step->id)->lockForUpdate()->first();
                if ($current?->status === CampaignParticipationTask::STATUS_ACCEPTED) {
                    return 'accepted';
                }
                if (! $current || ! in_array($current->status, self::ACCEPTABLE_STEP_STATUSES, true)) {
                    return 'stale';
                }

                $action = match ($task) {
                    CampaignTask::Subscribe => AgentYoutubeAction::ACTION_SUBSCRIBE,
                    CampaignTask::Comment => AgentYoutubeAction::ACTION_COMMENT,
                    CampaignTask::Watch => AgentYoutubeAction::ACTION_WATCH,
                    CampaignTask::Like => AgentYoutubeAction::ACTION_LIKE,
                    default => null,
                };

                if ($action) {
                    AgentYoutubeAction::query()->firstOrCreate(
                        ['campaign_participation_id' => $participation->id, 'action' => $action],
                        [
                            'user_id' => $participation->agent_id,
                            'agent_channel_id' => $agentChannelId,
                            'target_channel_id' => $campaign->channel_id,
                            'target_video_id' => $campaign->video_id,
                            'external_ref' => $task === CampaignTask::Comment ? (($step->proof_meta ?? [])['comment_id'] ?? null) : null,
                            'campaign_id' => $campaign->id,
                            'evidence' => $evidence,
                            'status' => AgentYoutubeAction::STATUS_VERIFIED,
                            'verified_at' => now(),
                            'last_checked_at' => now(),
                        ],
                    );
                }

                $current->forceFill([
                    'status' => CampaignParticipationTask::STATUS_ACCEPTED,
                    'accepted_at' => now(),
                    'reviewed_by' => $reviewerId,
                    'next_check_at' => null,
                ])->save();
                $step->setRawAttributes($current->getAttributes(), true);

                return 'accepted';
            });
        } catch (UniqueConstraintViolationException $e) {
            $current = $step->fresh();
            if ($current && $current->status !== CampaignParticipationTask::STATUS_ACCEPTED) {
                $meta = (array) ($current->proof_meta ?? []);
                $meta['check_note'] = $task === CampaignTask::Comment
                    ? 'This comment ID was already used for another task.'
                    : 'This action was already recorded for another task.';
                $current->forceFill([
                    'status' => CampaignParticipationTask::STATUS_NEEDS_REVIEW,
                    'proof_meta' => $meta,
                    'next_check_at' => null,
                ])->save();
                $step->setRawAttributes($current->getAttributes(), true);
            }

            return 'duplicate';
        }
    }

    /**
     * After checks: pay automatically when every step is accepted (comment re-checked live
     * right before paying); otherwise put the task in the admin review queue.
     */
    private function settleParticipation(int $participationId, Campaign $campaign): void
    {
        $steps = CampaignParticipationTask::query()->where('participation_id', $participationId)->get();
        $participation = CampaignParticipation::query()->find($participationId);
        if (! $participation || $steps->isEmpty()) {
            return;
        }

        $open = [
            CampaignParticipation::STATUS_SUBMITTED,
            CampaignParticipation::STATUS_VERIFYING,
            CampaignParticipation::STATUS_UNDER_REVIEW,
        ];
        if (! in_array($participation->status, $open, true)) {
            return;
        }

        if ($steps->every(fn ($s) => $s->status === CampaignParticipationTask::STATUS_ACCEPTED)) {
            $toReview = [CampaignParticipation::STATUS_SUBMITTED, CampaignParticipation::STATUS_VERIFYING];

            if (! $this->commentsStillPresent($steps, $participation, $campaign)) {
                $this->moveParticipation($participationId, $toReview, CampaignParticipation::STATUS_UNDER_REVIEW);

                return;
            }

            try {
                $this->participations->approve($participation, null);
            } catch (InvalidArgumentException $e) {
                Log::warning('Bundled task auto-pay refused', ['participation' => $participationId, 'reason' => $e->getMessage()]);
                $this->moveParticipation($participationId, $toReview, CampaignParticipation::STATUS_UNDER_REVIEW);
            }

            return;
        }

        $status = $steps->contains('status', CampaignParticipationTask::STATUS_CHECKING)
            ? CampaignParticipation::STATUS_VERIFYING
            : CampaignParticipation::STATUS_UNDER_REVIEW;

        $this->moveParticipation($participationId, $open, $status);
    }

    /** Re-check accepted comments that were checked more than a minute ago. */
    private function commentsStillPresent($steps, CampaignParticipation $participation, Campaign $campaign): bool
    {
        foreach ($steps as $step) {
            if ($step->task !== CampaignTask::Comment->value) {
                continue;
            }
            if ($step->checked_at && $step->checked_at->gt(now()->subMinute())) {
                continue;
            }

            $commentId = (string) (($step->proof_meta ?? [])['comment_id'] ?? '');
            $comment = $commentId !== '' ? $this->api->comment($commentId) : null;
            if ($comment === null || ! $comment['found']) {
                $meta = (array) ($step->proof_meta ?? []);
                $meta['check_note'] = $comment === null
                    ? 'Live re-check before payment unavailable: the YouTube API did not answer.'
                    : 'Comment no longer found right before payment.';
                $this->saveIfUnchanged($step, CampaignParticipationTask::STATUS_ACCEPTED, [
                    'status' => CampaignParticipationTask::STATUS_NEEDS_REVIEW,
                    'proof_meta' => $meta,
                    'checked_at' => now(),
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Live comment lookup for the admin review screen.
     *
     * @return array{state: string, note: string}
     */
    public function liveCommentCheck(CampaignParticipationTask $step): array
    {
        $participation = $step->participation()->first();
        $campaign = $step->campaign()->first();
        if (! $participation || ! $campaign) {
            return ['state' => 'unknown', 'note' => 'Task not found.'];
        }

        $agentChannelId = AgentYoutubeIdentity::query()
            ->where('user_id', $participation->agent_id)
            ->where('status', AgentYoutubeIdentity::STATUS_VERIFIED)
            ->value('channel_id');

        [$outcome, $note] = $this->checkComment($step, $campaign, $agentChannelId);

        return ['state' => $outcome === 'accept' ? 'ok' : 'problem', 'note' => $note];
    }

    /** Admin "Approve and pay": every step not yet accepted is accepted by the admin, then pay once. */
    public function adminApprove(CampaignParticipation $participation, User $admin): CampaignParticipation
    {
        // Steps accepted here are rolled back if payment is refused, so nothing changes on failure.
        return DB::transaction(function () use ($participation, $admin) {
            $participation = CampaignParticipation::query()->whereKey($participation->id)->lockForUpdate()->firstOrFail();
            if (in_array($participation->status, [CampaignParticipation::STATUS_APPROVED, CampaignParticipation::STATUS_PAID], true)) {
                return $participation;
            }
            if (! in_array($participation->status, [
                CampaignParticipation::STATUS_SUBMITTED,
                CampaignParticipation::STATUS_UNDER_REVIEW,
                CampaignParticipation::STATUS_VERIFYING,
            ], true)) {
                throw new InvalidArgumentException('Only submitted tasks can be approved. This one is '.str_replace('_', ' ', (string) $participation->status).'.');
            }

            $campaign = Campaign::query()->findOrFail($participation->campaign_id);
            $campaign->assertAllowsAgentWork();

            $agentChannelId = AgentYoutubeIdentity::query()
                ->where('user_id', $participation->agent_id)
                ->where('status', AgentYoutubeIdentity::STATUS_VERIFIED)
                ->value('channel_id');

            $steps = CampaignParticipationTask::query()->where('participation_id', $participation->id)->lockForUpdate()->get();
            if ($steps->contains(fn ($s) => ! in_array($s->status, [...self::ACCEPTABLE_STEP_STATUSES, CampaignParticipationTask::STATUS_ACCEPTED], true))) {
                throw new InvalidArgumentException('This task has steps that were never completed, so it cannot be approved.');
            }

            foreach ($steps as $step) {
                $result = $this->accept($step, $participation, $campaign, $agentChannelId, 'admin_approved', $admin->id);
                if ($result === 'duplicate') {
                    throw new InvalidArgumentException('A comment on this task was already used for another task, so it cannot be approved.');
                }
                if ($result === 'stale') {
                    throw new InvalidArgumentException('This task changed while you were reviewing it. Reload the page and try again.');
                }
            }

            return $this->participations->approve($participation, $admin);
        });
    }

    /** Admin rejects the whole bundle: no reward. Accepted steps stay recorded. */
    public function adminReject(CampaignParticipation $participation, User $admin, string $reason): CampaignParticipation
    {
        return DB::transaction(function () use ($participation, $admin, $reason) {
            $rejected = $this->participations->reject($participation, $admin, $reason);

            CampaignParticipationTask::query()
                ->where('participation_id', $participation->id)
                ->where('status', '!=', CampaignParticipationTask::STATUS_ACCEPTED)
                ->update([
                    'status' => CampaignParticipationTask::STATUS_REJECTED,
                    'rejection_reason' => $reason,
                    'reviewed_by' => $admin->id,
                    'next_check_at' => null,
                    'updated_at' => now(),
                ]);

            return $rejected;
        });
    }
}
