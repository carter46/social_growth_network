<?php

namespace App\Http\Controllers\Agent;

use App\Enums\EngagementMetric;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Services\Campaigns\AgentEligibilityService;
use App\Services\Campaigns\CampaignParticipationService;
use App\Services\Campaigns\YoutubeChannelRequiredException;
use App\Services\Campaigns\YoutubeIdentityService;
use App\Services\Engagement\VideoEmbedResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function __construct(
        private CampaignParticipationService $participations,
        private AgentEligibilityService $eligibility,
        private YoutubeIdentityService $identities,
        private VideoEmbedResolver $embeds,
    ) {}

    public function index(): View
    {
        $query = Campaign::query()
            ->openForAgents()
            ->whereDoesntHave('participations', function ($q) {
                $q->where('agent_id', auth()->id())
                    ->where('status', '!=', CampaignParticipation::STATUS_EXPIRED);
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

        $openParticipation = $participation && $participation->status !== CampaignParticipation::STATUS_EXPIRED
            ? $participation
            : null;

        if (! $openParticipation) {
            $reason = $this->eligibility->conflictReason($agent, $campaign)
                ?? $this->eligibility->precheckWithApi($agent, $campaign);
            if ($reason !== null) {
                $this->eligibility->logRefusal($agent, $campaign, $reason);
                abort(403, AgentEligibilityService::NOT_AVAILABLE);
            }
        }

        $needsChannel = ! $openParticipation
            && YoutubeIdentityService::requiredFor($campaign)
            && ! $this->identities->verified($agent);

        $campaign->load('product.heroMedia.variants');
        $platform = EngagementMetric::platformFromProductSlug($campaign->product?->slug)
            ?? ($campaign->meta['platform'] ?? null);

        return view('dashboard.agent.marketplace.show', [
            'campaign' => $campaign,
            'participation' => $openParticipation,
            'needsChannel' => $needsChannel,
            'channelIdentity' => $needsChannel ? $this->identities->current($agent) : null,
            'oauthEnabled' => YoutubeIdentityService::oauthEnabled(),
            'embed' => $campaign->target_url
                ? $this->embeds->resolve($campaign->target_url, $platform)
                : null,
        ]);
    }

    public function start(Request $request, Campaign $campaign): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $participation = $this->participations->start($request->user(), $campaign);
        } catch (YoutubeChannelRequiredException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('agent.marketplace.show', $campaign)
                ->with('youtube_channel_required', true)
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $status = $campaign->usesStepFlow()
            ? __('Task started.')
            : __('Task started.');

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('agent.tasks.show', $participation),
                'message' => $status,
            ]);
        }

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('status', $status);
    }
}
