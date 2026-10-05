<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EngagementMetric;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignAdminController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $query = Campaign::query()
            ->with(['creator', 'product'])
            ->orderByDesc('created_at');

        if ($status !== '' && in_array($status, Campaign::STATUSES, true)) {
            $query->where('status', $status);
        }

        return view('dashboard.admin.campaigns.index', [
            'campaigns' => $query->paginate(25)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(Campaign $campaign): View
    {
        $campaign->load([
            'creator',
            'product',
            'variant',
            'order',
            'orderItem',
            'participations' => fn ($q) => $q->with('agent')->orderByDesc('created_at'),
        ]);

        $metric = EngagementMetric::tryFrom((string) ($campaign->engagement_metric ?? ''))
            ?? EngagementMetric::fromProductSlug($campaign->product?->slug);
        $quantity = max(0, (int) $campaign->quantity);
        $reward = (string) $campaign->locked_agent_reward;

        $totalCost = $campaign->totalCost();
        $unitCost = $quantity > 0 ? bcdiv($totalCost, (string) $quantity, 4) : '0';
        $agentBudget = bcmul($reward, (string) $quantity, 2);

        $participations = $campaign->participations;
        $paidStatuses = [CampaignParticipation::STATUS_APPROVED, CampaignParticipation::STATUS_PAID];
        $pendingStatuses = [
            CampaignParticipation::STATUS_SUBMITTED,
            CampaignParticipation::STATUS_UNDER_REVIEW,
            CampaignParticipation::STATUS_VERIFYING,
        ];

        $paidToAgents = $participations
            ->whereIn('status', $paidStatuses)
            ->reduce(fn (string $sum, CampaignParticipation $p) => bcadd($sum, (string) $p->reward_amount, 2), '0.00');
        $awaitingReview = $participations
            ->whereIn('status', $pendingStatuses)
            ->reduce(fn (string $sum, CampaignParticipation $p) => bcadd($sum, (string) $p->reward_amount, 2), '0.00');

        return view('dashboard.admin.campaigns.show', [
            'campaign' => $campaign,
            'metric' => $metric,
            'unitLabel' => $campaign->variant?->resolveUnitLabelPlural($metric)
                ?? ($metric ? strtolower($metric->label()) : 'units'),
            'finance' => [
                'total_cost' => $totalCost,
                'unit_cost' => $unitCost,
                'agent_reward' => $reward,
                'agent_budget' => $agentBudget,
                'platform_share' => bcsub($totalCost, $agentBudget, 2),
                'paid_to_agents' => $paidToAgents,
                'awaiting_review' => $awaitingReview,
                'agent_budget_left' => bcsub($agentBudget, $paidToAgents, 2),
            ],
            'counts' => [
                'in_progress' => $participations->where('status', CampaignParticipation::STATUS_STARTED)->count(),
                'awaiting_review' => $participations->whereIn('status', $pendingStatuses)->count(),
                'paid' => $participations->whereIn('status', $paidStatuses)->count(),
                'rejected' => $participations->where('status', CampaignParticipation::STATUS_REJECTED)->count(),
            ],
        ]);
    }

    public function updateStatus(Request $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', Campaign::STATUSES)],
        ]);

        if ($validated['status'] === Campaign::STATUS_ACTIVE && $campaign->remainingSlots() < 1) {
            return back()->with('error', __('All units on this campaign are already delivered. Set it to Completed instead.'));
        }

        $campaign->update(['status' => $validated['status']]);

        return back()->with('status', __('Campaign status changed to :status.', ['status' => $campaign->statusLabel()]));
    }
}
