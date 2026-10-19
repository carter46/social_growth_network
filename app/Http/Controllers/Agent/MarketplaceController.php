<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Services\Campaigns\AgentEligibilityService;
use App\Services\Campaigns\CampaignParticipationService;
use App\Services\Campaigns\YoutubeChannelRequiredException;
use App\Services\Campaigns\YoutubeIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function __construct(
        private CampaignParticipationService $participations,
        private AgentEligibilityService $eligibility,
        private YoutubeIdentityService $identities,
    ) {}

    public function index(): View
    {
        $query = Campaign::query()
            ->openForAgents()
            ->whereDoesntHave('participations', function ($q) {
                $q->where('agent_id', auth()->id());
            });

        $campaigns = $this->eligibility->applyToQuery($query, auth()->user())
            ->with([
                'product',
                'creator.verifiedYoutubeIdentity',
            ])
            ->whereHas('creator', fn ($q) => $q->where('is_suspended', false))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('dashboard.agent.marketplace.index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function show(Campaign $campaign): View
    {
        $agent = auth()->user();
        $participation = CampaignParticipation::query()
            ->where('campaign_id', $campaign->id)
            ->where('agent_id', $agent->id)
            ->first();

        abort_unless($campaign->isOpenForAgents() || $participation, 404);

        if (! $participation) {
            $reason = $this->eligibility->conflictReason($agent, $campaign)
                ?? $this->eligibility->precheckWithApi($agent, $campaign);
            if ($reason !== null) {
                $this->eligibility->logRefusal($agent, $campaign, $reason);
                abort(403, AgentEligibilityService::NOT_AVAILABLE);
            }
        }

        $needsChannel = ! $participation
            && YoutubeIdentityService::requiredFor($campaign)
            && ! $this->identities->verified($agent);

        return view('dashboard.agent.marketplace.show', [
            'campaign' => $campaign->load('product.heroMedia.variants'),
            'participation' => $participation,
            'needsChannel' => $needsChannel,
            'channelIdentity' => $needsChannel ? $this->identities->current($agent) : null,
            'oauthEnabled' => YoutubeIdentityService::oauthEnabled(),
        ]);
    }

    public function start(Campaign $campaign): RedirectResponse
    {
        try {
            $participation = $this->participations->start(auth()->user(), $campaign);
        } catch (YoutubeChannelRequiredException $e) {
            return redirect()
                ->route('agent.marketplace.show', $campaign)
                ->with('youtube_channel_required', true)
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('status', $campaign->usesStepFlow()
                ? __('Task started. Complete each step in order, then submit the task.')
                : __('Task started. Complete the requirements and submit proof.'));
    }
}
