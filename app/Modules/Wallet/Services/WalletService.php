<?php

namespace App\Modules\Wallet\Services;

use App\Enums\TransactionType;
use App\Enums\WalletHoldReason;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletType;
use App\Events\WalletFunded;
use App\Events\WalletWithdrawalCompleted;
use App\Events\WithdrawalPayoutFailed;
use App\Models\Order;
use App\Models\PaymentTimelineEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFunding;
use App\Models\WalletHold;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WalletService
{
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

            $wallet->balance = bcadd((string) $wallet->balance, (string) $funding->amount, 2);
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

    public function debitForPlatformPurchase(Wallet $wallet, Order $order, float $amount): Transaction
    {
        return DB::transaction(function () use ($wallet, $order, $amount) {
            $amountStr = number_format((float) $amount, 2, '.', '');

            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $wallet->availableBalance(), $amountStr, 2) < 0) {
                throw new InvalidArgumentException('Insufficient wallet balance.');
            }

            $wallet->balance = bcsub((string) $wallet->balance, $amountStr, 2);
            $wallet->save();

            $buyerTxn = $this->createLedgerEntry($wallet, [
                'user_id' => $wallet->user_id,
                'order_id' => $order->id,
                'type' => TransactionType::Purchase->value,
                'label' => 'Platform purchase',
                'description' => 'Paid for order '.$order->reference,
                'amount' => -((float) $amountStr),
                'currency' => 'NGN',
                'status' => 'completed',
            ]);

            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            $platformWallet->balance = bcadd((string) $platformWallet->balance, $amountStr, 2);
            $platformWallet->save();

            $this->createLedgerEntry($platformWallet, [
                'user_id' => $platformWallet->user_id,
                'order_id' => $order->id,
                'type' => TransactionType::Purchase->value,
                'label' => 'Platform product sale',
                'description' => 'Revenue from order '.$order->reference,
                'amount' => (float) $amountStr,
                'currency' => 'NGN',
                'status' => 'completed',
            ]);

            return $buyerTxn;
        });
    }

    /**
     * Credit platform revenue for a Monnify-paid catalog order (no user wallet required).
     */
    public function creditPlatformFromGatewaySale(Order $order, float $amount): Transaction
    {
        return DB::transaction(function () use ($order, $amount) {
            $amountStr = number_format((float) $amount, 2, '.', '');

            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            $platformWallet->balance = bcadd((string) $platformWallet->balance, $amountStr, 2);
            $platformWallet->save();

            return $this->createLedgerEntry($platformWallet, [
                'user_id' => $platformWallet->user_id,
                'order_id' => $order->id,
                'type' => TransactionType::Purchase->value,
                'label' => 'Platform product sale (gateway)',
                'description' => 'Gateway revenue from order '.$order->reference,
                'amount' => (float) $amountStr,
                'currency' => 'NGN',
                'status' => 'completed',
            ]);
        });
    }

    public function lockForWithdrawal(Withdrawal $withdrawal): Transaction
    {
        return DB::transaction(function () use ($withdrawal) {
            $wallet = Wallet::where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();
            $amountStr = number_format((float) $withdrawal->amount, 2, '.', '');

            if (bccomp((string) $wallet->availableBalance(), $amountStr, 2) < 0) {
                throw new InvalidArgumentException('Insufficient balance for withdrawal.');
            }

            $wallet->locked_balance = bcadd((string) $wallet->locked_balance, $amountStr, 2);
            $wallet->save();

            $this->createHold($wallet, WalletHoldReason::Withdrawal, $withdrawal->id, (float) $amountStr);

            $txn = $this->createLedgerEntry($wallet, [
                'user_id' => $withdrawal->user_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => TransactionType::WithdrawalHold->value,
                'label' => 'Withdrawal hold',
                'description' => 'Funds held for withdrawal '.$withdrawal->reference,
                'amount' => -((float) $amountStr),
                'currency' => 'NGN',
                'status' => 'pending',
            ]);

            PaymentTimelineEvent::record($withdrawal, 'created', 'Created');

            return $txn;
        });
    }

    /**
     * Mark approved and move to processing after Monnify accepts the transfer (or legacy complete).
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
        string $provider = 'monnify',
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

            $completableInternal = ['approved', 'processing', 'pending_review', Withdrawal::INTERNAL_AWAITING_PROVIDER_AUTH, null];
            if (! in_array($withdrawal->internal_status, $completableInternal, true)
                && ! in_array($withdrawal->status, ['pending', 'approved', 'processing'], true)) {
                throw new InvalidArgumentException('Withdrawal cannot be completed from current status.');
            }

            $wallet = Wallet::where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();
            $amountStr = number_format((float) $withdrawal->amount, 2, '.', '');

            if (bccomp((string) $wallet->locked_balance, $amountStr, 2) < 0) {
                throw new InvalidArgumentException('Insufficient locked balance for withdrawal.');
            }

            $wallet->locked_balance = bcsub((string) $wallet->locked_balance, $amountStr, 2);
            $wallet->balance = bcsub((string) $wallet->balance, $amountStr, 2);
            $wallet->save();

            $this->transitionHold(
                $wallet->id,
                WalletHoldReason::Withdrawal,
                $withdrawal->id,
                WalletHoldStatus::Consumed
            );

            $transaction = $this->createLedgerEntry($wallet, [
                'user_id' => $withdrawal->user_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => TransactionType::Withdrawal->value,
                'label' => 'Withdrawal to bank',
                'amount' => -((float) $amountStr),
                'currency' => 'NGN',
                'status' => 'completed',
            ]);

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
                    (string) ($withdrawal->currency ?: 'NGN')
                );
            });

            return $transaction;
        });
    }

    /**
     * Legacy admin approve path (no Monnify): approve + complete in one step.
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
            $amountStr = number_format((float) $withdrawal->amount, 2, '.', '');

            if (bccomp((string) $wallet->locked_balance, $amountStr, 2) < 0) {
                throw new InvalidArgumentException('Insufficient locked balance to unlock.');
            }

            $wallet->locked_balance = bcsub((string) $wallet->locked_balance, $amountStr, 2);
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
                'amount' => (float) $amountStr,
                'currency' => 'NGN',
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
                    (string) ($withdrawal->currency ?: 'NGN')
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

            if (! in_array($withdrawal->status, ['pending', 'approved'], true)
                && ! in_array($withdrawal->internal_status, ['pending_review', 'approved', null], true)
                && $withdrawal->internal_status !== Withdrawal::INTERNAL_AWAITING_PROVIDER_AUTH) {
                throw new InvalidArgumentException('Withdrawal cannot be rejected from current status.');
            }

            if (in_array($withdrawal->status, ['processing'], true)
                && $withdrawal->internal_status !== Withdrawal::INTERNAL_AWAITING_PROVIDER_AUTH) {
                throw new InvalidArgumentException('Cannot reject a withdrawal that is already processing.');
            }

            $wallet = Wallet::where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();
            $amountStr = number_format((float) $withdrawal->amount, 2, '.', '');

            if (bccomp((string) $wallet->locked_balance, $amountStr, 2) < 0) {
                throw new InvalidArgumentException('Insufficient locked balance to unlock.');
            }

            $wallet->locked_balance = bcsub((string) $wallet->locked_balance, $amountStr, 2);
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
                'amount' => (float) $amountStr,
                'currency' => 'NGN',
                'status' => 'completed',
            ]);

            $withdrawal->update([
                'status' => 'rejected',
                'internal_status' => 'failed',
                'admin_notes' => $adminNotes,
            ]);

            PaymentTimelineEvent::record($withdrawal, 'rejected', 'Rejected, funds returned');
        });
    }

    public function adminAdjust(Wallet $wallet, float $amount, string $reason, int $adminId): Transaction
    {
        return DB::transaction(function () use ($wallet, $amount, $reason, $adminId) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $newBalance = bcadd((string) $wallet->balance, (string) $amount, 2);
            if (bccomp($newBalance, '0', 2) < 0) {
                throw new InvalidArgumentException('Adjustment would result in negative balance.');
            }

            if (bccomp($newBalance, (string) $wallet->locked_balance, 2) < 0) {
                throw new InvalidArgumentException('Adjustment would make balance lower than locked funds.');
            }

            $wallet->balance = $newBalance;
            $wallet->save();

            return $this->createLedgerEntry($wallet, [
                'user_id' => $wallet->user_id,
                'type' => TransactionType::AdminAdjustment->value,
                'label' => 'Admin adjustment',
                'description' => $reason.' (admin #'.$adminId.')',
                'amount' => $amount,
                'currency' => 'NGN',
                'status' => 'completed',
            ]);
        });
    }

    /**
     * Credit an arbitrary positive amount to a user's wallet (e.g. campaign rewards).
     */
    public function creditReward(
        User $user,
        float $amount,
        string $label,
        string $type = 'campaign_reward',
    ): Transaction {
        $amountStr = number_format((float) $amount, 2, '.', '');

        if (bccomp($amountStr, '0', 2) <= 0) {
            throw new InvalidArgumentException('Reward amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amountStr, $label, $type) {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new InvalidArgumentException('User has no wallet to credit.');
            }

            $wallet->balance = bcadd((string) $wallet->balance, $amountStr, 2);
            $wallet->save();

            return $this->createLedgerEntry($wallet, [
                'user_id' => $user->id,
                'type' => $type,
                'label' => $label,
                'description' => $label,
                'amount' => (float) $amountStr,
                'currency' => $wallet->currency ?: 'NGN',
                'status' => 'completed',
            ]);
        });
    }

    /**
     * Claw back a credited referral commission. The balance may go below zero when the
     * commission was already withdrawn; future earnings then cover the difference.
     */
    public function reverseReferralCommission(Transaction $original, string $reason): Transaction
    {
        if ($original->type !== TransactionType::ReferralCommission->value || bccomp((string) $original->amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('Only credited referral commissions can be reversed here.');
        }

        return DB::transaction(function () use ($original, $reason) {
            $existing = Transaction::query()
                ->where('reverses_transaction_id', $original->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet = Wallet::where('id', $original->wallet_id)->lockForUpdate()->firstOrFail();
            $reverseAmount = bcmul((string) $original->amount, '-1', 2);

            $wallet->balance = bcadd((string) $wallet->balance, $reverseAmount, 2);
            $wallet->save();

            return $this->createLedgerEntry($wallet, [
                'user_id' => $original->user_id,
                'reverses_transaction_id' => $original->id,
                'type' => TransactionType::ReferralCommission->value,
                'label' => 'Referral commission reversed',
                'description' => $reason,
                'amount' => $reverseAmount,
                'currency' => $original->currency,
                'status' => 'completed',
            ]);
        });
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
            $reverseAmount = bcmul((string) $original->amount, '-1', 2);
            $newBalance = bcadd((string) $wallet->balance, $reverseAmount, 2);

            if (bccomp($newBalance, '0', 2) < 0) {
                throw new InvalidArgumentException('Reversal would result in negative balance.');
            }

            if (bccomp($newBalance, (string) $wallet->locked_balance, 2) < 0) {
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
        $amountStr = number_format((float) $amount, 2, '.', '');

        return DB::transaction(function () use ($campaignId, $amountStr, $orderId) {
            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $platformWallet->availableBalance(), $amountStr, 2) < 0) {
                throw new InvalidArgumentException('Platform wallet has insufficient available balance to hold this campaign budget.');
            }

            $platformWallet->locked_balance = bcadd((string) $platformWallet->locked_balance, $amountStr, 2);
            $platformWallet->save();

            $this->createHold($platformWallet, WalletHoldReason::CampaignBudget, $campaignId, (float) $amountStr);

            return $this->createLedgerEntry($platformWallet, [
                'reference' => 'CMP-HOLD-'.$campaignId,
                'user_id' => $platformWallet->user_id,
                'order_id' => $orderId,
                'type' => TransactionType::CampaignBudgetHold->value,
                'label' => 'Campaign budget held',
                'description' => 'Agent budget held for campaign #'.$campaignId,
                'amount' => 0,
                'currency' => 'NGN',
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
        $amountStr = number_format((float) $amount, 2, '.', '');
        if (bccomp($amountStr, '0', 2) <= 0) {
            throw new InvalidArgumentException('Reward amount must be positive.');
        }

        return DB::transaction(function () use ($agent, $participationId, $amountStr, $label) {
            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            if (bccomp((string) $platformWallet->locked_balance, $amountStr, 2) < 0
                || bccomp((string) $platformWallet->balance, $amountStr, 2) < 0) {
                throw new InvalidArgumentException('Held campaign funds are insufficient for this payout.');
            }

            $agentWallet = Wallet::query()->where('user_id', $agent->id)->lockForUpdate()->first();
            if (! $agentWallet) {
                throw new InvalidArgumentException('User has no wallet to credit.');
            }

            $platformWallet->balance = bcsub((string) $platformWallet->balance, $amountStr, 2);
            $platformWallet->locked_balance = bcsub((string) $platformWallet->locked_balance, $amountStr, 2);
            $platformWallet->save();

            $this->createLedgerEntry($platformWallet, [
                'reference' => 'CMP-PAY-'.$participationId.'-P',
                'user_id' => $platformWallet->user_id,
                'type' => TransactionType::CampaignReward->value,
                'label' => 'Campaign reward paid from hold',
                'description' => $label.' (task #'.$participationId.')',
                'amount' => -((float) $amountStr),
                'currency' => 'NGN',
                'status' => 'completed',
            ]);

            $agentWallet->balance = bcadd((string) $agentWallet->balance, $amountStr, 2);
            $agentWallet->save();

            return $this->createLedgerEntry($agentWallet, [
                'reference' => 'CMP-PAY-'.$participationId,
                'user_id' => $agent->id,
                'type' => TransactionType::CampaignReward->value,
                'label' => $label,
                'description' => $label,
                'amount' => (float) $amountStr,
                'currency' => $agentWallet->currency ?: 'NGN',
                'status' => 'completed',
            ]);
        });
    }

    /** Release leftover held funds back to platform revenue (balance unchanged, lock removed). */
    public function releaseCampaignBudget(int $campaignId, string $amount): ?Transaction
    {
        $amountStr = number_format((float) $amount, 2, '.', '');
        if (bccomp($amountStr, '0', 2) <= 0) {
            // Nothing left to unlock (every unit was paid), but the hold row still has to close.
            $this->transitionHold($this->getPlatformWallet()->id, WalletHoldReason::CampaignBudget, $campaignId, WalletHoldStatus::Released);

            return null;
        }

        return DB::transaction(function () use ($campaignId, $amountStr) {
            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            $release = bccomp((string) $platformWallet->locked_balance, $amountStr, 2) < 0
                ? number_format(max(0, (float) $platformWallet->locked_balance), 2, '.', '')
                : $amountStr;

            $platformWallet->locked_balance = bcsub((string) $platformWallet->locked_balance, $release, 2);
            $platformWallet->save();

            $this->transitionHold($platformWallet->id, WalletHoldReason::CampaignBudget, $campaignId, WalletHoldStatus::Released);

            return $this->createLedgerEntry($platformWallet, [
                'reference' => 'CMP-SETTLE-'.$campaignId,
                'user_id' => $platformWallet->user_id,
                'type' => TransactionType::CampaignBudgetRelease->value,
                'label' => 'Campaign budget settled',
                'description' => 'Unused held funds ('.$release.') released to revenue for campaign #'.$campaignId,
                'amount' => 0,
                'currency' => 'NGN',
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
        $total = number_format((float) $refundTotal, 2, '.', '');
        $held = number_format((float) $heldPart, 2, '.', '');

        return DB::transaction(function () use ($creatorWallet, $campaignId, $total, $held) {
            $platformWallet = Wallet::where('id', $this->getPlatformWallet()->id)->lockForUpdate()->firstOrFail();
            if (bccomp((string) $platformWallet->balance, $total, 2) < 0
                || bccomp((string) $platformWallet->locked_balance, $held, 2) < 0) {
                throw new InvalidArgumentException('Platform wallet balance is insufficient for this refund.');
            }

            $platformWallet->balance = bcsub((string) $platformWallet->balance, $total, 2);
            $platformWallet->locked_balance = bcsub((string) $platformWallet->locked_balance, $held, 2);
            $platformWallet->save();

            $this->transitionHold($platformWallet->id, WalletHoldReason::CampaignBudget, $campaignId, WalletHoldStatus::Released);

            $this->createLedgerEntry($platformWallet, [
                'reference' => 'CMP-REFUND-'.$campaignId.'-P',
                'user_id' => $platformWallet->user_id,
                'type' => TransactionType::Refund->value,
                'label' => 'Campaign refund',
                'description' => 'Refund of unused units for campaign #'.$campaignId,
                'amount' => -((float) $total),
                'currency' => 'NGN',
                'status' => 'completed',
            ]);

            $creatorWallet = Wallet::where('id', $creatorWallet->id)->lockForUpdate()->firstOrFail();
            $creatorWallet->balance = bcadd((string) $creatorWallet->balance, $total, 2);
            $creatorWallet->save();

            return $this->createLedgerEntry($creatorWallet, [
                'reference' => 'CMP-REFUND-'.$campaignId,
                'user_id' => $creatorWallet->user_id,
                'type' => TransactionType::Refund->value,
                'label' => 'Campaign refund',
                'description' => 'Refund for unused units of cancelled campaign #'.$campaignId,
                'amount' => (float) $total,
                'currency' => $creatorWallet->currency ?: 'NGN',
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
            'currency' => 'NGN',
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
        float $amount,
        ?\DateTimeInterface $expiresAt = null,
        array $meta = [],
    ): WalletHold {
        return WalletHold::create([
            'wallet_id' => $wallet->id,
            'reason_type' => $reason->value,
            'reason_id' => $reasonId,
            'amount' => $amount,
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
