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
use App\Services\Media\ScreenshotOptimizer;
use App\Services\Engagement\VideoEmbedResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

    public function submit(Request $request, CampaignParticipation $participation): RedirectResponse|JsonResponse
    {
        $this->authorizeAgent($participation);
        $participation->loadMissing('campaign.product');
        $metric = $participation->campaign?->taskProfile()->legacyMetric();
        $needsProof = (bool) ($metric?->usesCountChangeVerification() || $metric?->targetsChannel());

        $validated = $request->validate([
            'proof' => [$needsProof ? 'required_without:proof_url' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'proof_url' => [$needsProof ? 'required_without:proof' : 'nullable', 'url', 'max:2048'],
            'proof_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'proof.max' => 'Screenshots must be 2 MB or smaller.',
        ]);

        try {
            $validated['proof_url'] = $this->proofUrlFromRequest($request, $validated['proof_url'] ?? null);
            $this->participations->submit($participation, $validated);
        } catch (\Throwable $e) {
            return $this->taskError($request, $e->getMessage());
        }

        return $this->taskRedirect($request, $participation, __('Submission sent for verification.'));
    }

    public function saveStep(Request $request, CampaignParticipation $participation, string $task): RedirectResponse|JsonResponse
    {
        $this->authorizeAgent($participation);
        $step = CampaignTask::tryFrom($task);
        abort_unless($step && in_array($step, [CampaignTask::Subscribe, CampaignTask::Comment], true), 404);

        $validated = $request->validate([
            'proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'proof_url' => [$step === CampaignTask::Comment ? 'required' : 'nullable', 'url', 'max:2048'],
            'proof_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'proof.max' => 'Screenshots must be 2 MB or smaller.',
        ]);

        try {
            if ($step !== CampaignTask::Comment) {
                $validated['proof_url'] = $this->proofUrlFromRequest($request, $validated['proof_url'] ?? null);
            }
            $this->taskFlow->saveStep($participation, $request->user(), $step, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->taskError($request, $e->getMessage());
        }

        return $this->taskRedirect($request, $participation, __(':step marked done. Proceed when you are ready.', ['step' => $step->label()]));
    }

    public function proceed(Request $request, CampaignParticipation $participation): RedirectResponse|JsonResponse
    {
        $this->authorizeAgent($participation);

        try {
            $next = $this->taskFlow->proceed($participation, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->taskError($request, $e->getMessage());
        }

        return $this->taskRedirect($request, $participation, $next
            ? __('Next step: :step.', ['step' => $next->taskEnum()?->label() ?? $next->task])
            : __('All steps are done. Submit the task when you are ready.'));
    }

    public function submitTask(Request $request, CampaignParticipation $participation): RedirectResponse|JsonResponse
    {
        $this->authorizeAgent($participation);

        try {
            $this->taskFlow->submit($participation, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->taskError($request, $e->getMessage());
        }

        return $this->taskRedirect($request, $participation, __('Task submitted. We are checking each step now.'));
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

    public function claimWatch(Request $request, CampaignParticipation $participation): RedirectResponse|JsonResponse
    {
        $this->authorizeAgent($participation);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        try {
            $this->watchSessions->claim($participation, $validated['token']);
        } catch (\Throwable $e) {
            return $this->taskError($request, $e->getMessage());
        }

        $usesSteps = (bool) $participation->campaign?->usesStepFlow();

        return $this->taskRedirect($request, $participation, $usesSteps
            ? __('Watch step done. Proceed when you are ready.')
            : __('Reward claimed. Thanks for completing this task.'));
    }

    private function proofUrlFromRequest(Request $request, ?string $postedUrl): ?string
    {
        if ($request->hasFile('proof')) {
            $path = app(ScreenshotOptimizer::class)->store($request->file('proof'), 'task-proofs/'.now()->format('Y/m'));

            return $this->publicProofUrl($path);
        }

        $postedUrl = trim((string) $postedUrl);

        return $postedUrl !== '' ? $postedUrl : null;
    }

    /** Use the site the agent is actually on, not APP_URL, which may still be localhost. */
    private function publicProofUrl(string $path): string
    {
        $relative = Storage::disk('public')->url($path);
        if (preg_match('#^https?://#i', $relative)) {
            return $relative;
        }

        return rtrim(request()->getSchemeAndHttpHost(), '/').'/'.ltrim($relative, '/');
    }

    private function taskRedirect(Request $request, CampaignParticipation $participation, string $status): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            session()->flash('status', $status);

            return response()->json([
                'redirect' => route('agent.tasks.show', $participation),
            ]);
        }

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('status', $status);
    }

    private function taskError(Request $request, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 422)
            : back()->withInput()->with('error', $message);
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
