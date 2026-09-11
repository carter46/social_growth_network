<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Services\Campaigns\CampaignParticipationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function __construct(
        private CampaignParticipationService $participations,
    ) {}

    public function index(): View
    {
        $campaigns = Campaign::query()
            ->openForAgents()
            ->whereDoesntHave('participations', function ($q) {
                $q->where('agent_id', auth()->id());
            })
            ->with('product')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('dashboard.agent.marketplace.index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function show(Campaign $campaign): View
    {
        abort_unless($campaign->isOpenForAgents() || $this->agentHasParticipation($campaign), 404);

        $participation = CampaignParticipation::query()
            ->where('campaign_id', $campaign->id)
            ->where('agent_id', auth()->id())
            ->first();

        return view('dashboard.agent.marketplace.show', [
            'campaign' => $campaign->load('product'),
            'participation' => $participation,
        ]);
    }

    public function start(Campaign $campaign): RedirectResponse
    {
        try {
            $participation = $this->participations->start(auth()->user(), $campaign);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('status', __('Task started. Complete the requirements and submit proof.'));
    }

    private function agentHasParticipation(Campaign $campaign): bool
    {
        return CampaignParticipation::query()
            ->where('campaign_id', $campaign->id)
            ->where('agent_id', auth()->id())
            ->exists();
    }
}
