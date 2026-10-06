<?php

namespace App\Services\Referrals;

use App\Enums\TransactionType;
use App\Models\ReferralCommission;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
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

        $base = number_format((float) $baseAmount, 2, '.', '');
        $amount = bcdiv(bcmul($base, $rate, 6), '100', 2);

        if (bccomp($amount, '0.01', 2) < 0) {
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
     * Reversal policy for a refunded, cancelled or revoked source. Pending rows are
     * cancelled; credited rows get an offsetting ledger entry. No caller yet.
     */
    public function reverseForSource(Model $source, string $reason): int
    {
        $ids = ReferralCommission::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereIn('status', [ReferralCommission::STATUS_PENDING, ReferralCommission::STATUS_CREDITED])
            ->pluck('id');

        $reversed = 0;

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $reason, &$reversed) {
                $commission = ReferralCommission::query()->whereKey($id)->lockForUpdate()->first();

                if (! $commission || $commission->status === ReferralCommission::STATUS_REVERSED) {
                    return;
                }

                $reversalId = null;

                if ($commission->status === ReferralCommission::STATUS_CREDITED && $commission->transaction_id) {
                    $original = Transaction::query()->find($commission->transaction_id);
                    if ($original) {
                        $reversalId = $this->wallets->reverseReferralCommission($original, $reason)->id;
                    }
                }

                $commission->forceFill([
                    'status' => ReferralCommission::STATUS_REVERSED,
                    'reversal_transaction_id' => $reversalId,
                    'reversed_at' => now(),
                ])->save();

                $reversed++;
            });
        }

        return $reversed;
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

                $transaction = $this->wallets->creditReward(
                    $referrer,
                    (float) $commission->amount,
                    $label,
                    TransactionType::ReferralCommission->value,
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
