<?php

namespace App\Modules\Wallet\Services;

use App\Enums\TransactionType;
use App\Enums\WalletHoldReason;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletType;
use App\Events\WalletFunded;
use App\Events\WalletWithdrawalCompleted;
use App\Events\WithdrawalApproved;
use App\Events\WithdrawalPayoutFailed;
use App\Events\WithdrawalRejected;
use App\Models\Order;
use App\Models\PaymentTimelineEvent;
use App\Models\ReferralCommission;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFunding;
use App\Models\WalletHold;
use App\Models\Withdrawal;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Wallet\Payments\Data\PayoutResult;
use App\Support\Money\Currency;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WalletService
{
    /** Ledger precision for balances, holds and entries (sub-cent rewards). */
    public const SCALE = 4;

    /** When withdrawal fees started being split into income, recovered and cost entries. */
    public const FEE_SPLIT_STARTED_SETTING = 'withdrawal_fee_split_started_at';

    private function amt(string|int|float|null $value): string
    {
        return Money::round($value ?? 0, self::SCALE);
    }

    private function currency(): string
    {
        return Currency::current()->code;
    }

    public function creditFromFunding(
        WalletFunding $funding,
        ?int $approvedBy = null,
        ?string $approvedIp = null,
        ?string $approvedDevice = null,
        ?string $approvedReason = null,
    ): Transaction {
        return DB::transaction(function () use ($funding, $approvedBy, $approvedIp, $approvedDevice, $approvedReason) {
            $funding = WalletFunding::where('id', $funding->id)->lockForUpdate()->firstOrFail();

            $owner = User::query()->find($funding->user_id);
            if ($owner?->hasRole('agent')) {
                throw new InvalidArgumentException('Agents cannot receive deposit credits. Earnings are credited after task verification.');
            }

            if ($funding->status === 'approved' || $funding->internal_status === 'completed') {
                return $this->findFundingTransaction($funding)
                    ?? throw new InvalidArgumentException('Approved funding has no ledger entry.');
            }

            $pendingStatuses = ['pending', 'processing'];
            $pendingInternal = ['pending', 'processing', null];
            $isPending = in_array($funding->status, $pendingStatuses, true)
                || in_array($funding->internal_status, $pendingInternal, true);

            if (! $isPending || in_array($funding->status, ['rejected', 'reversed'], true)) {
                throw new InvalidArgumentException('Funding is not pending.');
            }

            $wallet = Wallet::where('id', $funding->wallet_id)->lockForUpdate()->firstOrFail();

            $wallet->balance = bcadd((string) $wallet->balance, $this->amt($funding->amount), self::SCALE);
            $wallet->save();

            $transaction = $this->createLedgerEntry($wallet, [
                'user_id' => $funding->user_id,
                'wallet_funding_id' => $funding->id,
                'type' => TransactionType::Funding->value,
                'label' => 'Deposit ('.$funding->method.')',
                'description' => 'Wallet funding approved',
                'amount' => $funding->amount,
                'currency' => $funding->currency,
                'status' => 'completed',
            ]);

            $funding->update(array_filter([
                'status' => 'approved',
                'internal_status' => 'completed',
                'provider_status' => $funding->provider_status ?? 'SUCCESS',
                'approved_by' => $approvedBy,
                'approved_at' => $approvedBy ? now() : now(),
                'approved_ip' => $approvedIp,
                'approved_device' => $approvedDevice,
                'approved_reason' => $approvedReason,
            ], fn ($v) => $v !== null));

            PaymentTimelineEvent::record($funding, 'wallet_credited', 'Wallet credited');

            DB::afterCommit(function () use ($funding, $transaction) {
                WalletFunded::dispatch(
                    (int) $funding->user_id,
                    (int) $transaction->id,
                    (float) $funding->amount,
                    (string) $funding->currency
                );
            });

            return $transaction;
        });
    }

    public function debitForPlatformPurchase(Wallet $wallet, Order $order, string|int|float $amount): Transaction
    {
        return DB::transaction(function () use ($wallet, $order, $amount) {
            $amountStr = $this->amt($amount);

            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $wallet->availableBalance(), $amountStr, self::SCALE) < 0) {
                throw new InvalidArgumentException('Insufficient wallet balance.');
            }

            $wallet->balance = bcsub((string) $wallet->balance, $amountStr, self::SCALE);
            $wallet->save();

            $buyerTxn = $this->createLedgerEntry($wallet, [
                'user_id' => $wallet->user_id,
                'order_id' => $order->id,
                'type' => TransactionType::Purchase->value,
                'label' => 'Platform purchase',
                'description' => 'Paid for order '.$order->reference,
                'amount' => bcmul($amountStr, '-1', self::SCALE),
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            $platformWallet->balance = bcadd((string) $platformWallet->balance, $amountStr, self::SCALE);
            $platformWallet->save();

            $this->createLedgerEntry($platformWallet, [
                'user_id' => $platformWallet->user_id,
                'order_id' => $order->id,
                'type' => TransactionType::Purchase->value,
                'label' => 'Platform product sale',
                'description' => 'Revenue from order '.$order->reference,
                'amount' => $amountStr,
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            return $buyerTxn;
        });
    }

    /**
     * Credit platform revenue for a gateway-paid catalog order (no user wallet required).
     */
    public function creditPlatformFromGatewaySale(Order $order, string|int|float $amount): Transaction
    {
        return DB::transaction(function () use ($order, $amount) {
            $amountStr = $this->amt($amount);

            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            $platformWallet->balance = bcadd((string) $platformWallet->balance, $amountStr, self::SCALE);
            $platformWallet->save();

            return $this->createLedgerEntry($platformWallet, [
                'user_id' => $platformWallet->user_id,
                'order_id' => $order->id,
                'type' => TransactionType::Purchase->value,
                'label' => 'Platform product sale (gateway)',
                'description' => 'Gateway revenue from order '.$order->reference,
                'amount' => $amountStr,
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);
        });
    }

    public function lockForWithdrawal(Withdrawal $withdrawal): Transaction
    {
        return DB::transaction(function () use ($withdrawal) {
            $wallet = Wallet::where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();
            $amountStr = $this->amt($withdrawal->amount);

            if (bccomp((string) $wallet->availableBalance(), $amountStr, self::SCALE) < 0) {
                throw new InvalidArgumentException('Insufficient balance for withdrawal.');
            }

            $wallet->locked_balance = bcadd((string) $wallet->locked_balance, $amountStr, self::SCALE);
            $wallet->save();

            $this->createHold($wallet, WalletHoldReason::Withdrawal, $withdrawal->id, $amountStr);

            $txn = $this->createLedgerEntry($wallet, [
                'user_id' => $withdrawal->user_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => TransactionType::WithdrawalHold->value,
                'label' => 'Withdrawal hold',
                'description' => 'Funds held for withdrawal '.$withdrawal->reference,
                'amount' => bcmul($amountStr, '-1', self::SCALE),
                'currency' => $this->currency(),
                'status' => 'pending',
            ]);

            PaymentTimelineEvent::record($withdrawal, 'created', 'Created');

            return $txn;
        });
    }

    /**
     * Mark approved; the payout service then sends it to a gateway (or completes it for manual payouts).
     */
    public function markWithdrawalApproved(Withdrawal $withdrawal, int $approvedBy, ?string $approvedIp = null, ?string $approvalNote = null): void
    {
        DB::transaction(function () use ($withdrawal, $approvedBy, $approvedIp, $approvalNote) {
            $withdrawal = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! in_array($withdrawal->status, ['pending'], true)
                && $withdrawal->internal_status !== 'pending_review') {
                throw new InvalidArgumentException('Withdrawal is not pending review.');
            }

            if (in_array($withdrawal->internal_status, ['approved', 'processing', 'completed'], true)
                || $withdrawal->status === 'completed') {
                throw new InvalidArgumentException('Withdrawal already approved or completed.');
            }

            $withdrawal->update([
                'status' => 'approved',
                'internal_status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'approved_ip' => $approvedIp,
                'approval_note' => $approvalNote,
            ]);

            PaymentTimelineEvent::record($withdrawal, 'approved', 'Approved by admin');

            DB::afterCommit(function () use ($withdrawal) {
                WithdrawalApproved::dispatch(
                    (int) $withdrawal->id,
                    (int) $withdrawal->user_id,
                    (float) $withdrawal->amount,
                    (string) ($withdrawal->currency ?: $this->currency())
                );
            });
        });
    }

    public function markWithdrawalProcessing(Withdrawal $withdrawal, ?string $providerStatus = null): void
    {
        DB::transaction(function () use ($withdrawal, $providerStatus) {
            $withdrawal = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();
            $withdrawal->update([
                'status' => 'processing',
                'internal_status' => 'processing',
                'provider_status' => $providerStatus ?? $withdrawal->provider_status,
            ]);
            PaymentTimelineEvent::record($withdrawal, 'sent_to_provider', 'Sent to payment provider');
        });
    }

    /**
     * @param  array<string, mixed>  $providerMeta
     */
    public function markWithdrawalAwaitingProviderAuthorization(
        Withdrawal $withdrawal,
        ?string $providerStatus,
        array $providerMeta,
        string $provider = 'gateway',
    ): void {
        DB::transaction(function () use ($withdrawal, $providerStatus, $providerMeta, $provider) {
            $withdrawal = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();
            $withdrawal->update([
                'status' => 'processing',
                'internal_status' => Withdrawal::INTERNAL_AWAITING_PROVIDER_AUTH,
                'provider_status' => $providerStatus ?? 'PENDING_AUTHORIZATION',
                'provider' => $provider,
                'provider_meta' => $providerMeta,
            ]);
            PaymentTimelineEvent::record($withdrawal, 'awaiting_provider_auth', 'Awaiting payment provider OTP authorization', [
                'provider_status' => $providerStatus,
            ]);
        });
    }

    public function completeWithdrawalPayout(Withdrawal $withdrawal): Transaction
    {
        return DB::transaction(function () use ($withdrawal) {
            $withdrawal = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status === 'completed' || $withdrawal->internal_status === 'completed') {
                return $this->findWithdrawalTransaction($withdrawal)
                    ?? throw new InvalidArgumentException('Completed withdrawal has no ledger entry.');
            }

            $completableInternal = ['approved', 'processing', 'pending_review', Withdrawal::INTERNAL_AWAITING_PROVIDER_AUTH, Withdrawal::INTERNAL_NEEDS_ADMIN_DECISION, null];
            if (! in_array($withdrawal->internal_status, $completableInternal, true)
                && ! in_array($withdrawal->status, ['pending', 'approved', 'processing'], true)) {
                throw new InvalidArgumentException('Withdrawal cannot be completed from current status.');
            }

            $wallet = Wallet::where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();
            $amountStr = $this->amt($withdrawal->amount);

            if (bccomp((string) $wallet->locked_balance, $amountStr, self::SCALE) < 0) {
                throw new InvalidArgumentException('Insufficient locked balance for withdrawal.');
            }

            $wallet->locked_balance = bcsub((string) $wallet->locked_balance, $amountStr, self::SCALE);
            $wallet->balance = bcsub((string) $wallet->balance, $amountStr, self::SCALE);
            $wallet->save();

            $this->transitionHold(
                $wallet->id,
                WalletHoldReason::Withdrawal,
                $withdrawal->id,
                WalletHoldStatus::Consumed
            );

            $fee = $this->amt($withdrawal->fee_amount ?? 0);
            $transaction = $this->createLedgerEntry($wallet, [
                'user_id' => $withdrawal->user_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => TransactionType::Withdrawal->value,
                'label' => 'Withdrawal to payout account',
                'description' => bccomp($fee, '0', self::SCALE) > 0
                    ? 'Sent '.Money::format($withdrawal->netPayoutAmount(), 2).' after a '.Money::format($fee, 2).' withdrawal fee'
                    : null,
                'amount' => bcmul($amountStr, '-1', self::SCALE),
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            $this->postWithdrawalFeeEntries($withdrawal, $fee);

            $withdrawal->update([
                'status' => 'completed',
                'internal_status' => 'completed',
                'provider_status' => $withdrawal->provider_status ?? 'SUCCESS',
            ]);

            PaymentTimelineEvent::record($withdrawal, 'completed', 'Payout completed');

            DB::afterCommit(function () use ($withdrawal, $transaction) {
                WalletWithdrawalCompleted::dispatch(
                    (int) $withdrawal->user_id,
                    (int) $withdrawal->id,
                    (int) $transaction->id,
                    (float) $withdrawal->amount,
                    (string) ($withdrawal->currency ?: $this->currency())
                );
            });

            return $transaction;
        });
    }

    /**
     * Platform side of a completed withdrawal, in three parts:
     * - the platform fee is income (WithdrawalFee);
     * - the provider fee the agent paid is recovered (PayoutProviderFeeRecovered);
     * - the fee the provider actually charged is a cost (PayoutProviderFeeCost).
     * Recovered minus cost is the real gain or loss on the quote, including any absorbed re-quote
     * difference. Runs inside the completion transaction.
     */
    private function postWithdrawalFeeEntries(Withdrawal $withdrawal, string $fee): void
    {
        $platformFee = $this->amt($withdrawal->platform_fee ?? 0);
        $providerFee = $this->amt($withdrawal->provider_fee ?? 0);
        if (bccomp(bcadd($platformFee, $providerFee, self::SCALE), '0', self::SCALE) <= 0 && bccomp($fee, '0', self::SCALE) > 0) {
            // Withdrawals created before the fee breakdown existed: the whole fee was the platform's.
            $platformFee = $fee;
        }
        $cost = $this->providerFeeCharged($withdrawal, $providerFee);

        $entries = [
            [TransactionType::WithdrawalFee, $platformFee, 'Withdrawal fee', 'Platform fee on withdrawal '.$withdrawal->reference],
            [TransactionType::PayoutProviderFeeRecovered, $providerFee, 'Provider fee recovered', 'Provider fee paid by the agent on withdrawal '.$withdrawal->reference],
            [TransactionType::PayoutProviderFeeCost, bcmul($cost, '-1', self::SCALE), 'Provider fee paid', 'Fee charged by the payout provider for withdrawal '.$withdrawal->reference],
        ];
        $entries = array_filter($entries, fn ($e) => bccomp($e[1], '0', self::SCALE) !== 0);
        if ($entries === []) {
            return;
        }

        if (SystemSetting::get(self::FEE_SPLIT_STARTED_SETTING) === null) {
            SystemSetting::set(self::FEE_SPLIT_STARTED_SETTING, now()->startOfSecond()->toIso8601String());
        }

        $platform = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
        foreach ($entries as [$type, $amount, $label, $description]) {
            $platform->balance = bcadd((string) $platform->balance, $amount, self::SCALE);
            $this->createLedgerEntry($platform, [
                'user_id' => $platform->user_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => $type->value,
                'label' => $label,
                'description' => $description,
                'amount' => $amount,
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);
        }
        $platform->save();
    }

    /** What the provider charged: the fee reported on the successful attempt, else the quoted fee plus any absorbed difference. */
    private function providerFeeCharged(Withdrawal $withdrawal, string $providerFee): string
    {
        $reported = WithdrawalPayoutAttempt::query()
            ->where('withdrawal_id', $withdrawal->id)
            ->where('status', PayoutResult::SUCCEEDED)
            ->whereNotNull('provider_fee_charged')
            ->orderByDesc('id')
            ->value('provider_fee_charged');
        if ($reported !== null) {
            return $this->amt($reported);
        }

        return bcadd($providerFee, $this->amt($withdrawal->absorbed_fee ?? 0), self::SCALE);
    }

    /**
     * Separate bounce fee after the full refund of a payout returned for incorrect account details.
     * The caller has already checked every condition; this only posts the entries.
     */
    public function chargeBounceFee(Withdrawal $withdrawal, string $amount, string $failureCode): ?Transaction
    {
        return DB::transaction(function () use ($withdrawal, $amount, $failureCode) {
            $withdrawal = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();
            $amount = $this->amt($amount);
            if (bccomp($amount, '0', self::SCALE) <= 0 || bccomp((string) $withdrawal->bounce_fee_amount, '0', self::SCALE) > 0) {
                return null;
            }

            $wallet = Wallet::where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();
            $wallet->balance = bcsub((string) $wallet->balance, $amount, self::SCALE);
            $wallet->save();

            $txn = $this->createLedgerEntry($wallet, [
                'user_id' => $withdrawal->user_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => TransactionType::BounceFee->value,
                'label' => 'Bounce fee: payout returned for incorrect account details',
                'amount' => bcmul($amount, '-1', self::SCALE),
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            $platform = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            $platform->balance = bcadd((string) $platform->balance, $amount, self::SCALE);
            $platform->save();
            $this->createLedgerEntry($platform, [
                'user_id' => $platform->user_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => TransactionType::BounceFee->value,
                'label' => 'Bounce fee',
                'description' => 'Bounce fee on withdrawal '.$withdrawal->reference,
                'amount' => $amount,
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            $withdrawal->update(['bounce_fee_amount' => $amount, 'bounce_failure_code' => $failureCode]);
            PaymentTimelineEvent::record($withdrawal, 'bounce_fee', 'Bounce fee charged', ['amount' => $amount, 'code' => $failureCode]);

            return $txn;
        });
    }

    public function waiveBounceFee(Withdrawal $withdrawal, int $adminId): ?Transaction
    {
        return DB::transaction(function () use ($withdrawal, $adminId) {
            $withdrawal = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();
            $amount = $this->amt($withdrawal->bounce_fee_amount ?? 0);
            if ($withdrawal->bounce_waived_at !== null || bccomp($amount, '0', self::SCALE) <= 0) {
                return null;
            }

            $wallet = Wallet::where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();
            $wallet->balance = bcadd((string) $wallet->balance, $amount, self::SCALE);
            $wallet->save();

            $txn = $this->createLedgerEntry($wallet, [
                'user_id' => $withdrawal->user_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => TransactionType::BounceFeeWaiver->value,
                'label' => 'Bounce fee waived',
                'amount' => $amount,
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            $platform = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            $platform->balance = bcsub((string) $platform->balance, $amount, self::SCALE);
            $platform->save();
            $this->createLedgerEntry($platform, [
                'user_id' => $platform->user_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => TransactionType::BounceFeeWaiver->value,
                'label' => 'Bounce fee waived',
                'description' => 'Waived bounce fee on withdrawal '.$withdrawal->reference,
                'amount' => bcmul($amount, '-1', self::SCALE),
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            $withdrawal->update(['bounce_waived_at' => now(), 'bounce_waived_by' => $adminId]);
            PaymentTimelineEvent::record($withdrawal, 'bounce_fee_waived', 'Bounce fee waived', ['admin_id' => $adminId]);

            return $txn;
        });
    }

    /**
     * Admin approve when no payout gateway is in use (manual transfer): approve + complete in one step.
     */
    public function debitForWithdrawal(Withdrawal $withdrawal, ?int $approvedBy = null): Transaction
    {
        return DB::transaction(function () use ($withdrawal, $approvedBy) {
            $withdrawal = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status === 'completed' || $withdrawal->internal_status === 'completed') {
                return $this->findWithdrawalTransaction($withdrawal)
                    ?? throw new InvalidArgumentException('Completed withdrawal has no ledger entry.');
            }

            if (! in_array($withdrawal->status, ['pending'], true)
                && ! in_array($withdrawal->internal_status, ['pending_review', null], true)) {
                throw new InvalidArgumentException('Withdrawal is not pending.');
            }

            if ($approvedBy) {
                $withdrawal->update([
                    'status' => 'approved',
                    'internal_status' => 'approved',
                    'approved_by' => $approvedBy,
                    'approved_at' => now(),
                ]);
                PaymentTimelineEvent::record($withdrawal, 'approved', 'Approved by admin');
            }

            return $this->completeWithdrawalPayout($withdrawal->fresh());
        });
    }

    public function failWithdrawalPayout(Withdrawal $withdrawal, ?string $adminNotes = null): void
    {
        DB::transaction(function () use ($withdrawal, $adminNotes) {
            $withdrawal = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();

            // Never unlock or mutate a completed payout (late FAILED/REVERSED webhooks).
            if ($withdrawal->status === 'completed' || $withdrawal->internal_status === 'completed') {
                return;
            }

            if (in_array($withdrawal->status, ['rejected', 'failed'], true)
                || $withdrawal->internal_status === 'failed') {
                return;
            }

            $wallet = Wallet::where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();
            $amountStr = $this->amt($withdrawal->amount);

            if (bccomp((string) $wallet->locked_balance, $amountStr, self::SCALE) < 0) {
                throw new InvalidArgumentException('Insufficient locked balance to unlock.');
            }

            $wallet->locked_balance = bcsub((string) $wallet->locked_balance, $amountStr, self::SCALE);
            $wallet->save();

            $this->transitionHold(
                $wallet->id,
                WalletHoldReason::Withdrawal,
                $withdrawal->id,
                WalletHoldStatus::Released
            );

            $this->createLedgerEntry($wallet, [
                'user_id' => $withdrawal->user_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => TransactionType::WithdrawalUnlock->value,
                'label' => 'Withdrawal failed, funds returned',
                'amount' => $amountStr,
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            $withdrawal->update([
                'status' => 'failed',
                'internal_status' => 'failed',
                'provider_status' => $withdrawal->provider_status ?? 'FAILED',
                'admin_notes' => $adminNotes ?? $withdrawal->admin_notes,
            ]);

            PaymentTimelineEvent::record($withdrawal, 'failed', 'Payout failed, funds unlocked');

            $outcome = match (strtoupper((string) ($withdrawal->provider_status ?? ''))) {
                'EXPIRED' => 'expired',
                'REVERSED' => 'reversed',
                default => 'failed',
            };

            DB::afterCommit(function () use ($withdrawal, $outcome) {
                WithdrawalPayoutFailed::dispatch(
                    (int) $withdrawal->id,
                    (int) $withdrawal->user_id,
                    (float) $withdrawal->amount,
                    $outcome,
                    (string) ($withdrawal->currency ?: $this->currency())
                );
            });
        });
    }

    public function unlockRejectedWithdrawal(Withdrawal $withdrawal, ?string $adminNotes = null): void
    {
        DB::transaction(function () use ($withdrawal, $adminNotes) {
            $withdrawal = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status === 'rejected' || $withdrawal->internal_status === 'failed') {
                if ($withdrawal->status === 'rejected') {
                    return;
                }
            }

            $heldStates = [
                Withdrawal::INTERNAL_AWAITING_PROVIDER_AUTH,
                Withdrawal::INTERNAL_AWAITING_AGENT_CONFIRMATION,
                Withdrawal::INTERNAL_NEEDS_ADMIN_DECISION,
            ];

            if (! in_array($withdrawal->status, ['pending', 'approved'], true)
                && ! in_array($withdrawal->internal_status, ['pending_review', 'approved', null], true)
                && ! in_array($withdrawal->internal_status, $heldStates, true)) {
                throw new InvalidArgumentException('Withdrawal cannot be rejected from current status.');
            }

            if (in_array($withdrawal->status, ['processing'], true)
                && ! in_array($withdrawal->internal_status, $heldStates, true)) {
                throw new InvalidArgumentException('Cannot reject a withdrawal that is already processing.');
            }

            $wallet = Wallet::where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();
            $amountStr = $this->amt($withdrawal->amount);

            if (bccomp((string) $wallet->locked_balance, $amountStr, self::SCALE) < 0) {
                throw new InvalidArgumentException('Insufficient locked balance to unlock.');
            }

            $wallet->locked_balance = bcsub((string) $wallet->locked_balance, $amountStr, self::SCALE);
            $wallet->save();

            $this->transitionHold(
                $wallet->id,
                WalletHoldReason::Withdrawal,
                $withdrawal->id,
                WalletHoldStatus::Released
            );

            $this->createLedgerEntry($wallet, [
                'user_id' => $withdrawal->user_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => TransactionType::WithdrawalUnlock->value,
                'label' => 'Withdrawal rejected, funds returned',
                'amount' => $amountStr,
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            $withdrawal->update([
                'status' => 'rejected',
                'internal_status' => 'failed',
                'admin_notes' => $adminNotes,
            ]);

            PaymentTimelineEvent::record($withdrawal, 'rejected', 'Rejected, funds returned');

            $reason = filled($adminNotes) ? trim((string) $adminNotes) : null;

            DB::afterCommit(function () use ($withdrawal, $reason) {
                WithdrawalRejected::dispatch(
                    (int) $withdrawal->id,
                    (int) $withdrawal->user_id,
                    (float) $withdrawal->amount,
                    (string) ($withdrawal->currency ?: $this->currency()),
                    $reason,
                );
            });
        });
    }

    public function adminAdjust(Wallet $wallet, string|int|float $amount, string $reason, int $adminId): Transaction
    {
        return DB::transaction(function () use ($wallet, $amount, $reason, $adminId) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $newBalance = bcadd((string) $wallet->balance, $this->amt($amount), self::SCALE);
            if (bccomp($newBalance, '0', self::SCALE) < 0) {
                throw new InvalidArgumentException('Adjustment would result in negative balance.');
            }

            if (bccomp($newBalance, (string) $wallet->locked_balance, self::SCALE) < 0) {
                throw new InvalidArgumentException('Adjustment would make balance lower than locked funds.');
            }

            $wallet->balance = $newBalance;
            $wallet->save();

            return $this->createLedgerEntry($wallet, [
                'user_id' => $wallet->user_id,
                'type' => TransactionType::AdminAdjustment->value,
                'label' => 'Admin adjustment',
                'description' => $reason.' (admin #'.$adminId.')',
                'amount' => $this->amt($amount),
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);
        });
    }

    /**
     * Credit a reward that is not paid from a campaign hold (referral commissions, rewards on
     * unfunded campaigns). The platform wallet is debited with a linked expense entry in the same
     * transaction, so every credit has a funding source. The pair uses {reference} for the user
     * and {reference}-P for the platform; both are unique, so a retry cannot pay twice.
     *
     * When the platform wallet cannot cover the expense the reward is still paid (the work was
     * done); the platform balance may go below zero only through these expense entries, and
     * admins are alerted.
     */
    public function creditFundedReward(
        User $user,
        string $amount,
        string $label,
        TransactionType $userType,
        TransactionType $expenseType,
        string $reference,
    ): Transaction {
        $amountStr = $this->amt($amount);
        if (bccomp($amountStr, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException('Reward amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amountStr, $label, $userType, $expenseType, $reference) {
            $existing = Transaction::query()->where('reference', $reference)->first();
            if ($existing) {
                return $existing;
            }

            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            $userWallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $userWallet) {
                throw new InvalidArgumentException('User has no wallet to credit.');
            }
            if ($userWallet->id === $platformWallet->id) {
                throw new InvalidArgumentException('The platform wallet cannot receive a reward.');
            }

            $short = bccomp((string) $platformWallet->availableBalance(), $amountStr, self::SCALE) < 0;

            $platformWallet->balance = bcsub((string) $platformWallet->balance, $amountStr, self::SCALE);
            $platformWallet->save();
            $this->createLedgerEntry($platformWallet, [
                'reference' => $reference.'-P',
                'user_id' => $platformWallet->user_id,
                'type' => $expenseType->value,
                'label' => $expenseType->label(),
                'description' => $label.' (to user #'.$user->id.')',
                'amount' => bcmul($amountStr, '-1', self::SCALE),
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            $userWallet->balance = bcadd((string) $userWallet->balance, $amountStr, self::SCALE);
            $userWallet->save();
            $credit = $this->createLedgerEntry($userWallet, [
                'reference' => $reference,
                'user_id' => $user->id,
                'type' => $userType->value,
                'label' => $label,
                'description' => $label,
                'amount' => $amountStr,
                'currency' => $userWallet->currency ?: $this->currency(),
                'status' => 'completed',
            ]);
            $this->recoverOwedCommissions($platformWallet, $userWallet);

            if ($short) {
                $balance = (string) $platformWallet->balance;
                DB::afterCommit(fn () => $this->alertPlatformShortfall($reference, $amountStr, $balance));
            }

            return $credit;
        });
    }

    private function alertPlatformShortfall(string $reference, string $amount, string $balance): void
    {
        \Illuminate\Support\Facades\Log::channel('financial')->warning('Funded reward paid while the platform wallet was short', [
            'reference' => $reference, 'amount' => $amount, 'platform_balance' => $balance,
        ]);

        try {
            app(\App\Services\Notifications\NotificationDispatcher::class)->notifyAdmins(new \App\Services\Notifications\NotificationMessage(
                type: 'platform.wallet_short',
                title: 'Platform wallet is short',
                body: 'A reward of '.money($amount, 2, 4).' ('.$reference.') was paid while the platform wallet could not cover it. The platform balance is now '.money($balance, 2, 4).'. Top up the platform wallet.',
                actionUrl: null,
                meta: ['reference' => $reference, 'amount' => $amount, 'balance' => $balance],
                dedupeKey: 'platform-wallet-short-'.now()->format('Y-m-d'),
            ), ['database']);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Claw back up to $target of a credited referral commission, once. The referrer's wallet never
     * goes below zero: what their available balance cannot cover is recorded as owed on the
     * commission and taken from later earnings. A commission that was funded from the platform
     * wallet (REF-COM-{id}-P) returns what is taken to the platform. Locks platform, referrer
     * wallet, then the commission, the same order as reward credits.
     *
     * @return array{taken: string, owed: string, transaction: ?Transaction}
     */
    public function reverseReferralCommission(int $commissionId, string $target, string $reason): array
    {
        $none = ['taken' => $this->amt(0), 'owed' => $this->amt(0), 'transaction' => null];
        $referrerId = ReferralCommission::query()->whereKey($commissionId)->value('referrer_id');
        if (! $referrerId) {
            return $none;
        }

        return DB::transaction(function () use ($commissionId, $referrerId, $target, $reason, $none) {
            $platform = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            $wallet = Wallet::query()->where('user_id', $referrerId)->lockForUpdate()->first();
            $commission = ReferralCommission::query()->whereKey($commissionId)->lockForUpdate()->first();
            if (! $wallet || ! $commission || $commission->status !== ReferralCommission::STATUS_CREDITED) {
                return $none;
            }

            $target = $this->amt($target);
            if (bccomp($target, (string) $commission->amount, self::SCALE) > 0) {
                $target = $this->amt($commission->amount);
            }
            $available = $wallet->availableBalance();
            $taken = bccomp($available, '0', self::SCALE) <= 0
                ? $this->amt(0)
                : (bccomp($available, $target, self::SCALE) < 0 ? $available : $target);
            $owed = bcsub($target, $taken, self::SCALE);

            $txn = null;
            if (bccomp($taken, '0', self::SCALE) > 0) {
                $wallet->balance = bcsub((string) $wallet->balance, $taken, self::SCALE);
                $wallet->save();
                $txn = $this->createLedgerEntry($wallet, [
                    'reference' => 'REF-REV-'.$commission->id,
                    'user_id' => $wallet->user_id,
                    'reverses_transaction_id' => $commission->transaction_id,
                    'type' => TransactionType::ReferralCommission->value,
                    'label' => 'Referral commission reversed',
                    'description' => $reason,
                    'amount' => bcmul($taken, '-1', self::SCALE),
                    'currency' => $wallet->currency ?: $this->currency(),
                    'status' => 'completed',
                ]);
                $this->returnCommissionToPlatform($platform, $commission, $taken, 'REF-REV-'.$commission->id.'-P');
            }

            $commission->forceFill([
                'status' => ReferralCommission::STATUS_REVERSED,
                'reversed_amount' => $taken,
                'owed_amount' => $owed,
                'reversal_transaction_id' => $txn?->id,
                'reversed_at' => now(),
            ])->save();

            return ['taken' => $taken, 'owed' => $owed, 'transaction' => $txn];
        });
    }

    /**
     * Take what the user still owes from reversed commissions out of a credit they just received.
     * Runs inside the crediting transaction, after the platform and user wallets are locked.
     */
    private function recoverOwedCommissions(Wallet $platform, Wallet $wallet): void
    {
        if (! ReferralCommission::query()->where('referrer_id', $wallet->user_id)->where('owed_amount', '>', 0)->exists()) {
            return;
        }

        $owing = ReferralCommission::query()
            ->where('referrer_id', $wallet->user_id)
            ->where('owed_amount', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($owing as $commission) {
            $available = $wallet->availableBalance();
            if (bccomp($available, '0', self::SCALE) <= 0) {
                return;
            }
            $owed = $this->amt($commission->owed_amount);
            $take = bccomp($available, $owed, self::SCALE) < 0 ? $available : $owed;

            $wallet->balance = bcsub((string) $wallet->balance, $take, self::SCALE);
            $wallet->save();
            $this->createLedgerEntry($wallet, [
                'user_id' => $wallet->user_id,
                'type' => TransactionType::ReferralCommission->value,
                'label' => 'Referral commission recovered',
                'description' => 'Balance owed from reversed referral commission #'.$commission->id,
                'amount' => bcmul($take, '-1', self::SCALE),
                'currency' => $wallet->currency ?: $this->currency(),
                'status' => 'completed',
            ]);
            $this->returnCommissionToPlatform($platform, $commission, $take, null);

            $commission->forceFill([
                'owed_amount' => bcsub($owed, $take, self::SCALE),
                'reversed_amount' => bcadd($this->amt($commission->reversed_amount), $take, self::SCALE),
            ])->save();
        }
    }

    private function returnCommissionToPlatform(Wallet $platform, ReferralCommission $commission, string $amount, ?string $reference): void
    {
        $funded = Transaction::query()->where('reference', 'REF-COM-'.$commission->id.'-P')->exists();
        if (! $funded || bccomp($amount, '0', self::SCALE) <= 0) {
            return;
        }

        $platform->balance = bcadd((string) $platform->balance, $amount, self::SCALE);
        $platform->save();
        $this->createLedgerEntry($platform, array_filter([
            'reference' => $reference,
            'user_id' => $platform->user_id,
            'type' => TransactionType::ReferralCommissionExpense->value,
            'label' => 'Referral commission returned',
            'description' => 'Reversed referral commission #'.$commission->id,
            'amount' => $amount,
            'currency' => $this->currency(),
            'status' => 'completed',
        ], fn ($v) => $v !== null));
    }

    public function reverseTransaction(Transaction $original, string $reason, ?int $adminId = null): Transaction
    {
        if ($original->reverses_transaction_id) {
            throw new InvalidArgumentException('Cannot reverse a reversal entry.');
        }

        if ($original->type !== TransactionType::Funding->value) {
            throw new InvalidArgumentException('Only funding transactions can be reversed.');
        }

        $existingReversal = Transaction::query()
            ->where('reverses_transaction_id', $original->id)
            ->exists();

        if ($existingReversal) {
            throw new InvalidArgumentException('This transaction has already been reversed.');
        }

        return DB::transaction(function () use ($original, $reason, $adminId) {
            $wallet = Wallet::where('id', $original->wallet_id)->lockForUpdate()->firstOrFail();
            $reverseAmount = bcmul((string) $original->amount, '-1', self::SCALE);
            $newBalance = bcadd((string) $wallet->balance, $reverseAmount, self::SCALE);

            if (bccomp($newBalance, '0', self::SCALE) < 0) {
                throw new InvalidArgumentException('Reversal would result in negative balance.');
            }

            if (bccomp($newBalance, (string) $wallet->locked_balance, self::SCALE) < 0) {
                throw new InvalidArgumentException('Reversal would make balance lower than locked funds.');
            }

            $wallet->balance = $newBalance;
            $wallet->save();

            $reversal = $this->createLedgerEntry($wallet, [
                'user_id' => $original->user_id,
                'wallet_funding_id' => $original->wallet_funding_id,
                'order_id' => $original->order_id,
                'withdrawal_id' => $original->withdrawal_id,
                'escrow_id' => $original->escrow_id,
                'reverses_transaction_id' => $original->id,
                'type' => TransactionType::Reversal->value,
                'label' => 'Reversal',
                'description' => $reason.($adminId ? ' (admin #'.$adminId.')' : ''),
                'amount' => $reverseAmount,
                'currency' => $original->currency,
                'status' => 'completed',
            ]);

            if ($original->wallet_funding_id) {
                WalletFunding::where('id', $original->wallet_funding_id)->update([
                    'status' => 'reversed',
                    'internal_status' => 'reversed',
                    'reversed_at' => now(),
                    'reversal_transaction_id' => $reversal->id,
                ]);
            }

            return $reversal;
        });
    }

    /**
     * Move a campaign's agent budget into the platform wallet's locked balance.
     * The reference CMP-HOLD-{id} is unique, so a campaign is held at most once.
     */
    public function holdCampaignBudget(int $campaignId, string $amount, ?int $orderId = null): Transaction
    {
        $amountStr = $this->amt($amount);

        return DB::transaction(function () use ($campaignId, $amountStr, $orderId) {
            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $platformWallet->availableBalance(), $amountStr, self::SCALE) < 0) {
                throw new InvalidArgumentException('Platform wallet has insufficient available balance to hold this campaign budget.');
            }

            $platformWallet->locked_balance = bcadd((string) $platformWallet->locked_balance, $amountStr, self::SCALE);
            $platformWallet->save();

            $this->createHold($platformWallet, WalletHoldReason::CampaignBudget, $campaignId, $amountStr);

            return $this->createLedgerEntry($platformWallet, [
                'reference' => 'CMP-HOLD-'.$campaignId,
                'user_id' => $platformWallet->user_id,
                'order_id' => $orderId,
                'type' => TransactionType::CampaignBudgetHold->value,
                'label' => 'Campaign budget held',
                'description' => 'Agent budget held for campaign #'.$campaignId,
                'amount' => 0,
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);
        });
    }

    /**
     * Pay one campaign reward out of the held budget: the platform wallet's balance and
     * locked balance both drop, the agent is credited. CMP-PAY-{participation} is unique,
     * so a second payment for the same participation fails at the database.
     */
    public function payCampaignRewardFromHold(User $agent, int $participationId, string $amount, string $label): Transaction
    {
        $amountStr = $this->amt($amount);
        if (bccomp($amountStr, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException('Reward amount must be positive.');
        }

        return DB::transaction(function () use ($agent, $participationId, $amountStr, $label) {
            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            if (bccomp((string) $platformWallet->locked_balance, $amountStr, self::SCALE) < 0
                || bccomp((string) $platformWallet->balance, $amountStr, self::SCALE) < 0) {
                throw new InvalidArgumentException('Held campaign funds are insufficient for this payout.');
            }

            $agentWallet = Wallet::query()->where('user_id', $agent->id)->lockForUpdate()->first();
            if (! $agentWallet) {
                throw new InvalidArgumentException('User has no wallet to credit.');
            }

            $platformWallet->balance = bcsub((string) $platformWallet->balance, $amountStr, self::SCALE);
            $platformWallet->locked_balance = bcsub((string) $platformWallet->locked_balance, $amountStr, self::SCALE);
            $platformWallet->save();

            $this->createLedgerEntry($platformWallet, [
                'reference' => 'CMP-PAY-'.$participationId.'-P',
                'user_id' => $platformWallet->user_id,
                'type' => TransactionType::CampaignReward->value,
                'label' => 'Campaign reward paid from hold',
                'description' => $label.' (task #'.$participationId.')',
                'amount' => bcmul($amountStr, '-1', self::SCALE),
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            $agentWallet->balance = bcadd((string) $agentWallet->balance, $amountStr, self::SCALE);
            $agentWallet->save();

            $credit = $this->createLedgerEntry($agentWallet, [
                'reference' => 'CMP-PAY-'.$participationId,
                'user_id' => $agent->id,
                'type' => TransactionType::CampaignReward->value,
                'label' => $label,
                'description' => $label,
                'amount' => $amountStr,
                'currency' => $agentWallet->currency ?: $this->currency(),
                'status' => 'completed',
            ]);
            $this->recoverOwedCommissions($platformWallet, $agentWallet);

            return $credit;
        });
    }

    /** Release leftover held funds back to platform revenue (balance unchanged, lock removed). */
    public function releaseCampaignBudget(int $campaignId, string $amount): ?Transaction
    {
        $amountStr = $this->amt($amount);
        if (bccomp($amountStr, '0', self::SCALE) <= 0) {
            // Nothing left to unlock (every unit was paid), but the hold row still has to close.
            $this->transitionHold($this->getPlatformWallet()->id, WalletHoldReason::CampaignBudget, $campaignId, WalletHoldStatus::Released);

            return null;
        }

        return DB::transaction(function () use ($campaignId, $amountStr) {
            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            $release = bccomp((string) $platformWallet->locked_balance, $amountStr, self::SCALE) < 0
                ? (bccomp((string) $platformWallet->locked_balance, '0', self::SCALE) > 0 ? $this->amt($platformWallet->locked_balance) : $this->amt(0))
                : $amountStr;

            $platformWallet->locked_balance = bcsub((string) $platformWallet->locked_balance, $release, self::SCALE);
            $platformWallet->save();

            $this->transitionHold($platformWallet->id, WalletHoldReason::CampaignBudget, $campaignId, WalletHoldStatus::Released);

            return $this->createLedgerEntry($platformWallet, [
                'reference' => 'CMP-SETTLE-'.$campaignId,
                'user_id' => $platformWallet->user_id,
                'type' => TransactionType::CampaignBudgetRelease->value,
                'label' => 'Campaign budget settled',
                'description' => 'Unused held funds ('.$release.') released to revenue for campaign #'.$campaignId,
                'amount' => 0,
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);
        });
    }

    /**
     * Refund a cancelled campaign's unused units to the creator. The held part is unlocked;
     * the whole refund leaves the platform balance. CMP-REFUND-{id} is unique.
     */
    public function refundCampaign(Wallet $creatorWallet, int $campaignId, string $refundTotal, string $heldPart): Transaction
    {
        $total = $this->amt($refundTotal);
        $held = $this->amt($heldPart);

        return DB::transaction(function () use ($creatorWallet, $campaignId, $total, $held) {
            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            if (bccomp((string) $platformWallet->balance, $total, self::SCALE) < 0
                || bccomp((string) $platformWallet->locked_balance, $held, self::SCALE) < 0) {
                throw new InvalidArgumentException('Platform wallet balance is insufficient for this refund.');
            }

            $platformWallet->balance = bcsub((string) $platformWallet->balance, $total, self::SCALE);
            $platformWallet->locked_balance = bcsub((string) $platformWallet->locked_balance, $held, self::SCALE);
            $platformWallet->save();

            $this->transitionHold($platformWallet->id, WalletHoldReason::CampaignBudget, $campaignId, WalletHoldStatus::Released);

            $this->createLedgerEntry($platformWallet, [
                'reference' => 'CMP-REFUND-'.$campaignId.'-P',
                'user_id' => $platformWallet->user_id,
                'type' => TransactionType::Refund->value,
                'label' => 'Campaign refund',
                'description' => 'Refund of unused units for campaign #'.$campaignId,
                'amount' => bcmul($total, '-1', self::SCALE),
                'currency' => $this->currency(),
                'status' => 'completed',
            ]);

            $creatorWallet = Wallet::where('id', $creatorWallet->id)->lockForUpdate()->firstOrFail();
            $creatorWallet->balance = bcadd((string) $creatorWallet->balance, $total, self::SCALE);
            $creatorWallet->save();

            return $this->createLedgerEntry($creatorWallet, [
                'reference' => 'CMP-REFUND-'.$campaignId,
                'user_id' => $creatorWallet->user_id,
                'type' => TransactionType::Refund->value,
                'label' => 'Campaign refund',
                'description' => 'Refund for undelivered units of campaign #'.$campaignId,
                'amount' => $total,
                'currency' => $creatorWallet->currency ?: $this->currency(),
                'status' => 'completed',
            ]);
        });
    }

    public function getPlatformWallet(): Wallet
    {
        $wallet = Wallet::query()
            ->where('type', WalletType::Platform->value)
            ->first();

        if ($wallet) {
            return $wallet;
        }

        $systemUser = \App\Models\User::firstOrCreate(
            ['email' => 'platform-wallet@internal.7thtradehub'],
            [
                'name' => 'Platform Wallet',
                'username' => 'platform_wallet',
                'password' => bcrypt(Str::random(64)),
                'email_verified_at' => now(),
            ]
        );

        return Wallet::create([
            'user_id' => $systemUser->id,
            'type' => WalletType::Platform->value,
            'balance' => 0,
            'locked_balance' => 0,
            'currency' => $this->currency(),
            'gateway_subaccount_id' => 'platform',
            'status' => 'active',
        ]);
    }

    public function walletSnapshot(Wallet $wallet): array
    {
        return [
            'wallet_id' => $wallet->id,
            'balance' => (string) $wallet->balance,
            'locked_balance' => (string) $wallet->locked_balance,
            'available' => (string) $wallet->availableBalance(),
        ];
    }

    public function createHold(
        Wallet $wallet,
        WalletHoldReason $reason,
        ?int $reasonId,
        string|int|float $amount,
        ?\DateTimeInterface $expiresAt = null,
        array $meta = [],
    ): WalletHold {
        return WalletHold::create([
            'wallet_id' => $wallet->id,
            'reason_type' => $reason->value,
            'reason_id' => $reasonId,
            'amount' => $this->amt($amount),
            'status' => WalletHoldStatus::Active->value,
            'expires_at' => $expiresAt,
            'meta' => $meta ?: null,
        ]);
    }

    public function transitionHold(
        int $walletId,
        WalletHoldReason $reason,
        int $reasonId,
        WalletHoldStatus $to,
    ): void {
        WalletHold::query()
            ->where('wallet_id', $walletId)
            ->where('reason_type', $reason->value)
            ->where('reason_id', $reasonId)
            ->where('status', WalletHoldStatus::Active->value)
            ->update(['status' => $to->value]);
    }

    private function findFundingTransaction(WalletFunding $funding): ?Transaction
    {
        return Transaction::query()
            ->where('wallet_funding_id', $funding->id)
            ->where('type', TransactionType::Funding->value)
            ->first();
    }

    private function findWithdrawalTransaction(Withdrawal $withdrawal): ?Transaction
    {
        return Transaction::query()
            ->where('withdrawal_id', $withdrawal->id)
            ->where('type', TransactionType::Withdrawal->value)
            ->first();
    }

    private function createLedgerEntry(Wallet $wallet, array $data): Transaction
    {
        return Transaction::create(array_merge([
            'wallet_id' => $wallet->id,
            'reference' => 'TXN-'.strtoupper(Str::random(10)),
        ], $data));
    }
}
