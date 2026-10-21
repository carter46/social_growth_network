<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignTask;
use App\Models\AgentYoutubeAction;
use App\Models\AgentYoutubeIdentity;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\CampaignParticipationTask;
use App\Models\User;
use App\Services\Engagement\TargetUrlValidator;
use App\Services\Engagement\YoutubeChannelApi;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Bundled steps: the agent does each step in order (evidence saved as "done"), clicks
 * Proceed to unlock the next one, and finally submits the whole task once. Nothing is
 * checked or accepted before that final submission.
 */
class CampaignTaskFlowService
{
    public function __construct(
        private ParticipationExpiryService $expiry,
        private YoutubeChannelApi $youtube,
    ) {}

    /** Without the YouTube API, a subscribe step can only be checked from a screenshot. */
    public function subscribeScreenshotRequired(): bool
    {
        return ! $this->youtube->hasKey();
    }

    /**
     * @param  list<\App\Enums\CampaignTask>|null  $tasks  steps this agent was given (default: every step)
     */
    public function createSteps(CampaignParticipation $participation, Campaign $campaign, ?array $tasks = null): void
    {
        foreach (array_values($tasks ?? $campaign->taskProfile()->tasks()) as $index => $task) {
            CampaignParticipationTask::query()->firstOrCreate(
                ['participation_id' => $participation->id, 'task' => $task->value],
                [
                    'campaign_id' => $campaign->id,
                    'position' => $index + 1,
                    'status' => $index === 0 ? CampaignParticipationTask::STATUS_ACTIVE : CampaignParticipationTask::STATUS_LOCKED,
                ],
            );
        }
    }

    /** The step the agent can act on right now (active, or done and waiting for Proceed). */
    public function currentStep(CampaignParticipation $participation): ?CampaignParticipationTask
    {
        return $participation->steps()
            ->whereIn('status', [CampaignParticipationTask::STATUS_ACTIVE])
            ->orderBy('position')
            ->first();
    }

    /**
     * Save evidence for the active subscribe or comment step. The step becomes "done":
     * not submitted, not verified, not accepted.
     *
     * @param  array{proof_url?: ?string, proof_notes?: ?string}  $data
     */
    public function saveStep(CampaignParticipation $participation, User $agent, CampaignTask $task, array $data): CampaignParticipationTask
    {
        if ($task === CampaignTask::Watch) {
            throw new InvalidArgumentException('The watch step is completed by the watch timer.');
        }

        $this->expireIfOverdue($participation);

        return DB::transaction(function () use ($participation, $agent, $task, $data) {
            [$participation, $campaign] = $this->lockForAgentAction($participation, $agent);

            $step = CampaignParticipationTask::query()
                ->where('participation_id', $participation->id)
                ->where('task', $task->value)
                ->lockForUpdate()
                ->first();

            if (! $step) {
                throw new InvalidArgumentException('This task does not include that step.');
            }
            if ($step->status === CampaignParticipationTask::STATUS_DONE) {
                return $step;
            }
            if ($step->status !== CampaignParticipationTask::STATUS_ACTIVE) {
                throw new InvalidArgumentException('Finish the earlier steps first. Steps must be done in order.');
            }

            $proofUrl = trim((string) ($data['proof_url'] ?? ''));
            if ($task === CampaignTask::Subscribe && $proofUrl === '' && $this->subscribeScreenshotRequired()) {
                throw new InvalidArgumentException('Upload a screenshot that shows you are subscribed.');
            }
            $notes = trim((string) ($data['proof_notes'] ?? ''));
            $meta = [];

            if ($task === CampaignTask::Comment) {
                $meta = $this->validateCommentLink($campaign, $step, $proofUrl);
                $method = 'comment_link';
            } else {
                if ($proofUrl !== '' && ! TargetUrlValidator::normalize($proofUrl)) {
                    throw new InvalidArgumentException('The screenshot link must be a valid http or https link.');
                }
                $method = $this->agentHasOAuth($agent) ? 'oauth' : ($proofUrl !== '' ? 'screenshot' : 'public_check');
            }

            $step->forceFill([
                'status' => CampaignParticipationTask::STATUS_DONE,
                'evidence_method' => $method,
                'proof_url' => $proofUrl !== '' ? TargetUrlValidator::normalize($proofUrl) : null,
                'proof_notes' => $notes !== '' ? mb_substr($notes, 0, 1000) : null,
                'proof_meta' => $meta ?: null,
                'done_at' => now(),
            ])->save();

            return $step->fresh();
        });
    }

    /** Called by the watch claim (inside its transaction): the timer completed. Never pays. */
    public function markWatchDone(CampaignParticipation $participation, int $seconds): void
    {
        $step = CampaignParticipationTask::query()
            ->where('participation_id', $participation->id)
            ->where('task', CampaignTask::Watch->value)
            ->lockForUpdate()
            ->first();

        if (! $step) {
            throw new InvalidArgumentException('This task does not include a watch step.');
        }
        if ($step->status === CampaignParticipationTask::STATUS_DONE) {
            return;
        }
        if ($step->status !== CampaignParticipationTask::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Finish the earlier steps first. Steps must be done in order.');
        }

        $step->forceFill([
            'status' => CampaignParticipationTask::STATUS_DONE,
            'evidence_method' => 'timer',
            'proof_meta' => ['seconds' => $seconds, 'note' => 'Timer completed, viewing not verified'],
            'done_at' => now(),
        ])->save();
    }

    /** The watch step must be the active one before a watch session can start. */
    public function assertWatchStepActive(CampaignParticipation $participation): void
    {
        $status = CampaignParticipationTask::query()
            ->where('participation_id', $participation->id)
            ->where('task', CampaignTask::Watch->value)
            ->value('status');

        if ($status !== CampaignParticipationTask::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Finish the earlier steps first. Steps must be done in order.');
        }
    }

    /** "Proceed to next task": unlocks the next step. Never accepts anything. */
    public function proceed(CampaignParticipation $participation, User $agent): ?CampaignParticipationTask
    {
        $this->expireIfOverdue($participation);

        return DB::transaction(function () use ($participation, $agent) {
            [$participation] = $this->lockForAgentAction($participation, $agent);

            $steps = CampaignParticipationTask::query()
                ->where('participation_id', $participation->id)
                ->orderBy('position')
                ->lockForUpdate()
                ->get();

            if ($steps->contains('status', CampaignParticipationTask::STATUS_ACTIVE)) {
                throw new InvalidArgumentException('Complete the current step before moving on.');
            }

            $next = $steps->firstWhere('status', CampaignParticipationTask::STATUS_LOCKED);
            if ($next) {
                $next->status = CampaignParticipationTask::STATUS_ACTIVE;
                $next->save();
            }

            return $next;
        });
    }

    /** The single final submission. Stops the clock and queues the checks. */
    public function submit(CampaignParticipation $participation, User $agent): CampaignParticipation
    {
        $this->expireIfOverdue($participation);

        $submitted = DB::transaction(function () use ($participation, $agent) {
            $participation = CampaignParticipation::query()->whereKey($participation->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($participation, $agent);

            if (in_array($participation->status, [
                CampaignParticipation::STATUS_SUBMITTED,
                CampaignParticipation::STATUS_VERIFYING,
                CampaignParticipation::STATUS_UNDER_REVIEW,
                CampaignParticipation::STATUS_PAID,
            ], true)) {
                return $participation;
            }

            [$participation] = $this->lockForAgentAction($participation, $agent);

            $steps = CampaignParticipationTask::query()
                ->where('participation_id', $participation->id)
                ->lockForUpdate()
                ->get();

            if ($steps->isEmpty() || $steps->contains(fn ($s) => $s->status !== CampaignParticipationTask::STATUS_DONE)) {
                throw new InvalidArgumentException('Complete every step before submitting the task.');
            }

            CampaignParticipationTask::query()
                ->where('participation_id', $participation->id)
                ->update(['status' => CampaignParticipationTask::STATUS_CHECKING, 'updated_at' => now()]);

            $participation->update([
                'status' => CampaignParticipation::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ]);

            DB::afterCommit(function () use ($participation) {
                try {
                    app(StepVerificationService::class)->runForParticipation($participation->id);
                } catch (\Throwable $e) {
                    report($e);
                }
            });

            return $participation;
        });

        return $submitted->fresh();
    }

    /**
     * @return array{0: CampaignParticipation, 1: Campaign}
     */
    private function lockForAgentAction(CampaignParticipation $participation, User $agent): array
    {
        $participation = CampaignParticipation::query()->whereKey($participation->id)->lockForUpdate()->firstOrFail();
        $this->assertOwner($participation, $agent);

        if ($participation->status !== CampaignParticipation::STATUS_STARTED) {
            throw new InvalidArgumentException($participation->status === CampaignParticipation::STATUS_EXPIRED
                ? 'Time ran out for this task, so it can no longer be completed.'
                : 'This task can no longer be changed.');
        }
        if ($participation->isPastDeadline()) {
            throw new InvalidArgumentException('Time ran out for this task, so it can no longer be completed.');
        }

        $campaign = Campaign::query()->whereKey($participation->campaign_id)->firstOrFail();
        $campaign->assertAllowsAgentWork();

        return [$participation, $campaign];
    }

    private function expireIfOverdue(CampaignParticipation $participation): void
    {
        $fresh = CampaignParticipation::query()->find($participation->id);
        if ($fresh && $fresh->isPastDeadline()) {
            $this->expiry->expire($fresh);

            throw new InvalidArgumentException('Time ran out for this task, so it can no longer be completed.');
        }
    }

    private function assertOwner(CampaignParticipation $participation, User $agent): void
    {
        if ((int) $participation->agent_id !== (int) $agent->id) {
            throw new InvalidArgumentException('This task belongs to another agent.');
        }
    }

    private function agentHasOAuth(User $agent): bool
    {
        return AgentYoutubeIdentity::query()
            ->where('user_id', $agent->id)
            ->where('status', AgentYoutubeIdentity::STATUS_VERIFIED)
            ->whereNotNull('oauth_token')
            ->exists();
    }

    /**
     * Format checks only (a comment link on this campaign's video, not used before).
     * Passing them gives "done", never "verified".
     *
     * @return array{comment_id: string}
     */
    private function validateCommentLink(Campaign $campaign, CampaignParticipationTask $step, string $proofUrl): array
    {
        $commentId = TargetUrlValidator::extractYoutubeCommentId($proofUrl);
        if (! $commentId) {
            throw new InvalidArgumentException('Paste the link to your comment. On YouTube, open the three-dot menu next to your comment (or click its timestamp) and copy the link; it contains "lc=".');
        }

        $videoId = $campaign->video_id ?: TargetUrlValidator::extractYoutubeVideoId($campaign->target_url);
        if ($videoId && TargetUrlValidator::extractYoutubeVideoId($proofUrl) !== $videoId) {
            throw new InvalidArgumentException('That comment link is for a different video. Comment on the campaign video and copy that link.');
        }

        $usedBefore = AgentYoutubeAction::query()
            ->where('action', AgentYoutubeAction::ACTION_COMMENT)
            ->where('external_ref', $commentId)
            ->exists()
            || CampaignParticipationTask::query()
                ->where('task', CampaignTask::Comment->value)
                ->where('id', '!=', $step->id)
                ->where('proof_meta->comment_id', $commentId)
                ->whereNotIn('status', [CampaignParticipationTask::STATUS_REJECTED, CampaignParticipationTask::STATUS_EXPIRED])
                ->exists();

        if ($usedBefore) {
            throw new InvalidArgumentException('That comment has already been used for a task. Post a new comment for this task.');
        }

        return ['comment_id' => $commentId];
    }
}
