<?php

namespace App\Modules\Wallet\Services;

use App\Models\PaymentTimelineEvent;
use App\Models\PayoutAccount;
use App\Models\Withdrawal;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Wallet\Payments\Contracts\PayoutGateway;
use App\Modules\Wallet\Payments\Contracts\ReclaimsFailedPayouts;
use App\Modules\Wallet\Payments\Data\PaymentEvent;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Payments\Data\PayoutQuote;
use App\Modules\Wallet\Payments\Data\PayoutRequest;
use App\Modules\Wallet\Payments\Data\PayoutResult;
use App\Modules\Wallet\Payments\FailureClass;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Modules\Wallet\Payments\PayoutGate;
use App\Services\Notifications\AdminPaymentAlertNotifier;
use App\Support\Money\Currency;
use App\Support\Money\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Sends approved withdrawals through the payout route, one attempt at a time.
 *
 * Fallback rule (the only one):
 * - not_created: one automatic attempt on the next verified gateway, after the fee check.
 * - recoverable / other: nothing automatic; an admin may authorise a retry elsewhere or refund.
 * - account_details: never retried elsewhere; full refund, bounce fee only when every condition holds.
 * - unknown: wait until the same provider confirms; no switch and no admin retry before that.
 */
class WithdrawalPayoutService
{
    public function __construct(
        private GatewayRegistry $registry,
        private WalletService $wallets,
        private WithdrawalFeeService $fees,
        private PayoutAccountService $accounts,
        private AdminPaymentAlertNotifier $alerts,
    ) {}

    /** True when at least one payout gateway is configured (otherwise approvals are manual). */
    public function gatewaysConfigured(): bool
    {
        foreach ($this->registry->payoutOrder() as $key) {
            if ($this->registry->payoutDriver($key)?->isConfigured()) {
                return true;
            }
        }

        return false;
    }

    /**
     * USD balance per configured payout gateway (null when the provider doesn't expose it).
     *
     * @return array<string, array{name: string, balance: ?string}>
     */
    public function gatewayBalances(): array
    {
        $out = [];
        foreach ($this->registry->payoutOrder() as $key) {
            $driver = $this->registry->payoutDriver($key);
            if (! $driver || ! $driver->isConfigured()) {
                continue;
            }
            try {
                $balance = $driver->balance('USD');
            } catch (\Throwable) {
                $balance = null;
            }
            $out[$key] = ['name' => $driver->displayName(), 'balance' => $balance];
        }

        return $out;
    }

    /**
     * Gateways an admin may choose for this withdrawal's destination, in route order.
     *
     * @return array<string, string> key => name
     */
    public function routeOptions(Withdrawal $withdrawal): array
    {
        $destination = PayoutAccountDetails::fromWithdrawal($withdrawal);

        return array_map(
            fn (PayoutGateway $d) => $d->displayName(),
            $this->registry->payoutRoute($destination->country, $destination->method, $destination->userId),
        );
    }

    /**
     * Approve & send. An admin may pick a gateway on the route (for example when the first one's
     * balance is too low); that choice goes through the same re-quote and fee check.
     */
    public function approveAndSend(
        Withdrawal $withdrawal,
        int $adminId,
        ?string $ip = null,
        ?string $note = null,
        ?string $gatewayKey = null,
    ): void {
        if ($withdrawal->status !== 'pending' && $withdrawal->internal_status !== 'pending_review') {
            throw new InvalidArgumentException('Withdrawal is not pending review.');
        }

        if (! $this->gatewaysConfigured()) {
            $this->wallets->debitForWithdrawal($withdrawal, $adminId);
            $withdrawal->refresh();
            if ($ip || $note) {
                $withdrawal->update(array_filter(['approved_ip' => $ip, 'approval_note' => $note]));
            }

            return;
        }

        PayoutGate::assertOpen();
        $this->assertDestinationUsable($withdrawal);
        $key = $this->pickGateway($withdrawal, $gatewayKey);

        $quote = $this->checkFee($withdrawal, $key);
        if ($quote === null) {
            return;
        }
        $this->assertBalance($withdrawal->fresh(), $key, $quote);

        $this->wallets->markWithdrawalApproved($withdrawal, $adminId, $ip, $note);

        $this->send($withdrawal->fresh(), $key, WithdrawalPayoutAttempt::TRIGGER_INITIAL, $gatewayKey !== null ? $adminId : null, $quote);
    }

    /**
     * Admin-authorised retry after a confirmed recoverable or unclassified failure.
     */
    public function adminRetryViaGateway(Withdrawal $withdrawal, string $gatewayKey, int $adminId): WithdrawalPayoutAttempt
    {
        $withdrawal = $withdrawal->fresh() ?? $withdrawal;
        PayoutGate::assertOpen();
        if (! $withdrawal->needsAdminDecision()) {
            throw new InvalidArgumentException('This withdrawal is not waiting for an admin decision.');
        }

        $last = $withdrawal->latestAttempt;
        if ($last && $last->isLive()) {
            throw new InvalidArgumentException('Waiting for '.$this->gatewayName($last->gateway_key).' to confirm the result of the last attempt.');
        }
        if ($last && $last->failure_class === FailureClass::ACCOUNT_DETAILS) {
            throw new InvalidArgumentException('The last attempt failed because of the account details. It cannot be retried through another gateway.');
        }

        $this->assertDestinationUsable($withdrawal);
        if (! array_key_exists($gatewayKey, $this->routeOptions($withdrawal))) {
            throw new InvalidArgumentException('That gateway is not verified for this destination.');
        }
        if ($last) {
            $check = $this->confirmNothingPaid($last);
            if (! $check['ok']) {
                throw new InvalidArgumentException($check['reason']);
            }
        }

        $quote = $this->checkFee($withdrawal, $gatewayKey);
        if ($quote === null) {
            throw new InvalidArgumentException('The fee on '.$this->gatewayName($gatewayKey).' is above the tolerance. The agent has been asked to accept the new fee or cancel.');
        }
        $this->assertBalance($withdrawal->fresh(), $gatewayKey, $quote);

        return $this->send($withdrawal->fresh(), $gatewayKey, WithdrawalPayoutAttempt::TRIGGER_ADMIN_RETRY, $adminId, $quote);
    }

    /** Admin chooses to refund instead of retrying. */
    public function refundToWallet(Withdrawal $withdrawal, int $adminId, ?string $note = null): void
    {
        $withdrawal = $withdrawal->fresh() ?? $withdrawal;
        if ($withdrawal->latestAttempt?->isLive()) {
            throw new InvalidArgumentException('Waiting for the provider to confirm the last attempt. Refunding now could pay twice.');
        }
        if ($withdrawal->isTerminal()) {
            throw new InvalidArgumentException('This withdrawal is already closed.');
        }

        $withdrawal->update(['provider_status' => 'REFUNDED']);
        $this->wallets->failWithdrawalPayout($withdrawal, $note ?: 'Refunded to wallet by admin');
        PaymentTimelineEvent::record($withdrawal, 'refunded', 'Refunded to wallet by admin', ['admin_id' => $adminId]);
    }

    // ---------------------------------------------------------------- agent fee confirmation

    public function agentAcceptFee(Withdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal) {
            $withdrawal = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if (! $withdrawal->awaitingAgentConfirmation()) {
                throw new InvalidArgumentException(__('This withdrawal is not waiting for your confirmation.'));
            }
            if ($withdrawal->confirmation_expires_at && $withdrawal->confirmation_expires_at->isPast()) {
                throw new InvalidArgumentException(__('This confirmation has expired.'));
            }

            $requote = (array) ($withdrawal->requote_snapshot ?? []);
            $providerFee = Money::round((string) ($requote['provider_fee'] ?? $withdrawal->provider_fee), 2);
            $fee = Money::add($providerFee, (string) $withdrawal->platform_fee, 2);
            $net = Money::sub((string) $withdrawal->amount, $fee, 2);
            if (! Money::isPositive($net, 2)) {
                throw new InvalidArgumentException(__('The new fee is larger than the withdrawal. Cancel it for a full refund.'));
            }

            $withdrawal->update([
                'provider_fee' => $providerFee,
                'fee_amount' => $fee,
                'net_amount' => $net,
                'absorbed_fee' => 0,
                'quote_snapshot' => $requote['quote'] ?? $withdrawal->quote_snapshot,
                'requote_snapshot' => $requote + ['accepted_at' => now()->toIso8601String()],
                'status' => 'pending',
                'internal_status' => 'pending_review',
                'confirmation_expires_at' => null,
            ]);
            PaymentTimelineEvent::record($withdrawal, 'fee_accepted', 'Agent accepted the new fee', ['provider_fee' => $providerFee]);
        });
    }

    public function agentCancel(Withdrawal $withdrawal): void
    {
        $withdrawal = $withdrawal->fresh() ?? $withdrawal;
        if (! $withdrawal->awaitingAgentConfirmation()) {
            throw new InvalidArgumentException(__('This withdrawal is not waiting for your confirmation.'));
        }

        $withdrawal->update(['provider_status' => 'CANCELLED']);
        $this->wallets->failWithdrawalPayout($withdrawal, 'Cancelled by agent after a fee change');
    }

    public function expireAgentConfirmation(Withdrawal $withdrawal): void
    {
        $withdrawal = $withdrawal->fresh() ?? $withdrawal;
        if (! $withdrawal->awaitingAgentConfirmation()
            || ! $withdrawal->confirmation_expires_at
            || $withdrawal->confirmation_expires_at->isFuture()) {
            return;
        }

        $withdrawal->update(['provider_status' => 'EXPIRED']);
        $this->wallets->failWithdrawalPayout($withdrawal, 'Fee confirmation expired');
    }

    // ---------------------------------------------------------------- status updates

    /** Webhook for an attempt: always re-verified with the same provider first. */
    public function syncAttempt(WithdrawalPayoutAttempt $attempt, PaymentEvent $event): string
    {
        if (! $attempt->isLive()) {
            if ($attempt->status === WithdrawalPayoutAttempt::SUCCEEDED && $event->failed()) {
                $this->alerts->disbursementReversed($attempt->reference, ['withdrawal_id' => $attempt->withdrawal_id], $attempt->gateway_key);
            }

            return 'unchanged';
        }

        return $this->reconcileAttempt($attempt);
    }

    /** Ask the provider about a live attempt by its reference and apply a final answer. */
    public function reconcileAttempt(WithdrawalPayoutAttempt $attempt): string
    {
        $attempt = $attempt->fresh() ?? $attempt;
        if (! $attempt->isLive()) {
            return $attempt->status;
        }

        $driver = $this->registry->payoutDriver($attempt->gateway_key);
        if (! $driver) {
            return 'no_driver';
        }

        try {
            $result = $driver->transferStatus($attempt->reference, $attempt->provider_reference);
        } catch (\Throwable $e) {
            Log::warning('payments.payout.status_failed', ['attempt' => $attempt->reference, 'error' => $e->getMessage()]);
            $attempt->touch();

            return 'error';
        }

        if ($result->status === PayoutResult::UNKNOWN
            || ($result->isLive() && $result->status === $attempt->status)) {
            $attempt->touch();

            return $attempt->status;
        }

        $this->applyResult($attempt, $result);

        return (string) ($attempt->fresh()?->status ?? $result->status);
    }

    // ---------------------------------------------------------------- internals

    private function assertDestinationUsable(Withdrawal $withdrawal): void
    {
        $account = $withdrawal->payoutAccount;
        if ($account && $account->isRejected()) {
            throw new InvalidArgumentException('The payout account was rejected. Refund or reject this withdrawal.');
        }
        if (! $withdrawal->destination_country && ! $withdrawal->account_number) {
            throw new InvalidArgumentException('This withdrawal has no payout destination.');
        }
    }

    /**
     * The requested gateway (must be on the route) or the first route gateway that hasn't
     * already confirmed "not created" for this withdrawal.
     */
    private function pickGateway(Withdrawal $withdrawal, ?string $requested): string
    {
        $route = $this->routeOptions($withdrawal);
        if ($route === []) {
            throw new InvalidArgumentException('No payout gateway is verified for '.$withdrawal->destinationLabel().'.');
        }

        if ($requested !== null) {
            if (! array_key_exists($requested, $route)) {
                throw new InvalidArgumentException('That gateway is not verified for this destination.');
            }

            return $requested;
        }

        $accepted = (array) ($withdrawal->requote_snapshot ?? []);
        if (! empty($accepted['accepted_at']) && array_key_exists((string) ($accepted['gateway_key'] ?? ''), $route)) {
            return (string) $accepted['gateway_key'];
        }

        $refused = $withdrawal->attempts()->where('status', WithdrawalPayoutAttempt::NOT_CREATED)->pluck('gateway_key')->all();
        foreach (array_keys($route) as $key) {
            if (! in_array($key, $refused, true)) {
                return $key;
            }
        }

        return (string) array_key_first($route);
    }

    /**
     * Re-quote on the chosen gateway. Returns the quote to send with, or null after moving the
     * withdrawal to awaiting_agent_confirmation. Throws when no fee can be determined.
     */
    private function checkFee(Withdrawal $withdrawal, string $gatewayKey, string $trigger = WithdrawalPayoutAttempt::TRIGGER_INITIAL): ?PayoutQuote
    {
        $check = $this->fees->requote($withdrawal, $gatewayKey);
        if ($check['quote'] === null) {
            throw new InvalidArgumentException('No fee quote is available from '.$this->gatewayName($gatewayKey).' for this destination right now. Try again later or choose another gateway.');
        }

        $snapshot = [
            'gateway_key' => $gatewayKey,
            'provider_fee' => $check['new_fee'],
            'stored_provider_fee' => (string) $withdrawal->provider_fee,
            'difference' => $check['difference'],
            'decision' => $check['decision'],
            'trigger' => $trigger,
            'quote' => $check['quote']->toArray(),
            'at' => now()->toIso8601String(),
        ];

        if ($check['decision'] === WithdrawalFeeService::DECISION_CONFIRM) {
            $withdrawal->update([
                'status' => 'processing',
                'internal_status' => Withdrawal::INTERNAL_AWAITING_AGENT_CONFIRMATION,
                'requote_snapshot' => $snapshot,
                'confirmation_expires_at' => now()->addDays((int) config('payments.payout.confirmation_expiry_days', 7)),
            ]);
            PaymentTimelineEvent::record($withdrawal, 'awaiting_agent_confirmation', 'Provider fee went up: waiting for the agent to accept or cancel', [
                'gateway' => $gatewayKey,
                'provider_fee' => $check['new_fee'],
            ]);

            return null;
        }

        $withdrawal->update([
            'requote_snapshot' => $snapshot,
            'absorbed_fee' => $check['decision'] === WithdrawalFeeService::DECISION_ABSORB ? $check['difference'] : 0,
        ]);

        return $check['quote'];
    }

    private function assertBalance(Withdrawal $withdrawal, string $gatewayKey, PayoutQuote $quote): void
    {
        $driver = $this->registry->payoutDriver($gatewayKey);
        try {
            $balance = $driver?->balance('USD');
        } catch (\Throwable) {
            $balance = null;
        }
        if ($balance === null) {
            return;
        }

        $needed = Money::add($withdrawal->netPayoutAmount(), $quote->providerFee, 2);
        if (Money::cmp($balance, $needed, 2) < 0) {
            throw new InvalidArgumentException(
                'Insufficient '.$this->gatewayName($gatewayKey).' balance: '.money($balance, 2).' available, about '.money($needed, 2).' needed. Fund it or choose another gateway.'
            );
        }
    }

    private function send(Withdrawal $withdrawal, string $gatewayKey, string $trigger, ?int $authorisedBy, ?PayoutQuote $quote): WithdrawalPayoutAttempt
    {
        PayoutGate::assertOpen();
        $currency = strtoupper((string) ($withdrawal->currency ?: Currency::current()->code));
        if (! PayoutGate::currencyAllowed($currency) || $currency !== Currency::current()->code) {
            throw new InvalidArgumentException('This withdrawal is in '.$currency.'. Gateway payouts only send '.PayoutGate::PAYOUT_CURRENCY.' wallet amounts.');
        }

        $driver = $this->registry->payoutDriver($gatewayKey)
            ?? throw new InvalidArgumentException('Unknown payout gateway.');
        $destination = PayoutAccountDetails::fromWithdrawal($withdrawal);

        try {
            $attempt = DB::transaction(function () use ($withdrawal, $gatewayKey, $trigger, $authorisedBy, $quote, $destination, $currency) {
                $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
                if ($locked->isTerminal()) {
                    throw new InvalidArgumentException('This withdrawal is already closed.');
                }
                if (WithdrawalPayoutAttempt::query()->where('withdrawal_id', $locked->id)->whereNotNull('live_flag')->exists()) {
                    throw new InvalidArgumentException('Another payout attempt for this withdrawal is still in progress.');
                }

                $no = (int) WithdrawalPayoutAttempt::query()->where('withdrawal_id', $locked->id)->max('attempt_no') + 1;
                $attempt = WithdrawalPayoutAttempt::create([
                    'withdrawal_id' => $locked->id,
                    'attempt_no' => $no,
                    'gateway_key' => $gatewayKey,
                    'reference' => WithdrawalPayoutAttempt::referenceFor($locked->id, $no),
                    'status' => WithdrawalPayoutAttempt::PENDING,
                    'trigger' => $trigger,
                    'amount' => $locked->netPayoutAmount(),
                    'request_snapshot' => ['destination' => $destination->masked(), 'amount' => $locked->netPayoutAmount(), 'currency' => $currency],
                    'quote_snapshot' => $quote?->toArray(),
                    'authorised_by' => $authorisedBy,
                    'authorised_at' => $authorisedBy ? now() : null,
                ]);

                $locked->update([
                    'provider' => $gatewayKey,
                    'provider_payout_reference' => $attempt->reference,
                    'status' => 'processing',
                    'internal_status' => 'processing',
                    'provider_status' => 'PENDING',
                ]);
                PaymentTimelineEvent::record($locked, 'sent_to_provider', 'Sent to '.$this->gatewayName($gatewayKey), [
                    'attempt' => $attempt->reference,
                    'trigger' => $trigger,
                    'authorised_by' => $authorisedBy,
                ]);

                return $attempt;
            });
        } catch (QueryException $e) {
            throw new InvalidArgumentException('Another payout attempt for this withdrawal is already in progress.', 0, $e);
        }

        $useQuote = $quote && $quote->gatewayKey === $gatewayKey && $quote->source === PayoutQuote::SOURCE_LIVE ? $quote : null;

        try {
            $result = $driver->initiateTransfer(new PayoutRequest(
                reference: $attempt->reference,
                amount: $attempt->amount,
                destination: $destination,
                narration: 'Withdrawal '.$withdrawal->reference,
                currency: $currency,
                quote: $useQuote,
            ));
        } catch (\Throwable $e) {
            Log::error('payments.payout.initiate_threw', ['attempt' => $attempt->reference, 'error' => $e->getMessage()]);
            $result = PayoutResult::unknown($e->getMessage());
        }

        $this->applyResult($attempt, $result);

        return $attempt->fresh() ?? $attempt;
    }

    private function applyResult(WithdrawalPayoutAttempt $attempt, PayoutResult $result): void
    {
        $withdrawal = $attempt->withdrawal;
        if (! $withdrawal) {
            return;
        }

        if ($result->beneficiaryIds !== [] && $withdrawal->payoutAccount) {
            $account = $withdrawal->payoutAccount;
            $account->update(['provider_beneficiary_ids' => array_replace((array) ($account->provider_beneficiary_ids ?? []), $result->beneficiaryIds)]);
        }

        $common = array_filter([
            'provider_reference' => $result->providerReference ?: $attempt->provider_reference,
            'response_snapshot' => $result->raw !== [] ? $result->raw : $attempt->response_snapshot,
        ], fn ($v) => $v !== null);

        if ($result->isLive()) {
            $attempt->fill($common + ['status' => $result->status])->save();
            $withdrawal->update(['provider_status' => strtoupper($result->status)]);
            if ($result->status === PayoutResult::UNKNOWN) {
                PaymentTimelineEvent::record($withdrawal, 'provider_unknown', 'Waiting for '.$this->gatewayName($attempt->gateway_key).' to confirm the result', [
                    'attempt' => $attempt->reference,
                ]);
            }

            return;
        }

        $driver = $this->registry->payoutDriver($attempt->gateway_key);
        $class = $result->status === PayoutResult::SUCCEEDED
            ? null
            : ($result->failureClass ?? $driver?->failureClass($result->failureCode) ?? FailureClass::OTHER);
        if ($result->status === PayoutResult::NOT_CREATED) {
            $class = FailureClass::NOT_CREATED;
        }

        // Close the live attempt exactly once, even with concurrent webhooks and reconciles.
        $closed = WithdrawalPayoutAttempt::query()
            ->whereKey($attempt->id)
            ->whereNotNull('live_flag')
            ->update([
                'status' => $result->status,
                'live_flag' => null,
                'provider_reference' => $common['provider_reference'] ?? null,
                'response_snapshot' => isset($common['response_snapshot']) ? json_encode($common['response_snapshot']) : null,
                'failure_code' => $result->failureCode,
                'failure_reason' => $result->failureReason ? mb_substr($result->failureReason, 0, 2000) : null,
                'failure_class' => $class,
                'recoverable' => in_array($class, [FailureClass::RECOVERABLE, FailureClass::OTHER], true),
                'return_charge' => $result->returnCharge,
                'provider_fee_charged' => $result->fee !== null && is_numeric($result->fee) ? Money::round($result->fee, 4) : null,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
        if ($closed !== 1) {
            return;
        }
        $attempt->refresh();

        if ($result->status === PayoutResult::SUCCEEDED) {
            $this->onSucceeded($withdrawal->fresh(), $attempt);

            return;
        }

        if ($result->status === PayoutResult::FAILED && $driver instanceof ReclaimsFailedPayouts) {
            $this->reclaim($attempt);
        }

        $this->onClosedWithoutPayment($withdrawal->fresh(), $attempt, (string) $class);
    }

    public const RECLAIM_WINDOW_DAYS = 3;

    /**
     * Move funds left on a recipient account after a failed payout back to the platform.
     * Failed funds can come back days later, so the reclaim stays pending for a window and
     * reconcile repeats it. Skipped while the agent has another live payout on the same
     * gateway, because that payout's funding sits on the same account.
     */
    public function reclaim(WithdrawalPayoutAttempt $attempt): bool
    {
        $driver = $this->registry->payoutDriver($attempt->gateway_key);
        $withdrawal = $attempt->withdrawal;
        $closedAt = $attempt->resolved_at ?? $attempt->updated_at;
        if (! $driver instanceof ReclaimsFailedPayouts || ! $withdrawal
            || ($closedAt && $closedAt->lt(now()->subDays(self::RECLAIM_WINDOW_DAYS)))) {
            $attempt->update(['reclaim_pending' => false]);

            return true;
        }

        $attempt->update(['reclaim_pending' => true, 'last_checked_at' => now()]);

        $busy = WithdrawalPayoutAttempt::query()
            ->where('gateway_key', $attempt->gateway_key)
            ->whereNotNull('live_flag')
            ->whereHas('withdrawal', fn ($q) => $q->where('user_id', $withdrawal->user_id))
            ->exists();
        if ($busy) {
            return false;
        }

        try {
            return $driver->reclaimFailedPayout($attempt->reference, PayoutAccountDetails::fromWithdrawal($withdrawal));
        } catch (\Throwable $e) {
            Log::warning('payments.payout.reclaim_failed', ['attempt' => $attempt->reference, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function onSucceeded(Withdrawal $withdrawal, WithdrawalPayoutAttempt $attempt): void
    {
        $withdrawal->update([
            'provider' => $attempt->gateway_key,
            'provider_payout_reference' => $attempt->reference,
            'provider_transfer_id' => $attempt->provider_reference,
            'provider_status' => 'SUCCEEDED',
        ]);

        if ($withdrawal->status !== 'processing') {
            $this->wallets->markWithdrawalProcessing($withdrawal, 'SUCCEEDED');
        }
        $this->wallets->completeWithdrawalPayout($withdrawal->fresh());

        if ($withdrawal->payoutAccount) {
            $this->accounts->markResolvedByPayout($withdrawal->payoutAccount, $attempt->gateway_key);
        }
    }

    private function onClosedWithoutPayment(Withdrawal $withdrawal, WithdrawalPayoutAttempt $attempt, string $class): void
    {
        $withdrawal->update(['provider_status' => strtoupper($attempt->status)]);
        PaymentTimelineEvent::record($withdrawal, 'attempt_closed', $this->gatewayName($attempt->gateway_key).' did not pay this out', [
            'attempt' => $attempt->reference,
            'status' => $attempt->status,
            'code' => $attempt->failure_code,
            'class' => $class,
        ]);

        if ($class === FailureClass::ACCOUNT_DETAILS) {
            $this->wallets->failWithdrawalPayout($withdrawal, 'Payout returned: '.($attempt->failure_reason ?: $attempt->failure_code ?: 'account details'));
            $this->applyBounceFee($withdrawal->fresh(), $attempt);
            if ($withdrawal->payoutAccount) {
                $this->accounts->reject($withdrawal->payoutAccount, $attempt->failure_reason ?: 'Payout returned for incorrect account details');
            }

            return;
        }

        if ($class === FailureClass::NOT_CREATED && $this->tryAutomaticFallback($withdrawal, $attempt)) {
            return;
        }

        $withdrawal->update(['status' => 'processing', 'internal_status' => Withdrawal::INTERNAL_NEEDS_ADMIN_DECISION]);
        $this->alerts->disbursementFailed($attempt->reference, strtoupper($attempt->status).($attempt->failure_code ? ' '.$attempt->failure_code : ''), [
            'withdrawal_id' => $withdrawal->id,
            'class' => $class,
        ], $attempt->gateway_key);
    }

    /** One automatic attempt on the next verified gateway after a confirmed "not created". */
    private function tryAutomaticFallback(Withdrawal $withdrawal, WithdrawalPayoutAttempt $attempt): bool
    {
        if ($withdrawal->attempts()->where('trigger', WithdrawalPayoutAttempt::TRIGGER_AUTOMATIC_FALLBACK)->exists()) {
            return false;
        }

        $tried = $withdrawal->attempts()->pluck('gateway_key')->all();
        $next = null;
        foreach (array_keys($this->routeOptions($withdrawal)) as $key) {
            if (! in_array($key, $tried, true)) {
                $next = $key;
                break;
            }
        }
        if ($next === null) {
            return false;
        }

        $check = $this->confirmNothingPaid($attempt);
        if (! $check['ok']) {
            Log::warning('payments.payout.fallback_unconfirmed', ['withdrawal_id' => $withdrawal->id, 'attempt' => $attempt->reference, 'reason' => $check['reason']]);
            PaymentTimelineEvent::record($withdrawal, 'fallback_held', 'Automatic fallback held: '.$check['reason'], ['attempt' => $attempt->reference]);

            return false;
        }

        try {
            $quote = $this->checkFee($withdrawal, $next, WithdrawalPayoutAttempt::TRIGGER_AUTOMATIC_FALLBACK);
            if ($quote === null) {
                return true;
            }
            $this->assertBalance($withdrawal->fresh(), $next, $quote);
            $this->send($withdrawal->fresh(), $next, WithdrawalPayoutAttempt::TRIGGER_AUTOMATIC_FALLBACK, null, $quote);
        } catch (InvalidArgumentException $e) {
            Log::warning('payments.payout.fallback_skipped', ['withdrawal_id' => $withdrawal->id, 'gateway' => $next, 'reason' => $e->getMessage()]);

            return false;
        }

        return true;
    }

    /**
     * Before sending the same withdrawal anywhere else, ask the gateway of the closed attempt
     * whether a payout exists for its reference. Only a confirmed failure or a confirmed
     * "no such payout" allows another attempt.
     *
     * @return array{ok: bool, reason: string}
     */
    private function confirmNothingPaid(WithdrawalPayoutAttempt $attempt): array
    {
        $name = $this->gatewayName($attempt->gateway_key);
        $driver = $this->registry->payoutDriver($attempt->gateway_key);
        if (! $driver) {
            return ['ok' => false, 'reason' => $name.' is no longer installed, so the last attempt cannot be checked.'];
        }

        try {
            $result = $driver->transferStatus($attempt->reference, $attempt->provider_reference);
        } catch (\Throwable $e) {
            Log::warning('payments.payout.lookup_failed', ['attempt' => $attempt->reference, 'error' => $e->getMessage()]);

            return ['ok' => false, 'reason' => 'Could not check the last attempt with '.$name.'. Try again in a few minutes.'];
        }

        if ($result->confirmedAbsent() || in_array($result->status, [PayoutResult::FAILED, PayoutResult::NOT_CREATED], true)) {
            return ['ok' => true, 'reason' => ''];
        }

        if ($result->status === PayoutResult::SUCCEEDED) {
            $this->alerts->closedAttemptPaid($attempt->reference, ['withdrawal_id' => $attempt->withdrawal_id], $attempt->gateway_key);

            return ['ok' => false, 'reason' => $name.' reports the last attempt as paid. Check it before sending again.'];
        }

        return ['ok' => false, 'reason' => $name.' could not confirm that the last attempt was not paid ('.$result->status.'). Try again later.'];
    }

    /**
     * Bounce fee only when: the setting is above $0, the failure is a documented account-detail code,
     * the agent accepted a disclosure covering the current fee for this account, and the provider
     * reported a return charge. Capped at the disclosed fee, the return charge and the withdrawal.
     */
    private function applyBounceFee(Withdrawal $withdrawal, WithdrawalPayoutAttempt $attempt): void
    {
        $setting = PayoutAccountService::bounceFee();
        if (! Money::isPositive($setting, 2) || $attempt->failure_class !== FailureClass::ACCOUNT_DETAILS) {
            return;
        }

        $account = $withdrawal->payoutAccount;
        $disclosed = $account instanceof PayoutAccount ? $account->acceptedBounceFee() : '0';
        if (! Money::isPositive($disclosed, 2) || Money::cmp($disclosed, $setting, 2) < 0) {
            return;
        }

        $returnCharge = (string) ($attempt->return_charge ?? '0');
        if (! Money::isPositive($returnCharge, 2)) {
            return;
        }

        $charge = $setting;
        foreach ([$returnCharge, (string) $withdrawal->amount] as $cap) {
            if (Money::cmp($cap, $charge, 2) < 0) {
                $charge = $cap;
            }
        }

        $this->wallets->chargeBounceFee($withdrawal, Money::round($charge, 2), (string) $attempt->failure_code);
    }

    private function gatewayName(?string $key): string
    {
        return $key && $this->registry->has($key) ? $this->registry->driver($key)->displayName() : (string) ($key ?: 'the provider');
    }
}
