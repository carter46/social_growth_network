<?php

namespace App\Modules\Wallet\Services;

use App\Events\WalletDepositFailed;
use App\Models\PaymentTimelineEvent;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WalletFunding;
use App\Modules\Wallet\Payments\Data\CheckoutRequest;
use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use App\Modules\Wallet\Payments\Exceptions\GatewayUnavailable;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Services\Notifications\AdminPaymentAlertNotifier;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creator wallet deposits through a hosted gateway checkout (Bachs or Flutterwave).
 * The creator picks the gateway; the funding remembers it, and only that gateway can confirm it.
 */
class DepositCheckoutService
{
    /** A payment above deposit_max_amount: recorded but not credited until an admin verifies it. */
    public const HELD_OVER_LIMIT = 'held_over_limit';

    /** Marked failed, then the provider reported it paid. Waits for an admin to credit it. */
    public const PAID_AFTER_FAILURE = 'paid_after_failure';

    public function __construct(
        private GatewayRegistry $gateways,
        private WalletService $wallets,
    ) {}

    public function gatewayEnabled(): bool
    {
        return $this->gateways->hasCollectionGateway();
    }

    /** @return array<string, string> key => display name, in admin order */
    public function availableGateways(): array
    {
        return collect($this->gateways->availableCollectionGateways())
            ->map(fn ($driver) => $driver->displayName())
            ->all();
    }

    public function defaultGatewayKey(): ?string
    {
        return $this->gateways->defaultCollectionKey();
    }

    public function assertDepositKyc(User $user): void
    {
        if ($user->hasRole('agent')) {
            throw new InvalidArgumentException('Agents cannot deposit funds. Earnings are credited after task verification.');
        }

        if (! SystemSetting::kycRequired()) {
            return;
        }

        $minLevel = SystemSetting::kycRequiredLevel('deposit', 1);
        if (! $user->hasApprovedKyc($minLevel)) {
            throw new InvalidArgumentException(
                'Complete KYC verification (level '.$minLevel.') before depositing.'
            );
        }
    }

    public function startCheckout(User $user, string|float $amount, string $callbackUrl, ?string $gatewayKey = null): WalletFunding
    {
        if ($user->hasRole('agent')) {
            throw new InvalidArgumentException('Agents cannot deposit funds.');
        }

        if (! $user->wallet) {
            throw new InvalidArgumentException('Create a wallet first.');
        }

        $this->assertDepositKyc($user);

        try {
            $driver = $this->gateways->collection($gatewayKey);
        } catch (GatewayUnavailable $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        $amount = Money::round((string) $amount, 2);
        $depositMin = Money::round((string) SystemSetting::get('deposit_min_amount', 1), 2);
        $depositMax = Money::round((string) SystemSetting::get('deposit_max_amount', 5000), 2);
        if (Money::cmp($amount, $depositMin, 2) < 0) {
            throw new InvalidArgumentException('Amount is below the minimum deposit of '.money($depositMin).'.');
        }
        if (Money::cmp($amount, $depositMax, 2) > 0) {
            throw new InvalidArgumentException('Amount is above the maximum deposit of '.money($depositMax).'.');
        }

        $key = $driver->key();
        $method = $key.'_checkout';
        $currency = (string) config('platform.currency', 'USD');

        $open = WalletFunding::query()
            ->where('user_id', $user->id)
            ->where('method', $method)
            ->whereIn('status', ['pending', 'processing'])
            ->where('amount', $amount)
            ->whereNotNull('checkout_url')
            ->where('checkout_expires_at', '>', now()->addMinutes(2))
            ->latest('id')
            ->first();
        if ($open) {
            return $open;
        }

        $reference = 'DEP-'.strtoupper((string) Str::ulid());

        $environment = method_exists($driver, 'environment') ? $driver->environment() : 'sandbox';
        $funding = DB::transaction(function () use ($user, $amount, $currency, $method, $key, $reference, $environment) {
            $funding = WalletFunding::create([
                'user_id' => $user->id,
                'wallet_id' => $user->wallet->id,
                'method' => $method,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'internal_status' => 'pending',
                'provider' => $key,
                'provider_payment_reference' => $reference,
                'reference' => $reference,
                'metadata' => ['environment' => $environment],
            ]);

            PaymentTimelineEvent::record($funding, 'created', 'Created');

            return $funding;
        });

        $separator = str_contains($callbackUrl, '?') ? '&' : '?';
        $returnUrl = $callbackUrl.$separator.'reference='.urlencode($reference);
        $minutes = (int) config('payments.checkout_expiry_minutes', 40);

        try {
            $session = $driver->initializeCheckout(new CheckoutRequest(
                amount: $amount,
                currency: $currency,
                reference: $reference,
                customerEmail: (string) $user->email,
                customerName: (string) $user->name,
                callbackUrl: $returnUrl,
                description: 'Wallet deposit '.$reference,
                cancelUrl: $returnUrl,
                expiresInMinutes: $minutes,
                metadata: ['type' => 'deposit', 'funding_id' => $funding->id],
            ));
        } catch (GatewayException $e) {
            Log::warning('deposit.checkout_failed', ['funding_id' => $funding->id, 'gateway' => $key, 'code' => $e->providerCode, 'error' => $e->getMessage()]);
            $funding->update(['status' => 'failed', 'internal_status' => 'failed', 'provider_status' => 'INIT_FAILED']);
            PaymentTimelineEvent::record($funding, 'failed', 'Could not open '.$driver->displayName().' checkout');
            WalletDepositFailed::dispatch(
                (int) $user->id,
                (int) $funding->id,
                (float) $funding->amount,
                __('We could not open the payment page.'),
                (string) $funding->currency,
            );

            throw new InvalidArgumentException('We could not open '.$driver->displayName().' right now. Try again, or choose another payment option.');
        }

        $funding->update([
            'checkout_url' => $session->checkoutUrl,
            'provider_transaction_reference' => $session->providerReference,
            'checkout_expires_at' => $session->expiresAt ?? now()->addMinutes($minutes),
            'internal_status' => 'processing',
            'status' => 'processing',
            'provider_status' => 'PENDING',
        ]);

        PaymentTimelineEvent::record($funding, 'sent_to_provider', 'Sent to '.$driver->displayName());

        return $funding->fresh();
    }

    /**
     * Ask the funding's own gateway whether it was paid, and credit the wallet once.
     *
     * @param  bool  $releaseHeld  Only an admin action may credit a payment held for being over the deposit limit.
     * @param  bool  $adminCredit  Only an admin action may credit a deposit marked failed; it is still re-verified first.
     */
    public function completeFromReturn(string $paymentReference, bool $releaseHeld = false, bool $adminCredit = false): WalletFunding
    {
        $funding = WalletFunding::query()
            ->where('provider_payment_reference', $paymentReference)
            ->firstOrFail();

        if ($funding->status === 'approved' || $funding->internal_status === 'completed') {
            return $funding;
        }

        if ($funding->internal_status === self::HELD_OVER_LIMIT && ! $releaseHeld) {
            return $funding;
        }

        $driver = $this->gateways->collectionDriver((string) $funding->provider);
        if (! $driver) {
            throw new InvalidArgumentException('This deposit was made with a payment provider that is no longer available. An admin will review it.');
        }

        $event = $driver->verify($paymentReference, $funding->provider_transaction_reference);

        $funding->update([
            'provider_status' => strtoupper($event->status),
            'provider_transaction_reference' => $event->providerReference ?? $funding->provider_transaction_reference,
        ]);

        if (! $event->succeeded()) {
            if ($event->failed() && in_array($funding->status, ['pending', 'processing'], true)) {
                $funding->update(['status' => 'failed', 'internal_status' => 'failed']);
                PaymentTimelineEvent::record($funding, 'failed', 'Payment not completed', ['code' => $event->failureCode]);
                WalletDepositFailed::dispatch(
                    (int) $funding->user_id,
                    (int) $funding->id,
                    (float) $funding->amount,
                    __('Your card payment was declined or cancelled.'),
                    (string) $funding->currency,
                );
            }

            return $funding->fresh();
        }

        $currencyMatches = strtoupper((string) $event->currency) === strtoupper((string) $funding->currency);
        if (! $currencyMatches || Money::cmp((string) $event->amount, (string) $funding->amount, 2) !== 0) {
            app(AdminPaymentAlertNotifier::class)->amountMismatch(
                'Deposit '.$funding->reference,
                money($funding->amount),
                trim(($event->currency ?? '').' '.($event->amount ?? '?')),
                ['funding_id' => $funding->id],
                (string) $funding->provider,
            );
            PaymentTimelineEvent::record($funding, 'amount_mismatch', 'Paid amount does not match', [
                'paid' => $event->amount, 'currency' => $event->currency,
            ]);

            throw new InvalidArgumentException('Paid amount does not match the deposit amount. An admin will review it.');
        }

        $owner = User::query()->find($funding->user_id);
        if ($owner?->hasRole('agent')) {
            throw new InvalidArgumentException('Agents cannot receive deposit credits.');
        }

        $markedFailed = in_array($funding->status, ['failed', 'rejected', 'reversed'], true)
            || in_array($funding->internal_status, ['failed', self::PAID_AFTER_FAILURE], true);
        if ($markedFailed) {
            if (! $adminCredit || in_array($funding->status, ['rejected', 'reversed'], true)) {
                if ($funding->internal_status !== self::PAID_AFTER_FAILURE) {
                    $funding->update(['internal_status' => self::PAID_AFTER_FAILURE]);
                    PaymentTimelineEvent::record($funding, 'paid_after_failure', $driver->displayName().' reports this deposit paid after it was marked failed');
                }
                app(AdminPaymentAlertNotifier::class)->paidAfterFailure(
                    (string) $funding->reference,
                    (string) $funding->amount,
                    ['funding_id' => $funding->id],
                    (string) $funding->provider,
                );

                return $funding->fresh();
            }

            $funding->update(['status' => 'processing', 'internal_status' => 'processing']);
            PaymentTimelineEvent::record($funding, 'admin_credit', 'Admin credited after the provider confirmed payment');
        }

        PaymentTimelineEvent::record($funding, 'verified', 'Payment verified with '.$driver->displayName());
        $this->wallets->creditFromFunding($funding);

        return $funding->fresh();
    }
}
