<?php

namespace App\Http\Controllers\Agent;

use App\Enums\EngagementMetric;
use App\Http\Controllers\Controller;
use App\Models\CampaignParticipation;
use App\Services\Campaigns\CampaignParticipationService;
use App\Services\Campaigns\CampaignWatchSessionService;
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
        private VideoEmbedResolver $embeds,
    ) {}

    public function active(): View
    {
        return $this->list(['started', 'submitted', 'under_review', 'verifying'], 'Active tasks');
    }

    public function incomplete(): View
    {
        return $this->list(['rejected'], 'Incomplete tasks');
    }

    public function completed(): View
    {
        return $this->list(['approved', 'paid'], 'Completed tasks');
    }

    public function show(CampaignParticipation $participation): View
    {
        $this->authorizeAgent($participation);
        $participation->load('campaign.product');
        $campaign = $participation->campaign;
        $metric = EngagementMetric::tryFrom((string) ($campaign?->engagement_metric ?? ''))
            ?? EngagementMetric::fromProductSlug($campaign?->product?->slug);
        $platform = EngagementMetric::platformFromProductSlug($campaign?->product?->slug)
            ?? ($campaign?->meta['platform'] ?? null);

        $taskMode = 'proof';
        if ($metric?->usesWatchSession()) {
            $taskMode = 'watch_session';
        } elseif ($metric?->usesCountChangeVerification()) {
            $taskMode = 'count_change_proof';
        } elseif ($metric?->targetsChannel()) {
            $taskMode = 'subscribe';
        }

        $embed = $campaign?->target_url
            ? $this->embeds->resolve($campaign->target_url, $platform)
            : null;

        return view('dashboard.agent.tasks.show', [
            'participation' => $participation,
            'metric' => $metric,
            'platform' => $platform,
            'taskMode' => $taskMode,
            'embed' => $embed,
        ]);
    }

    public function submit(Request $request, CampaignParticipation $participation): RedirectResponse
    {
        $this->authorizeAgent($participation);
        $participation->loadMissing('campaign.product');
        $metric = EngagementMetric::tryFrom((string) ($participation->campaign?->engagement_metric ?? ''))
            ?? EngagementMetric::fromProductSlug($participation->campaign?->product?->slug);

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

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('status', __('Reward claimed. Thanks for completing this task.'));
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

    private function authorizeAgent(CampaignParticipation $participation): void
    {
        abort_unless((int) $participation->agent_id === (int) auth()->id(), 403);
    }
}
