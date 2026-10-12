<?php

namespace App\Services\Referrals;

use App\Enums\TransactionType;
use App\Models\ReferralCommission;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferralCommissionService
{
    public function __construct(
        private WalletService $wallets,
    ) {}

    /**
     * Record (and credit when possible) the referrer's commission for one qualifying
     * event. Each source can only ever produce one commission.
     */
    public function record(User $referred, string $kind, Model $source, string|float $baseAmount): ?ReferralCommission
    {
        $settings = SystemSetting::referralSettings();

        if (! $settings['enabled'] || ! $referred->referred_by_id) {
            return null;
        }

        $referrer = User::query()->find($referred->referred_by_id);

        if (! $referrer || $referrer->is_suspended || $referrer->anonymized_at !== null || ! $referrer->isAgent()) {
            return null;
        }

        $rate = number_format($kind === ReferralCommission::KIND_AGENT_EARNING
            ? $settings['agent_percent']
            : $settings['creator_percent'], 2, '.', '');

        if (bccomp($rate, '0', 2) <= 0) {
            return null;
        }

        $base = Money::round((string) $baseAmount, 4);
        $amount = bcdiv(bcmul($base, $rate, 8), '100', 4);

        if (bccomp($amount, '0.0001', 4) < 0) {
            return null;
        }

        $sourceType = $source->getMorphClass();
        $sourceId = (int) $source->getKey();

        if ($this->existsForSource($kind, $sourceType, $sourceId)) {
            return null;
        }

        try {
            $commission = ReferralCommission::query()->create([
                'referrer_id' => $referrer->id,
                'referred_user_id' => $referred->id,
                'kind' => $kind,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'base_amount' => $base,
                'rate' => $rate,
                'amount' => $amount,
                'status' => ReferralCommission::STATUS_PENDING,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $this->creditPending($commission->id);

        return $commission->fresh();
    }

    /**
     * Credit every pending commission for this referrer. Safe to call repeatedly or
     * concurrently: each row is locked and re-checked before it is credited.
     */
    public function releasePending(User $referrer): int
    {
        if ($referrer->anonymized_at !== null || ! Wallet::query()->where('user_id', $referrer->id)->exists()) {
            return 0;
        }

        $credited = 0;

        ReferralCommission::query()
            ->where('referrer_id', $referrer->id)
            ->where('status', ReferralCommission::STATUS_PENDING)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$credited) {
                if ($this->creditPending($id)) {
                    $credited++;
                }
            });

        return $credited;
    }

    /**
     * Reverse the commissions earned on a refunded, cancelled or revoked source, once each.
     * Pending rows are cancelled. Credited rows are clawed back through the wallet service
     * (reference REF-REV-{id}); what the referrer cannot cover is owed and taken from later earnings.
     * With $refundedAmount, only the refunded share of the source's base amount is reversed.
     */
    public function reverseForSource(Model $source, string $reason, ?string $refundedAmount = null): int
    {
        $ids = ReferralCommission::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereIn('status', [ReferralCommission::STATUS_PENDING, ReferralCommission::STATUS_CREDITED])
            ->pluck('id');

        $reversed = 0;

        foreach ($ids as $id) {
            $pendingCancelled = DB::transaction(function () use ($id) {
                $commission = ReferralCommission::query()->whereKey($id)->lockForUpdate()->first();
                if (! $commission || $commission->status !== ReferralCommission::STATUS_PENDING) {
                    return false;
                }
                $commission->forceFill([
                    'status' => ReferralCommission::STATUS_REVERSED,
                    'reversed_amount' => 0,
                    'reversed_at' => now(),
                ])->save();

                return true;
            });
            if ($pendingCancelled) {
                $reversed++;

                continue;
            }

            $commission = ReferralCommission::query()->find($id);
            if (! $commission || $commission->status !== ReferralCommission::STATUS_CREDITED) {
                continue;
            }

            $target = $this->reversalTarget($commission, $refundedAmount);
            $result = $this->wallets->reverseReferralCommission((int) $id, $target, $reason);
            if ($commission->fresh()?->status === ReferralCommission::STATUS_REVERSED) {
                $reversed++;
                if (bccomp($result['owed'], '0', 4) > 0) {
                    Log::channel('financial')->info('Referral commission reversal partly owed', [
                        'commission_id' => $id, 'taken' => $result['taken'], 'owed' => $result['owed'],
                    ]);
                }
            }
        }

        return $reversed;
    }

    private function reversalTarget(ReferralCommission $commission, ?string $refundedAmount): string
    {
        $amount = Money::round((string) $commission->amount, 4);
        $base = Money::round((string) $commission->base_amount, 4);
        if ($refundedAmount === null || bccomp($base, '0', 4) <= 0) {
            return $amount;
        }

        $share = bcdiv(bcmul($amount, Money::round($refundedAmount, 4), 8), $base, 4);

        return bccomp($share, $amount, 4) > 0 ? $amount : $share;
    }

    private function creditPending(int $commissionId): bool
    {
        try {
            return DB::transaction(function () use ($commissionId) {
                $commission = ReferralCommission::query()->whereKey($commissionId)->lockForUpdate()->first();

                if (! $commission || $commission->status !== ReferralCommission::STATUS_PENDING) {
                    return false;
                }

                $referrer = User::query()->find($commission->referrer_id);

                if (! $referrer || ! Wallet::query()->where('user_id', $referrer->id)->exists()) {
                    return false;
                }

                $referredName = User::query()->whereKey($commission->referred_user_id)->value('username');
                $label = $referredName
                    ? 'Referral commission from @'.$referredName
                    : 'Referral commission';

                $transaction = $this->wallets->creditFundedReward(
                    $referrer,
                    (string) $commission->amount,
                    $label,
                    TransactionType::ReferralCommission,
                    TransactionType::ReferralCommissionExpense,
                    'REF-COM-'.$commission->id,
                );

                $commission->forceFill([
                    'status' => ReferralCommission::STATUS_CREDITED,
                    'transaction_id' => $transaction->id,
                    'credited_at' => now(),
                ])->save();

                return true;
            });
        } catch (\Throwable $e) {
            Log::channel('financial')->error('Referral commission credit failed', [
                'commission_id' => $commissionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function existsForSource(string $kind, string $sourceType, int $sourceId): bool
    {
        return ReferralCommission::query()
            ->where('kind', $kind)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }
}
