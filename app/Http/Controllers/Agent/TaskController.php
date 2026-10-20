<?php

namespace App\Http\Controllers\Agent;

use App\Enums\CampaignTask;
use App\Enums\EngagementMetric;
use App\Http\Controllers\Controller;
use App\Models\CampaignParticipation;
use App\Services\Campaigns\CampaignParticipationService;
use App\Services\Campaigns\CampaignTaskFlowService;
use App\Services\Campaigns\CampaignWatchSessionService;
use App\Services\Campaigns\ParticipationExpiryService;
use App\Services\Engagement\VideoEmbedResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(
        private CampaignParticipationService $participations,
        private CampaignWatchSessionService $watchSessions,
        private CampaignTaskFlowService $taskFlow,
        private ParticipationExpiryService $expiry,
        private VideoEmbedResolver $embeds,
    ) {}

    public function active(): View
    {
        return $this->list(['started', 'submitted', 'under_review', 'verifying'], 'Active tasks');
    }

    public function incomplete(): View
    {
        return $this->list(['rejected', CampaignParticipation::STATUS_EXPIRED], 'Incomplete tasks');
    }

    public function completed(): View
    {
        return $this->list(['approved', 'paid'], 'Completed tasks');
    }

    public function show(CampaignParticipation $participation): View
    {
        $this->authorizeAgent($participation);
        $this->expireIfOverdue($participation);
        $participation->refresh()->load(['campaign.product', 'steps']);
        $campaign = $participation->campaign;
        $profile = $campaign?->taskProfile();
        $metric = $profile?->legacyMetric();
        $platform = EngagementMetric::platformFromProductSlug($campaign?->product?->slug)
            ?? ($campaign?->meta['platform'] ?? null);

        $usesSteps = (bool) $campaign?->usesStepFlow();

        $taskMode = 'proof';
        if ($usesSteps) {
            $taskMode = 'steps';
        } elseif ($metric?->usesWatchSession()) {
            $taskMode = 'watch_session';
        } elseif ($metric?->usesCountChangeVerification()) {
            $taskMode = 'count_change_proof';
        } elseif ($metric?->targetsChannel()) {
            $taskMode = 'subscribe';
        }

        $embed = $campaign?->target_url
            ? $this->embeds->resolve($campaign->target_url, $platform)
            : null;

        $requiredSeconds = $campaign && $profile?->has(\App\Enums\CampaignTask::Watch)
            ? $this->watchSessions->requiredSecondsFor($campaign)
            : max(60, (int) ($campaign?->estimated_minutes ?: 1) * 60);

        return view('dashboard.agent.tasks.show', [
            'participation' => $participation,
            'metric' => $metric,
            'profile' => $profile,
            'platform' => $platform,
            'taskMode' => $taskMode,
            'embed' => $embed,
            'steps' => $usesSteps ? $participation->steps : collect(),
            'secondsRemaining' => $participation->secondsRemaining(),
            'totalSeconds' => $campaign?->completion_minutes ? (int) $campaign->completion_minutes * 60 : null,
            'requiredSeconds' => $requiredSeconds,
        ]);
    }

    public function submit(Request $request, CampaignParticipation $participation): RedirectResponse
    {
        $this->authorizeAgent($participation);
        $participation->loadMissing('campaign.product');
        $metric = $participation->campaign?->taskProfile()->legacyMetric();

        $rules = [
            'proof_url' => ['nullable', 'url', 'max:2048'],
            'proof_notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($metric?->usesCountChangeVerification() || $metric?->targetsChannel()) {
            $rules['proof_url'] = ['required', 'url', 'max:2048'];
        }

        $validated = $request->validate($rules);

        try {
            $this->participations->submit($participation, $validated);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('status', __('Submission sent for verification.'));
    }

    public function saveStep(Request $request, CampaignParticipation $participation, string $task): RedirectResponse
    {
        $this->authorizeAgent($participation);
        $step = CampaignTask::tryFrom($task);
        abort_unless($step && in_array($step, [CampaignTask::Subscribe, CampaignTask::Comment], true), 404);

        $validated = $request->validate([
            'proof_url' => [$step === CampaignTask::Comment ? 'required' : 'nullable', 'url', 'max:2048'],
            'proof_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->taskFlow->saveStep($participation, $request->user(), $step, $validated);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('status', __(':step marked done. Proceed when you are ready.', ['step' => $step->label()]));
    }

    public function proceed(Request $request, CampaignParticipation $participation): RedirectResponse
    {
        $this->authorizeAgent($participation);

        try {
            $next = $this->taskFlow->proceed($participation, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('status', $next
                ? __('Next step: :step.', ['step' => $next->taskEnum()?->label() ?? $next->task])
                : __('All steps are done. Submit the task when you are ready.'));
    }

    public function submitTask(Request $request, CampaignParticipation $participation): RedirectResponse
    {
        $this->authorizeAgent($participation);

        try {
            $this->taskFlow->submit($participation, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('status', __('Task submitted. We are checking each step now.'));
    }

    /** Keeps the session alive while a task page is open and reports the time left. */
    public function heartbeat(Request $request, CampaignParticipation $participation): JsonResponse
    {
        $this->authorizeAgent($participation);

        $participation->refresh();
        if ($participation->status === CampaignParticipation::STATUS_STARTED && $participation->isPastDeadline()) {
            $this->expiry->expire($participation);
            $participation->refresh();
        }

        if ($participation->status === CampaignParticipation::STATUS_STARTED) {
            $participation->forceFill(['last_heartbeat_at' => now()])->save();
        }

        return response()->json([
            'status' => $participation->status,
            'expired' => $participation->status === CampaignParticipation::STATUS_EXPIRED,
            'seconds_remaining' => $participation->secondsRemaining(),
        ]);
    }

    public function startWatch(Request $request, CampaignParticipation $participation): RedirectResponse|JsonResponse
    {
        $this->authorizeAgent($participation);

        try {
            $session = $this->watchSessions->start($participation);
        } catch (\Throwable $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'token' => $session->token,
                'required_seconds' => (int) $session->required_seconds,
            ]);
        }

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('watch_token', $session->token)
            ->with('status', __('Watch session started. Keep this page open until the timer finishes.'));
    }

    public function claimWatch(Request $request, CampaignParticipation $participation): RedirectResponse
    {
        $this->authorizeAgent($participation);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        try {
            $this->watchSessions->claim($participation, $validated['token']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $usesSteps = (bool) $participation->campaign?->usesStepFlow();

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('status', $usesSteps
                ? __('Watch step done. Proceed when you are ready.')
                : __('Reward claimed. Thanks for completing this task.'));
    }

    /**
     * @param  list<string>  $statuses
     */
    private function list(array $statuses, string $title): View
    {
        $participations = CampaignParticipation::query()
            ->where('agent_id', auth()->id())
            ->whereIn('status', $statuses)
            ->with('campaign')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('dashboard.agent.tasks.index', [
            'participations' => $participations,
            'title' => $title,
        ]);
    }

    private function expireIfOverdue(CampaignParticipation $participation): void
    {
        if ($participation->status === CampaignParticipation::STATUS_STARTED && $participation->isPastDeadline()) {
            $this->expiry->expire($participation);
        }
    }

    private function authorizeAgent(CampaignParticipation $participation): void
    {
        abort_unless((int) $participation->agent_id === (int) auth()->id(), 403);
    }
}
