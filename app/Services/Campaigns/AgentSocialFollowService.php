<?php

namespace App\Services\Campaigns;

use App\Models\AgentSocialFollowProof;
use App\Models\AgentSocialFollowSubmission;
use App\Models\SocialLink;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use App\Services\Communications\Social\SocialLinkRepository;
use App\Services\Media\ScreenshotOptimizer;
use App\Services\Media\TaskProofRetentionService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Platform “follow us” onboarding for agents. Not a paid campaign — required before
 * marketplace tasks when the site has enabled social links.
 */
class AgentSocialFollowService
{
    public function __construct(
        private SocialLinkRepository $socials,
        private ScreenshotOptimizer $screenshots,
        private NotificationDispatcher $notifications,
        private TaskProofRetentionService $proofRetention,
    ) {}

    public function tablesReady(): bool
    {
        return Schema::hasTable('agent_social_follow_submissions')
            && Schema::hasTable('agent_social_follow_proofs');
    }

    /** True when the agent may start paid marketplace tasks. */
    public function isCleared(User $user): bool
    {
        if (! $this->tablesReady()) {
            return true;
        }

        if ($this->socials->enabled()->isEmpty()) {
            return true;
        }

        $submission = AgentSocialFollowSubmission::query()->where('user_id', $user->id)->first();

        return $submission?->isApproved() ?? false;
    }

    public function assertCleared(User $user): void
    {
        if (! $this->isCleared($user)) {
            throw new SocialFollowRequiredException;
        }
    }

    public function submissionFor(User $user): ?AgentSocialFollowSubmission
    {
        if (! $this->tablesReady()) {
            return null;
        }

        return AgentSocialFollowSubmission::query()
            ->where('user_id', $user->id)
            ->with('proofs')
            ->first();
    }

    /**
     * Create or refresh the agent’s follow task from currently enabled socials.
     * Approved submissions are left alone. Submitted ones stay frozen until review.
     */
    public function ensureSubmission(User $user): ?AgentSocialFollowSubmission
    {
        if (! $this->tablesReady()) {
            return null;
        }

        $links = $this->socials->enabled();
        if ($links->isEmpty()) {
            return $this->submissionFor($user);
        }

        return DB::transaction(function () use ($user, $links) {
            $submission = AgentSocialFollowSubmission::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['status' => AgentSocialFollowSubmission::STATUS_PENDING],
            );

            if ($submission->isApproved() || $submission->isSubmitted()) {
                return $submission->load('proofs');
            }

            if ($submission->status === AgentSocialFollowSubmission::STATUS_REJECTED) {
                $submission->forceFill([
                    'status' => AgentSocialFollowSubmission::STATUS_PENDING,
                    'rejection_reason' => null,
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                    'submitted_at' => null,
                    'started_at' => null,
                    'expires_at' => null,
                ])->save();
                $submission->proofs()->delete();
            }

            $existing = $submission->proofs()->get()->keyBy(fn (AgentSocialFollowProof $p) => (string) ($p->social_link_id ?: $p->platform));
            $order = 0;
            $keepIds = [];

            foreach ($links as $link) {
                /** @var SocialLink $link */
                $key = (string) $link->id;
                $proof = $existing->get($key) ?? $existing->get((string) $link->platform);

                if ($proof) {
                    $proof->forceFill([
                        'social_link_id' => $link->id,
                        'platform' => $link->platform,
                        'url' => $link->url,
                        'icon_media_id' => $link->icon_media_id,
                        'sort_order' => $order++,
                    ])->save();
                    $keepIds[] = $proof->id;
                } else {
                    $created = $submission->proofs()->create([
                        'social_link_id' => $link->id,
                        'platform' => $link->platform,
                        'url' => $link->url,
                        'icon_media_id' => $link->icon_media_id,
                        'sort_order' => $order++,
                    ]);
                    $keepIds[] = $created->id;
                }
            }

            $submission->proofs()->whereNotIn('id', $keepIds)->whereNull('proof_url')->delete();

            return $submission->fresh('proofs');
        });
    }

    /**
     * Start (or restart) the timed follow run. Minutes: 1→3, 2→5, 3→7, 4+→10.
     */
    public function start(User $user): AgentSocialFollowSubmission
    {
        if (! Schema::hasColumn('agent_social_follow_submissions', 'started_at')
            || ! Schema::hasColumn('agent_social_follow_submissions', 'expires_at')) {
            throw new InvalidArgumentException('Priority task timer is not set up yet. Run database migrations, then try again.');
        }

        $submission = $this->ensureSubmission($user);
        if (! $submission) {
            throw new InvalidArgumentException('Priority task is not available.');
        }
        if ($submission->isApproved()) {
            throw new InvalidArgumentException('This priority task is already approved.');
        }
        if ($submission->isSubmitted()) {
            throw new InvalidArgumentException('This priority task is already waiting for review.');
        }

        $this->expireIfNeeded($submission);

        if ($submission->isTimedRunActive() && ! $submission->expires_at?->isPast()) {
            return $submission->fresh('proofs');
        }

        $submission->load('proofs');
        if ($submission->proofs->isEmpty()) {
            throw new InvalidArgumentException('There are no social accounts to complete right now.');
        }

        $minutes = AgentSocialFollowSubmission::completionMinutesForCount($submission->proofs->count());

        $submission->forceFill([
            'status' => AgentSocialFollowSubmission::STATUS_PENDING,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($minutes),
            'rejection_reason' => null,
            'submitted_at' => null,
        ])->save();

        return $submission->fresh('proofs');
    }

    public function expireIfNeeded(?AgentSocialFollowSubmission $submission): ?AgentSocialFollowSubmission
    {
        if (! $submission) {
            return null;
        }

        if ($submission->status !== AgentSocialFollowSubmission::STATUS_PENDING) {
            return $submission;
        }

        if ($submission->expires_at === null || $submission->expires_at->isFuture()) {
            return $submission;
        }

        $submission->forceFill([
            'status' => AgentSocialFollowSubmission::STATUS_EXPIRED,
        ])->save();

        return $submission->fresh('proofs');
    }

    public function saveProof(User $user, AgentSocialFollowProof $proof, UploadedFile $file): AgentSocialFollowProof
    {
        $submission = $this->ensureSubmission($user);
        if (! $submission || (int) $proof->submission_id !== (int) $submission->id) {
            throw new InvalidArgumentException('That follow step is not part of your task.');
        }

        $this->expireIfNeeded($submission);
        $submission->refresh();

        if ($submission->isExpired()) {
            throw new InvalidArgumentException('Time ran out. Start the task again to continue.');
        }
        if (! $submission->isTimedRunActive()) {
            throw new InvalidArgumentException('Start the task before uploading screenshots.');
        }
        if (! $submission->canEditProofs()) {
            throw new InvalidArgumentException('You cannot change proofs while this task is under review or already approved.');
        }

        $path = $this->screenshots->store($file, 'social-follow-proofs/'.now()->format('Y/m'));
        $url = Storage::disk('public')->url($path);

        $proof->forceFill([
            'proof_url' => $url,
            'completed_at' => now(),
        ])->save();

        return $proof->fresh();
    }

    public function submit(User $user): AgentSocialFollowSubmission
    {
        $submission = $this->ensureSubmission($user);
        if (! $submission) {
            throw new InvalidArgumentException('Priority task is not available.');
        }
        if ($submission->isApproved()) {
            throw new InvalidArgumentException('This priority task is already approved.');
        }
        if ($submission->isSubmitted()) {
            throw new InvalidArgumentException('This priority task is already waiting for review.');
        }

        $this->expireIfNeeded($submission);
        $submission->refresh();

        if ($submission->isExpired()) {
            throw new InvalidArgumentException('Time ran out. Start the task again to continue.');
        }
        if (! $submission->isTimedRunActive()) {
            throw new InvalidArgumentException('Start the task before submitting.');
        }

        $submission->load('proofs');
        if ($submission->proofs->isEmpty()) {
            throw new InvalidArgumentException('There are no social accounts to follow right now.');
        }
        if ($submission->proofs->contains(fn (AgentSocialFollowProof $p) => ! $p->isComplete())) {
            throw new InvalidArgumentException('Upload a follow screenshot for every social account before submitting.');
        }

        $submission->forceFill([
            'status' => AgentSocialFollowSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $this->notifyAdmins($submission);

        return $submission->fresh('proofs');
    }

    public function approve(AgentSocialFollowSubmission $submission, User $admin): AgentSocialFollowSubmission
    {
        if ($submission->isApproved()) {
            return $submission;
        }
        if (! $submission->isSubmitted()) {
            throw new InvalidArgumentException('Only submitted follow tasks can be approved.');
        }

        $submission->forceFill([
            'status' => AgentSocialFollowSubmission::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'rejection_reason' => null,
        ])->save();

        $fresh = $submission->fresh('proofs');
        $this->proofRetention->scheduleForSocialFollow($fresh);

        $this->audit($admin->id, 'agent.social_follow_approved', $fresh);
        $this->notifyAgent(
            $fresh,
            'agent.social_follow.approved',
            'Priority task approved',
            'Your priority task was approved. You can now start earning on the Task Marketplace.',
        );

        return $fresh;
    }

    public function reject(AgentSocialFollowSubmission $submission, User $admin, string $reason): AgentSocialFollowSubmission
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }
        if (! $submission->isSubmitted()) {
            throw new InvalidArgumentException('Only submitted follow tasks can be rejected.');
        }

        $submission->forceFill([
            'status' => AgentSocialFollowSubmission::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'rejection_reason' => mb_substr($reason, 0, 500),
            'submitted_at' => null,
        ])->save();

        // Clear proofs so the agent must re-upload after a reject.
        $submission->proofs()->update([
            'proof_url' => null,
            'completed_at' => null,
        ]);

        $this->audit($admin->id, 'agent.social_follow_rejected', $submission, ['reason' => $reason]);
        $this->notifyAgent(
            $submission,
            'agent.social_follow.rejected',
            'Priority task needs changes',
            'Your priority task screenshots were not approved: '.$reason.' Open the task, upload new screenshots, and submit again.',
        );

        return $submission->fresh('proofs');
    }

    /** @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, AgentSocialFollowSubmission> */
    public function adminQueue(int $perPage = 25)
    {
        return AgentSocialFollowSubmission::query()
            ->where('status', AgentSocialFollowSubmission::STATUS_SUBMITTED)
            ->with(['user', 'proofs'])
            ->orderBy('submitted_at')
            ->paginate($perPage);
    }

    public function pendingReviewCount(): int
    {
        if (! $this->tablesReady()) {
            return 0;
        }

        return AgentSocialFollowSubmission::query()
            ->where('status', AgentSocialFollowSubmission::STATUS_SUBMITTED)
            ->count();
    }

    private function notifyAdmins(AgentSocialFollowSubmission $submission): void
    {
        try {
            $this->notifications->notifyAdmins(new NotificationMessage(
                type: 'agent.social_follow.submitted',
                title: 'Agent priority task submitted',
                body: 'An agent submitted priority task screenshots for review.',
                actionUrl: Route::has('admin.social-follows.show')
                    ? route('admin.social-follows.show', $submission)
                    : null,
                meta: ['submission_id' => $submission->id, 'user_id' => $submission->user_id],
                emailSubject: 'Agent priority task submitted',
                permission: 'users.manage',
                dedupeKey: 'agent.social_follow.submitted.'.$submission->id.'.'.($submission->submitted_at?->timestamp ?? time()),
            ));
        } catch (\Throwable $e) {
            Log::warning('agent.social_follow.admin_notify_failed', ['id' => $submission->id, 'error' => $e->getMessage()]);
        }
    }

    private function notifyAgent(AgentSocialFollowSubmission $submission, string $type, string $title, string $body): void
    {
        try {
            $user = $submission->user()->first();
            if (! $user) {
                return;
            }
            $this->notifications->notifyUser($user, new NotificationMessage(
                type: $type,
                title: $title,
                body: $body,
                actionUrl: Route::has('agent.social-follow')
                    ? route('agent.social-follow')
                    : (Route::has('agent.marketplace') ? route('agent.marketplace') : null),
                meta: ['submission_id' => $submission->id],
                emailSubject: $title,
                dedupeKey: $type.'.'.$submission->id.'.'.($submission->reviewed_at?->timestamp ?? time()),
            ), ['database', 'mail']);
        } catch (\Throwable $e) {
            Log::warning('agent.social_follow.agent_notify_failed', ['id' => $submission->id, 'error' => $e->getMessage()]);
        }
    }

    private function audit(?int $adminId, string $action, AgentSocialFollowSubmission $submission, array $extra = []): void
    {
        try {
            app(AuditLogService::class)->log($adminId, $action, $submission, null, array_merge([
                'user_id' => $submission->user_id,
                'status' => $submission->status,
            ], $extra));
        } catch (\Throwable $e) {
            Log::warning('agent.social_follow.audit_failed', ['id' => $submission->id, 'error' => $e->getMessage()]);
        }
    }
}
