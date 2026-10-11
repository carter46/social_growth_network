<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignTask;
use App\Http\Controllers\Controller;
use App\Models\AgentYoutubeIdentity;
use App\Models\CampaignParticipation;
use App\Models\CampaignParticipationTask;
use App\Modules\Admin\Services\AuditLogService;
use App\Services\Campaigns\CampaignParticipationService;
use App\Services\Campaigns\StepVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationAdminController extends Controller
{
    public function __construct(
        private CampaignParticipationService $participations,
        private StepVerificationService $steps,
    ) {}

    public function index(): View
    {
        $queue = CampaignParticipation::query()
            ->whereIn('status', [
                CampaignParticipation::STATUS_SUBMITTED,
                CampaignParticipation::STATUS_UNDER_REVIEW,
                CampaignParticipation::STATUS_VERIFYING,
            ])
            ->with(['campaign', 'agent'])
            ->withCount('steps')
            ->orderBy('submitted_at')
            ->paginate(25);

        return view('dashboard.admin.verifications.index', [
            'queue' => $queue,
        ]);
    }

    public function show(CampaignParticipation $participation): View
    {
        $participation->load(['campaign.product', 'agent', 'steps']);

        $identity = AgentYoutubeIdentity::query()
            ->where('user_id', $participation->agent_id)
            ->whereIn('status', [AgentYoutubeIdentity::STATUS_VERIFIED, AgentYoutubeIdentity::STATUS_PENDING])
            ->latest('id')
            ->first();

        return view('dashboard.admin.verifications.show', [
            'participation' => $participation,
            'steps' => $participation->steps,
            'identity' => $identity,
            'commentCheck' => session('comment_check'),
        ]);
    }

    public function approve(Request $request, CampaignParticipation $participation): RedirectResponse
    {
        $previousStatus = $participation->status;

        try {
            if ($participation->campaign?->usesStepFlow()) {
                $this->steps->adminApprove($participation, auth()->user());
            } else {
                $this->participations->approve($participation, auth()->user());
            }
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit($request, 'campaign.participation_approved', $participation, $previousStatus);

        return redirect()
            ->route('admin.verifications')
            ->with('status', __('Submission approved and agent rewarded.'));
    }

    public function reject(Request $request, CampaignParticipation $participation): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $previousStatus = $participation->status;

        try {
            if ($participation->campaign?->usesStepFlow()) {
                $this->steps->adminReject($participation, auth()->user(), $validated['rejection_reason']);
            } else {
                $this->participations->reject($participation, auth()->user(), $validated['rejection_reason']);
            }
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit($request, 'campaign.participation_rejected', $participation, $previousStatus, ['reason' => $validated['rejection_reason']]);

        return redirect()
            ->route('admin.verifications')
            ->with('status', __('Submission rejected.'));
    }

    private function audit(Request $request, string $action, CampaignParticipation $participation, ?string $previousStatus, array $context = []): void
    {
        $fresh = $participation->fresh();

        app(AuditLogService::class)->logFromRequest(
            $request,
            $request->user()?->id,
            $action,
            $fresh ?? $participation,
            ['status' => $previousStatus],
            ['status' => $fresh?->status, 'reward_amount' => (string) $fresh?->reward_amount],
            $context + ['campaign_id' => $participation->campaign_id, 'agent_id' => $participation->agent_id],
        );
    }

    /** Looks the comment up on YouTube right now so the reviewer sees its current state. */
    public function checkComment(CampaignParticipation $participation): RedirectResponse
    {
        $step = CampaignParticipationTask::query()
            ->where('participation_id', $participation->id)
            ->where('task', CampaignTask::Comment->value)
            ->first();

        if (! $step) {
            return back()->with('error', __('This task has no comment step.'));
        }

        return back()->with('comment_check', $this->steps->liveCommentCheck($step));
    }
}
