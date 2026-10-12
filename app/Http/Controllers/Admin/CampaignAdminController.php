<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignBudget;
use App\Models\CampaignParticipation;
use App\Modules\Admin\Services\AuditLogService;
use App\Services\Campaigns\CampaignBudgetService;
use App\Services\Campaigns\ParticipationExpiryService;
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

        $heldTotals = null;
        if (CampaignBudgetService::enabled()) {
            $held = CampaignBudget::query()->where('status', CampaignBudget::STATUS_HELD);
            $heldTotals = [
                'count' => (clone $held)->count(),
                'remaining' => number_format(max(0, (float) (clone $held)->selectRaw('COALESCE(SUM(funded_amount - paid_amount - refunded_amount - released_amount), 0) as t')->value('t')), 2, '.', ''),
                'paid' => number_format((float) CampaignBudget::query()->sum('paid_amount'), 2, '.', ''),
            ];
        }

        return view('dashboard.admin.campaigns.index', [
            'campaigns' => $query->paginate(25)->withQueryString(),
            'heldTotals' => $heldTotals,
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

        $profile = $campaign->taskProfile();
        $metric = $profile->legacyMetric();
        $budget = CampaignBudgetService::enabled() ? $campaign->budget()->first() : null;
        $quantity = max(0, (int) $campaign->quantity);
        $reward = (string) $campaign->locked_agent_reward;

        $totalCost = $campaign->totalCost();
        $unitCost = $quantity > 0 ? bcdiv($totalCost, (string) $quantity, 4) : '0';
        $agentBudget = bcmul($reward, (string) $quantity, 4);

        $participations = $campaign->participations;
        $paidStatuses = [CampaignParticipation::STATUS_APPROVED, CampaignParticipation::STATUS_PAID];
        $pendingStatuses = [
            CampaignParticipation::STATUS_SUBMITTED,
            CampaignParticipation::STATUS_UNDER_REVIEW,
            CampaignParticipation::STATUS_VERIFYING,
        ];

        $paidToAgents = $participations
            ->whereIn('status', $paidStatuses)
            ->reduce(fn (string $sum, CampaignParticipation $p) => bcadd($sum, (string) $p->reward_amount, 4), '0.0000');
        $awaitingReview = $participations
            ->whereIn('status', $pendingStatuses)
            ->reduce(fn (string $sum, CampaignParticipation $p) => bcadd($sum, (string) $p->reward_amount, 4), '0.0000');

        return view('dashboard.admin.campaigns.show', [
            'campaign' => $campaign,
            'metric' => $metric,
            'profile' => $profile,
            'budget' => $budget,
            'unitLabel' => $campaign->variant?->resolveUnitLabelPlural($metric)
                ?? ($metric ? strtolower($metric->label()) : 'units'),
            'finance' => [
                'total_cost' => $totalCost,
                'unit_cost' => $unitCost,
                'agent_reward' => $reward,
                'agent_budget' => $agentBudget,
                'platform_share' => bcsub($totalCost, $agentBudget, 4),
                'paid_to_agents' => $paidToAgents,
                'awaiting_review' => $awaitingReview,
                'agent_budget_left' => bcsub($agentBudget, $paidToAgents, 4),
            ],
            'counts' => [
                'in_progress' => $participations->where('status', CampaignParticipation::STATUS_STARTED)->count(),
                'awaiting_review' => $participations->whereIn('status', $pendingStatuses)->count(),
                'paid' => $participations->whereIn('status', $paidStatuses)->count(),
                'rejected' => $participations->where('status', CampaignParticipation::STATUS_REJECTED)->count(),
                'expired' => $participations->where('status', CampaignParticipation::STATUS_EXPIRED)->count(),
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

        $budget = $campaign->budget()->first();
        if ($budget && $budget->status !== CampaignBudget::STATUS_HELD
            && in_array($validated['status'], Campaign::WORKABLE_STATUSES, true)) {
            return back()->with('error', __('This campaign\'s budget was already settled or refunded, so it cannot be reopened.'));
        }

        // Agents can only finish and be paid while the campaign is workable (or cancelled with a held budget).
        $closing = [Campaign::STATUS_COMPLETED, Campaign::STATUS_SUSPENDED, Campaign::STATUS_REJECTED];
        if (in_array($validated['status'], $closing, true) && $validated['status'] !== $campaign->status) {
            $inFlight = app(CampaignBudgetService::class)->inFlightCount($campaign);
            if ($inFlight > 0) {
                return back()->with('error', trans_choice(
                    '{1} 1 agent task is still in progress or awaiting review. Pause the campaign, or cancel it (unused units are refunded once that task finishes), then try again.|[2,*] :count agent tasks are still in progress or awaiting review. Pause the campaign, or cancel it (unused units are refunded once those tasks finish), then try again.',
                    $inFlight,
                    ['count' => $inFlight],
                ));
            }
        }

        $previousStatus = $campaign->status;
        $campaign->update(['status' => $validated['status']]);

        app(AuditLogService::class)->logFromRequest(
            $request,
            $request->user()?->id,
            'campaign.status_changed',
            $campaign,
            ['status' => $previousStatus],
            ['status' => $campaign->status],
        );

        if (in_array($campaign->status, CampaignBudgetService::REFUNDING_STATUSES, true) && $previousStatus !== $campaign->status) {
            try {
                $refunded = app(CampaignBudgetService::class)->refundUndelivered($campaign);
            } catch (\InvalidArgumentException $e) {
                return back()->with('error', __('Campaign status changed to :status, but the refund could not be sent: :reason', [
                    'status' => $campaign->statusLabel(),
                    'reason' => $e->getMessage(),
                ]));
            }

            if ($refunded !== null && bccomp($refunded, '0', 2) > 0) {
                return back()->with('status', __('Campaign status changed to :status. :amount was refunded to the creator\'s wallet for undelivered units.', [
                    'status' => $campaign->statusLabel(),
                    'amount' => money($refunded),
                ]));
            }

            if ($refunded === null && $campaign->status === Campaign::STATUS_CANCELLED) {
                return back()->with('status', __('Campaign cancelled. New agents can no longer start it. The refund for unused units is sent automatically once tasks already in progress finish or expire (the creator needs a wallet).'));
            }
        }

        return back()->with('status', __('Campaign status changed to :status.', ['status' => $campaign->statusLabel()]));
    }

    /** Frees the slot of a started task the agent abandoned. Nothing is paid for it. */
    public function releaseParticipation(Request $request, Campaign $campaign, CampaignParticipation $participation): RedirectResponse
    {
        abort_unless((int) $participation->campaign_id === (int) $campaign->id, 404);

        if (! app(ParticipationExpiryService::class)->release($participation)) {
            return back()->with('error', __('Only tasks that are still in progress can be released.'));
        }

        app(AuditLogService::class)->logFromRequest(
            $request,
            $request->user()?->id,
            'campaign.participation_released',
            $participation,
            ['status' => CampaignParticipation::STATUS_STARTED],
            ['status' => CampaignParticipation::STATUS_EXPIRED],
            ['campaign_id' => $campaign->id, 'agent_id' => $participation->agent_id],
        );

        $campaign->refresh();
        if ($campaign->status === Campaign::STATUS_CANCELLED) {
            try {
                $refunded = app(CampaignBudgetService::class)->refundIfCancelled($campaign);
            } catch (\InvalidArgumentException $e) {
                return back()->with('error', __('Task released, but the refund could not be sent: :reason', ['reason' => $e->getMessage()]));
            }

            if ($refunded !== null) {
                return back()->with('status', __('Task released. :amount was refunded to the creator\'s wallet.', ['amount' => money($refunded)]));
            }
        }

        return back()->with('status', __('Task released. The agent was told, and the slot is open again.'));
    }
}
