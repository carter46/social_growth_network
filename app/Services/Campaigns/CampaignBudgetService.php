<?php

namespace App\Services\Campaigns;

use App\Enums\TransactionType;
use App\Models\Campaign;
use App\Models\CampaignBudget;
use App\Models\CampaignParticipation;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Admin\Services\AuditLogService;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Referrals\ReferralCommissionService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * The agent budget of a campaign (reward x quantity) is held in the platform wallet's
 * locked balance when the campaign is created. Every reward is paid from that hold once.
 */
class CampaignBudgetService
{
    public const FUNDING_UNFUNDED = 'unfunded';

    /** Participations that may still be paid from the hold. */
    public const IN_FLIGHT_STATUSES = [
        CampaignParticipation::STATUS_STARTED,
        CampaignParticipation::STATUS_SUBMITTED,
        CampaignParticipation::STATUS_UNDER_REVIEW,
        CampaignParticipation::STATUS_VERIFYING,
        CampaignParticipation::STATUS_APPROVED,
    ];

    public function __construct(
        private WalletService $wallets,
    ) {}

    public static function enabled(): bool
    {
        return Schema::hasTable('campaign_budgets');
    }

    public function fundedAmountFor(Campaign $campaign, ?int $units = null): string
    {
        $units ??= (int) $campaign->quantity;

        return bcmul((string) $campaign->locked_agent_reward, (string) max(0, $units), 4);
    }

    /**
     * Hold the agent budget for a newly created campaign. Returns null (legacy payout path)
     * when there is nothing to hold or the platform wallet can't cover it.
     */
    public function holdForCampaign(Campaign $campaign, ?int $units = null): ?CampaignBudget
    {
        if (! self::enabled()) {
            return null;
        }

        $existing = CampaignBudget::query()->where('campaign_id', $campaign->id)->first();
        if ($existing) {
            return $existing;
        }

        $amount = $this->fundedAmountFor($campaign, $units);
        if (bccomp($amount, '0', 4) <= 0) {
            return null;
        }

        try {
            return DB::transaction(function () use ($campaign, $amount) {
                $this->wallets->holdCampaignBudget($campaign->id, $amount, $campaign->order_id);

                return CampaignBudget::query()->create([
                    'campaign_id' => $campaign->id,
                    'funded_amount' => $amount,
                    'paid_amount' => 0,
                    'refunded_amount' => 0,
                    'released_amount' => 0,
                    'status' => CampaignBudget::STATUS_HELD,
                ]);
            });
        } catch (InvalidArgumentException $e) {
            Log::channel('financial')->warning('Campaign budget not held', [
                'campaign_id' => $campaign->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            $this->flagUnfunded($campaign, $amount);

            return null;
        }
    }

    public static function isUnfunded(Campaign $campaign): bool
    {
        return (($campaign->meta ?? [])['funding'] ?? null) === self::FUNDING_UNFUNDED;
    }

    /**
     * The campaign stays live without a hold, so rewards are paid from platform revenue.
     * Admins are told so they can top up the platform wallet and run campaigns:backfill-budgets.
     */
    private function flagUnfunded(Campaign $campaign, string $amount): void
    {
        $meta = is_array($campaign->meta) ? $campaign->meta : [];
        $meta['funding'] = self::FUNDING_UNFUNDED;
        $meta['funding_shortfall'] = $amount;
        $campaign->forceFill(['meta' => $meta])->save();

        $campaignId = (int) $campaign->id;
        $title = (string) $campaign->title;
        DB::afterCommit(function () use ($campaignId, $title, $amount) {
            try {
                app(NotificationDispatcher::class)->notifyAdmins(new NotificationMessage(
                    type: 'campaign.budget_unfunded',
                    title: 'Campaign budget not held',
                    body: 'Campaign #'.$campaignId.' ('.$title.') went live without its agent budget of '.money((float) $amount, 2)
                        .' held, because the platform wallet could not cover it. Top up the platform wallet, then run campaigns:backfill-budgets.',
                    actionUrl: Route::has('admin.campaigns.show') ? route('admin.campaigns.show', $campaignId) : null,
                    meta: ['campaign_id' => $campaignId, 'amount' => $amount],
                    dedupeKey: 'campaign-unfunded-'.$campaignId,
                ), ['database']);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Pay a participation's reward. Must run inside the approve() transaction, after the
     * participation and campaign rows are locked. Budgeted campaigns pay from the hold
     * (capped at the funded amount); legacy campaigns keep the credit-only path.
     */
    public function payReward(CampaignParticipation $participation, Campaign $campaign, User $agent, string $amount): void
    {
        $label = 'Campaign reward: '.$campaign->title;
        $budget = self::enabled()
            ? CampaignBudget::query()->where('campaign_id', $campaign->id)->lockForUpdate()->first()
            : null;

        if (! $budget) {
            $this->wallets->creditFundedReward(
                $agent,
                $amount,
                $label,
                TransactionType::CampaignReward,
                TransactionType::UnfundedCampaignRewardExpense,
                'CMP-UNF-'.$participation->id,
            );

            return;
        }

        if ($budget->status !== CampaignBudget::STATUS_HELD) {
            throw new InvalidArgumentException('This campaign budget is no longer held, so the reward cannot be paid.');
        }

        $newPaid = bcadd((string) $budget->paid_amount, $amount, 4);
        if (bccomp($newPaid, (string) $budget->funded_amount, 4) > 0) {
            throw new InvalidArgumentException('The campaign budget has no room left for this reward.');
        }

        $this->wallets->payCampaignRewardFromHold($agent, $participation->id, $amount, $label);

        $budget->paid_amount = $newPaid;
        $budget->save();
    }

    /** Completed campaign: release leftover rounding to revenue. */
    public function settle(Campaign $campaign): void
    {
        if (! self::enabled()) {
            return;
        }

        DB::transaction(function () use ($campaign) {
            $budget = CampaignBudget::query()->where('campaign_id', $campaign->id)->lockForUpdate()->first();
            if (! $budget || $budget->status !== CampaignBudget::STATUS_HELD) {
                return;
            }

            $leftover = $budget->remaining();
            $this->wallets->releaseCampaignBudget($campaign->id, $leftover);

            $budget->released_amount = bcadd((string) $budget->released_amount, $leftover, 4);
            $budget->status = CampaignBudget::STATUS_SETTLED;
            $budget->save();

            $this->audit('campaign.budget_settled', $campaign, ['released' => $leftover, 'paid' => (string) $budget->paid_amount]);
        });
    }

    private function audit(string $action, Campaign $campaign, array $values): void
    {
        try {
            app(AuditLogService::class)->log(auth()->id(), $action, $campaign, null, $values);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * What the creator paid for the units never completed: the order line total pro rata,
     * rounded down to the cent. locked_creator_price is a package price on legacy fixed
     * packages, so it must not be multiplied by units.
     */
    public function refundableAmount(Campaign $campaign): string
    {
        $quantity = max(0, (int) $campaign->quantity);
        $remainingUnits = max(0, $quantity - (int) $campaign->completed_count);
        if ($quantity === 0 || $remainingUnits === 0) {
            return '0.00';
        }

        return bcdiv(bcmul($campaign->totalCost(), (string) $remainingUnits, 4), (string) $quantity, 2);
    }

    public function inFlightCount(Campaign $campaign): int
    {
        return CampaignParticipation::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', self::IN_FLIGHT_STATUSES)
            ->count();
    }

    /** Campaign end states that refund the creator for units never delivered. Suspended keeps its hold. */
    public const REFUNDING_STATUSES = [Campaign::STATUS_CANCELLED, Campaign::STATUS_REJECTED, Campaign::STATUS_COMPLETED];

    /** Cancelled campaign: see refundUndelivered(). Kept for the expiry sweep. */
    public function refundIfCancelled(Campaign $campaign): ?string
    {
        return $campaign->status === Campaign::STATUS_CANCELLED ? $this->refundUndelivered($campaign) : null;
    }

    /**
     * One locked path for every end state that refunds (Cancelled, Rejected, admin Completed).
     * Once nothing is in flight, the creator gets the price of every unit never delivered;
     * the held budget is released at the same time. Campaigns with no held budget (unfunded or
     * legacy) are refunded from the platform wallet with a linked entry. CMP-REFUND-{id} is
     * unique, so a campaign is refunded at most once. The creator-spend referral commission on
     * the order is reversed for the refunded share.
     *
     * @return string|null refunded total, or null when not (yet) refundable
     */
    public function refundUndelivered(Campaign $campaign): ?string
    {
        if (! in_array($campaign->status, self::REFUNDING_STATUSES, true)) {
            return null;
        }

        $result = DB::transaction(function () use ($campaign) {
            $campaign = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if (! in_array($campaign->status, self::REFUNDING_STATUSES, true)) {
                return null;
            }
            $budget = self::enabled()
                ? CampaignBudget::query()->where('campaign_id', $campaign->id)->lockForUpdate()->first()
                : null;
            if ($budget && $budget->status !== CampaignBudget::STATUS_HELD) {
                return null;
            }
            if ($this->inFlightCount($campaign) > 0) {
                return null;
            }
            if (Transaction::query()->where('reference', 'CMP-REFUND-'.$campaign->id)->exists()) {
                return null;
            }

            $total = $this->refundableAmount($campaign);
            $held = $budget ? $budget->remaining() : '0';
            $refunding = bccomp($total, '0', 2) > 0;

            if ($refunding) {
                $creatorWallet = Wallet::query()->where('user_id', $campaign->creator_id)->first();
                if (! $creatorWallet) {
                    return null;
                }
                $this->wallets->refundCampaign($creatorWallet, $campaign->id, $total, $held);
            } elseif ($budget) {
                $this->wallets->releaseCampaignBudget($campaign->id, $held);
            } else {
                return null;
            }

            if ($budget) {
                $budget->refunded_amount = $total;
                $budget->released_amount = bcadd((string) $budget->released_amount, $refunding ? '0' : $held, 4);
                $budget->status = $refunding ? CampaignBudget::STATUS_REFUNDED : CampaignBudget::STATUS_SETTLED;
                $budget->save();
            }

            $this->audit($refunding ? 'campaign.refunded' : 'campaign.budget_settled', $campaign, [
                'status' => $campaign->status, 'refunded' => $total, 'unlocked' => $held, 'funded' => $budget !== null,
            ]);

            return ['total' => $total, 'refunded' => $refunding, 'order_id' => $campaign->order_id];
        });

        if ($result === null) {
            return null;
        }

        if ($result['refunded'] && $result['order_id']) {
            $order = Order::query()->find($result['order_id']);
            if ($order) {
                try {
                    app(ReferralCommissionService::class)->reverseForSource($order, 'Campaign #'.$campaign->id.' refunded for undelivered units', $result['total']);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return $result['refunded'] ? $result['total'] : '0.00';
    }

    /**
     * Legacy active campaigns without a hold: what the backfill would hold.
     *
     * @return list<array{campaign_id: int, title: string, units: int, amount: string}>
     */
    public function backfillCandidates(): array
    {
        $rows = [];

        Campaign::query()
            ->whereIn('status', Campaign::WORKABLE_STATUSES)
            ->whereDoesntHave('budget')
            ->whereColumn('completed_count', '<', 'quantity')
            ->where('locked_agent_reward', '>', 0)
            ->orderBy('id')
            ->each(function (Campaign $campaign) use (&$rows) {
                $units = max(0, (int) $campaign->quantity - (int) $campaign->completed_count);
                $rows[] = [
                    'campaign_id' => (int) $campaign->id,
                    'title' => (string) $campaign->title,
                    'units' => $units,
                    'amount' => $this->fundedAmountFor($campaign, $units),
                ];
            });

        return $rows;
    }

    /** Budget for the remaining units of a legacy campaign; paid_amount starts at zero. */
    public function backfill(Campaign $campaign, int $units): CampaignBudget
    {
        $amount = $this->fundedAmountFor($campaign, $units);

        return DB::transaction(function () use ($campaign, $amount) {
            $this->wallets->holdCampaignBudget($campaign->id, $amount, $campaign->order_id);

            if (self::isUnfunded($campaign)) {
                $meta = $campaign->meta;
                unset($meta['funding'], $meta['funding_shortfall']);
                $campaign->forceFill(['meta' => $meta])->save();
            }

            return CampaignBudget::query()->create([
                'campaign_id' => $campaign->id,
                'funded_amount' => $amount,
                'paid_amount' => 0,
                'refunded_amount' => 0,
                'released_amount' => 0,
                'status' => CampaignBudget::STATUS_HELD,
            ]);
        });
    }
}
