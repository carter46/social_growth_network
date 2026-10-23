<?php

namespace App\Modules\Wallet\Payments;

use App\Jobs\ProcessPaymentWebhook;
use App\Models\Order;
use App\Models\PaymentTimelineEvent;
use App\Models\PaymentWebhook;
use App\Models\WalletFunding;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Catalog\Services\PlatformCheckoutService;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use App\Modules\Wallet\Payments\Data\PaymentEvent;
use App\Modules\Wallet\Payments\Exceptions\InvalidWebhookSignature;
use App\Modules\Wallet\Services\DepositCheckoutService;
use App\Modules\Wallet\Services\WithdrawalPayoutService;
use App\Services\Notifications\AdminPaymentAlertNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives /webhooks/payments/{provider}: verify, store once, then match the event to a
 * deposit, order or payout attempt. Events are only applied to records created by the
 * same provider, and every state change is re-verified with the provider first.
 */
class PaymentEventProcessor
{
    /** Failed webhooks are retried with backoff (5, 10, 20, 40 minutes), then an admin is alerted. */
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private GatewayRegistry $registry,
        private AdminPaymentAlertNotifier $alerts,
    ) {}

    /**
     * @return array{status: int, webhook: ?PaymentWebhook}
     */
    public function receive(string $provider, Request $request): array
    {
        if (! $this->registry->has($provider)) {
            return ['status' => 404, 'webhook' => null];
        }

        $driver = $this->registry->driver($provider);
        $raw = $request->getContent();
        $payload = json_decode($raw, true) ?: [];

        try {
            $event = $driver->parseWebhook($request);
        } catch (InvalidWebhookSignature $e) {
            Log::warning('payments.webhook.bad_signature', ['provider' => $provider, 'ip' => $request->ip()]);
            $this->store($provider, 'invalid:'.sha1($raw), (string) ($payload['type'] ?? $payload['event'] ?? 'unknown'), $payload, $request, false, 'ignored', $e->getMessage());

            return ['status' => 401, 'webhook' => null];
        }

        $key = $event->eventId ? 'evt:'.$event->eventId : 'body:'.sha1($raw);
        $existing = PaymentWebhook::query()->where('provider', $provider)->where('idempotency_key', $key)->first();
        if ($existing) {
            if ($existing->status === 'failed') {
                $this->retry($existing);
            }

            return ['status' => 200, 'webhook' => $existing->fresh()];
        }

        $webhook = $this->store($provider, $key, (string) ($payload['type'] ?? $payload['event'] ?? $event->type), $payload, $request, true, 'received');
        if (! $webhook) {
            return ['status' => 200, 'webhook' => PaymentWebhook::query()->where('provider', $provider)->where('idempotency_key', $key)->first()];
        }

        // Process inline. Shared hosting often has QUEUE_CONNECTION=database with no
        // worker — a queued job would leave paid checkouts stuck on "processing" while
        // Bachs still shows the webhook delivery as successful (HTTP 200).
        try {
            ProcessPaymentWebhook::dispatchSync($webhook->id);
        } catch (\Throwable $e) {
            Log::error('payments.webhook.sync_failed', [
                'webhook_id' => $webhook->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }

        return ['status' => 200, 'webhook' => $webhook->fresh()];
    }

    public function process(PaymentWebhook $webhook): void
    {
        if (in_array($webhook->status, ['processed', 'ignored'], true)) {
            return;
        }

        $provider = (string) $webhook->provider;
        if (! $this->registry->has($provider)) {
            $webhook->update(['status' => 'ignored', 'error' => 'Unknown provider', 'processed_at' => now()]);

            return;
        }

        try {
            $event = $this->registry->driver($provider)->normalizeWebhookPayload((array) $webhook->payload);

            $outcome = match ($event->type) {
                PaymentEvent::TYPE_COLLECTION => $this->handleCollection($provider, $event, $webhook),
                PaymentEvent::TYPE_PAYOUT => $this->handlePayout($provider, $event, $webhook),
                PaymentEvent::TYPE_CONNECT => $provider === BachsConnectService::PROVIDER
                    ? app(BachsConnectService::class)->applyEvent($event)
                    : 'ignored',
                default => 'ignored',
            };

            $webhook->update([
                'status' => $outcome === 'ignored' ? 'ignored' : 'processed',
                'processed_at' => now(),
                'error' => null,
                'next_retry_at' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('payments.webhook.failed', ['webhook_id' => $webhook->id, 'provider' => $provider, 'error' => $e->getMessage()]);
            $attempts = (int) $webhook->attempts + 1;
            $webhook->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'processed_at' => now(),
                'attempts' => $attempts,
                'next_retry_at' => $attempts < self::MAX_ATTEMPTS ? now()->addMinutes(5 * (2 ** ($attempts - 1))) : null,
            ]);
            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->alerts->webhookGaveUp($webhook->id, $provider, (string) $webhook->event, $e->getMessage());
            }
            throw $e;
        }
    }

    /** Process a failed webhook again (provider redelivery or reconcile backoff). */
    public function retry(PaymentWebhook $webhook): void
    {
        if ($webhook->status !== 'failed') {
            return;
        }

        try {
            ProcessPaymentWebhook::dispatchSync($webhook->id);
        } catch (\Throwable $e) {
            Log::warning('payments.webhook.retry_failed', ['webhook_id' => $webhook->id, 'error' => $e->getMessage()]);
        }
    }

    private function handleCollection(string $provider, PaymentEvent $event, PaymentWebhook $webhook): string
    {
        $reference = (string) $event->reference;
        if ($reference === '' && filled($event->providerReference)) {
            $reference = (string) (
                WalletFunding::query()->where('provider', $provider)->where('provider_transaction_reference', $event->providerReference)->value('provider_payment_reference')
                ?? Order::query()->where('payment_provider', $provider)->where('provider_transaction_reference', $event->providerReference)->value('provider_payment_reference')
                ?? ''
            );
        }
        if ($reference === '') {
            return 'ignored';
        }

        $funding = WalletFunding::query()->where('provider_payment_reference', $reference)->first();
        if ($funding) {
            if ((string) $funding->provider !== $provider) {
                Log::warning('payments.webhook.provider_mismatch', ['funding_id' => $funding->id, 'expected' => $funding->provider, 'got' => $provider]);

                return 'ignored';
            }

            PaymentTimelineEvent::record($funding, 'webhook_received', 'Webhook received', ['webhook_id' => $webhook->id, 'provider' => $provider]);

            try {
                app(DepositCheckoutService::class)->completeFromReturn($reference);
            } catch (\InvalidArgumentException $e) {
                Log::warning('payments.webhook.funding_not_credited', ['funding_id' => $funding->id, 'reason' => $e->getMessage()]);
            }

            return 'processed';
        }

        $order = Order::query()
            ->where('payment_method', 'gateway')
            ->where(function ($q) use ($reference, $event) {
                $q->where('provider_payment_reference', $reference);
                if (filled($event->providerReference)) {
                    $q->orWhere('provider_transaction_reference', $event->providerReference);
                }
            })
            ->first();
        if ($order) {
            if ((string) $order->payment_provider !== $provider) {
                Log::warning('payments.webhook.provider_mismatch', ['order_id' => $order->id, 'expected' => $order->payment_provider, 'got' => $provider]);

                return 'ignored';
            }

            try {
                app(PlatformCheckoutService::class)->confirmGatewayOrder($order);
            } catch (\InvalidArgumentException $e) {
                Log::warning('payments.webhook.order_not_confirmed', ['order_id' => $order->id, 'reason' => $e->getMessage()]);
            }

            return 'processed';
        }

        $replaced = $this->orderWithReplacedReference($provider, $reference);
        if ($replaced) {
            if ($event->succeeded()) {
                Log::warning('payments.webhook.replaced_reference_paid', ['order_id' => $replaced->id, 'provider' => $provider, 'reference' => $reference]);
                $this->alerts->paymentOnReplacedReference($reference, (int) $replaced->id, ['webhook_id' => $webhook->id], $provider);
            }

            return 'processed';
        }

        if ($event->succeeded()) {
            $this->alerts->gatewayUnmatched($reference, ['webhook_id' => $webhook->id], $provider);
        }

        return 'ignored';
    }

    private function orderWithReplacedReference(string $provider, string $reference): ?Order
    {
        return Order::query()
            ->where('payment_method', 'gateway')
            ->where('payment_metadata', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $reference).'%')
            ->limit(5)
            ->get()
            ->first(fn (Order $order) => collect((array) data_get($order->payment_metadata, 'replaced_references', []))
                ->contains(fn ($row) => is_array($row) && ($row['reference'] ?? null) === $reference && ($row['provider'] ?? null) === $provider));
    }

    private function handlePayout(string $provider, PaymentEvent $event, PaymentWebhook $webhook): string
    {
        $attempt = null;
        if (filled($event->reference)) {
            $attempt = WithdrawalPayoutAttempt::query()
                ->where('gateway_key', $provider)
                ->where('reference', $event->reference)
                ->first();
        }
        if (! $attempt && filled($event->providerReference)) {
            $attempt = WithdrawalPayoutAttempt::query()
                ->where('gateway_key', $provider)
                ->where('provider_reference', $event->providerReference)
                ->first();
        }

        if (! $attempt) {
            Log::info('payments.webhook.payout_unmatched', ['provider' => $provider, 'reference' => $event->reference]);

            return 'ignored';
        }

        $withdrawal = $attempt->withdrawal;
        if ($withdrawal) {
            PaymentTimelineEvent::record($withdrawal, 'webhook_received', 'Webhook received', [
                'webhook_id' => $webhook->id,
                'provider' => $provider,
                'attempt' => $attempt->reference,
            ]);
        }

        app(WithdrawalPayoutService::class)->syncAttempt($attempt, $event);

        return 'processed';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(
        string $provider,
        string $key,
        string $event,
        array $payload,
        Request $request,
        bool $signatureValid,
        string $status,
        ?string $error = null,
    ): ?PaymentWebhook {
        try {
            return PaymentWebhook::create([
                'provider' => $provider,
                'event' => mb_substr($event, 0, 80),
                'payload' => $payload,
                'headers' => ['ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 191)],
                'signature_valid' => $signatureValid,
                'idempotency_key' => mb_substr($key, 0, 191),
                'status' => $status,
                'error' => $error,
                'received_at' => now(),
                'processed_at' => $status === 'ignored' ? now() : null,
            ]);
        } catch (QueryException) {
            // Duplicate delivery raced us to the unique (provider, idempotency_key) index.
            return null;
        }
    }
}
