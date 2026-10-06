<?php

namespace App\Modules\Wallet\Payments\Monnify;

use App\Jobs\ProcessMonnifyWebhook;
use App\Models\Order;
use App\Models\PaymentTimelineEvent;
use App\Models\PaymentWebhook;
use App\Models\WalletFunding;
use App\Models\Withdrawal;
use App\Modules\Catalog\Services\PlatformCheckoutService;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Notifications\AdminPaymentAlertNotifier;
use Illuminate\Support\Facades\Log;

class MonnifyWebhookProcessor
{
    public const DEFAULT_ALLOWED_IPS = ['35.242.133.146'];

    public function __construct(
        private MonnifyClient $client,
        private MonnifyPaymentRail $rail,
        private WalletService $wallets,
        private AdminPaymentAlertNotifier $paymentAlerts,
    ) {}

    /**
     * Persist webhook and return quickly. Processing is queued.
     */
    public function receive(string $rawBody, array $headers, ?string $ip): PaymentWebhook
    {
        $payload = json_decode($rawBody, true) ?: [];
        $signature = $headers['monnify-signature'][0]
            ?? $headers['Monnify-Signature'][0]
            ?? $headers['monnify-signature']
            ?? $headers['Monnify-Signature']
            ?? null;

        if (is_array($signature)) {
            $signature = $signature[0] ?? null;
        }

        $env = (string) ($this->client->provider()->meta['environment'] ?? 'sandbox');
        $isLive = $env === 'live';

        $signatureValid = null;
        if ($isLive) {
            $signatureValid = is_string($signature) && $this->client->verifySignature($rawBody, $signature);
        }

        $ipAllowed = $this->isIpAllowed($ip, $isLive);

        $eventType = (string) ($payload['eventType'] ?? $payload['eventData']['eventType'] ?? 'unknown');
        $eventData = $payload['eventData'] ?? $payload;
        $idempotency = (string) (
            $eventData['transactionReference']
            ?? $eventData['paymentReference']
            ?? $eventData['reference']
            ?? md5($rawBody)
        );

        $existing = PaymentWebhook::query()
            ->where('provider', 'monnify')
            ->where('idempotency_key', $eventType.':'.$idempotency)
            ->first();

        if ($existing) {
            return $existing;
        }

        $webhook = PaymentWebhook::create([
            'provider' => 'monnify',
            'event' => $eventType,
            'payload' => $payload,
            'headers' => [
                'monnify-signature' => $signature,
                'ip' => $ip,
            ],
            'signature_valid' => $signatureValid,
            'idempotency_key' => $eventType.':'.$idempotency,
            'status' => 'received',
            'received_at' => now(),
        ]);

        if ($isLive && ! $ipAllowed) {
            $webhook->update([
                'status' => 'ignored',
                'error' => 'IP not allowlisted: '.($ip ?: 'unknown'),
                'processed_at' => now(),
            ]);

            Log::warning('Monnify webhook rejected: IP not allowlisted', [
                'webhook_id' => $webhook->id,
                'ip' => $ip,
            ]);

            return $webhook;
        }

        if ($isLive && $signatureValid === false) {
            $webhook->update([
                'status' => 'ignored',
                'error' => 'Invalid monnify-signature',
                'processed_at' => now(),
            ]);

            return $webhook;
        }

        ProcessMonnifyWebhook::dispatch($webhook->id);

        return $webhook;
    }

    public function process(PaymentWebhook $webhook): void
    {
        if (in_array($webhook->status, ['processed', 'ignored'], true)) {
            return;
        }

        try {
            $payload = $webhook->payload ?? [];
            $eventType = strtoupper((string) ($webhook->event ?? ''));
            $eventData = $payload['eventData'] ?? $payload;

            if (str_contains($eventType, 'COLLECTION') || str_contains($eventType, 'SUCCESSFUL_TRANSACTION') || isset($eventData['paymentReference'])) {
                if ($this->looksLikeCollection($eventData, $eventType)) {
                    $this->handleCollection($webhook, $eventData);
                }
            }

            if (str_contains($eventType, 'DISBURSEMENT') || isset($eventData['reference'])) {
                if ($this->looksLikeDisbursement($eventData, $eventType)) {
                    $this->handleDisbursement($webhook, $eventData, $eventType);
                }
            }

            $webhook->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Monnify webhook processing failed', [
                'webhook_id' => $webhook->id,
                'error' => $e->getMessage(),
            ]);
            $webhook->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'processed_at' => now(),
            ]);
            throw $e;
        }
    }

    /**
     * @return list<string>
     */
    public function allowedIps(): array
    {
        $meta = $this->client->provider()->meta ?? [];
        $configured = $meta['webhook_allowed_ips'] ?? null;

        if (is_string($configured) && trim($configured) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $configured))));
        }

        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(array_map('strval', $configured)));
        }

        return self::DEFAULT_ALLOWED_IPS;
    }

    private function isIpAllowed(?string $ip, bool $isLive): bool
    {
        if (! $isLive) {
            return true;
        }

        if (! is_string($ip) || $ip === '') {
            return false;
        }

        return in_array($ip, $this->allowedIps(), true);
    }

    private function looksLikeCollection(array $eventData, string $eventType): bool
    {
        return isset($eventData['paymentReference'])
            || str_contains($eventType, 'COLLECTION')
            || str_contains($eventType, 'SUCCESSFUL_TRANSACTION');
    }

    private function looksLikeDisbursement(array $eventData, string $eventType): bool
    {
        return str_contains($eventType, 'DISBURSEMENT')
            || (isset($eventData['reference']) && ! isset($eventData['paymentReference']));
    }

    private function handleCollection(PaymentWebhook $webhook, array $eventData): void
    {
        $paymentReference = (string) ($eventData['paymentReference'] ?? '');
        if ($paymentReference === '') {
            return;
        }

        $verified = $this->rail->verifyTransaction($paymentReference);
        $status = strtoupper((string) ($verified['paymentStatus'] ?? $eventData['paymentStatus'] ?? ''));
        $amountPaid = (string) ($verified['amountPaid'] ?? $eventData['amountPaid'] ?? '0');

        if (! in_array($status, ['PAID', 'SUCCESS', 'COMPLETED'], true)) {
            return;
        }

        $funding = WalletFunding::query()
            ->where('provider_payment_reference', $paymentReference)
            ->first();

        if (! $funding) {
            $order = Order::query()
                ->where('provider_payment_reference', $paymentReference)
                ->where('source', 'platform')
                ->where('payment_method', 'gateway')
                ->first();

            if ($order) {
                $this->completePlatformGatewayOrder($order, $verified, $amountPaid, $status);

                return;
            }

            $reserved = app(\App\Modules\Wallet\Services\DepositCheckoutService::class)->creditReservedPayment($verified);
            if (! $reserved) {
                $this->paymentAlerts->gatewayUnmatched($paymentReference, ['webhook_id' => $webhook->id]);
            }

            return;
        }

        PaymentTimelineEvent::record($funding, 'webhook_received', 'Webhook received', [
            'webhook_id' => $webhook->id,
        ]);

        if ($funding->internal_status === \App\Modules\Wallet\Services\DepositCheckoutService::HELD_OVER_LIMIT) {
            $funding->update(['provider_status' => $status]);

            return;
        }

        if (bccomp($amountPaid, (string) $funding->amount, 2) !== 0) {
            $funding->update(['provider_status' => $status]);
            Log::warning('Monnify amount mismatch', [
                'funding_id' => $funding->id,
                'expected' => $funding->amount,
                'paid' => $amountPaid,
            ]);
            $this->paymentAlerts->amountMismatch(
                'Funding #'.$funding->id,
                (string) $funding->amount,
                $amountPaid,
                ['funding_id' => $funding->id, 'webhook_id' => $webhook->id]
            );

            return;
        }

        $funding->update([
            'provider_status' => $status,
            'provider_transaction_reference' => $verified['transactionReference'] ?? $funding->provider_transaction_reference,
            'internal_status' => $funding->internal_status === 'completed' ? 'completed' : 'processing',
            'status' => $funding->status === 'approved' ? 'approved' : 'processing',
        ]);

        $this->wallets->creditFromFunding($funding);
    }

    /**
     * @param  array<string, mixed>  $verified
     */
    private function completePlatformGatewayOrder(Order $order, array $verified, string $amountPaid, string $status): void
    {
        if (bccomp($amountPaid, (string) $order->total_amount, 2) !== 0) {
            Log::warning('Monnify platform order amount mismatch', [
                'order_id' => $order->id,
                'expected' => $order->total_amount,
                'paid' => $amountPaid,
            ]);
            $this->paymentAlerts->amountMismatch(
                'Order #'.$order->id,
                (string) $order->total_amount,
                $amountPaid,
                ['order_id' => $order->id]
            );

            return;
        }

        $order->update([
            'provider_transaction_reference' => $verified['transactionReference'] ?? $order->provider_transaction_reference,
        ]);

        if ($order->status === 'paid') {
            return;
        }

        app(PlatformCheckoutService::class)->fulfillPaidGatewayOrder($order);
    }

    private function handleDisbursement(PaymentWebhook $webhook, array $eventData, string $eventType): void
    {
        $reference = (string) ($eventData['reference'] ?? '');
        if ($reference === '') {
            return;
        }

        $withdrawal = Withdrawal::query()
            ->where('provider_payout_reference', $reference)
            ->first();

        if (! $withdrawal) {
            return;
        }

        PaymentTimelineEvent::record($withdrawal, 'webhook_received', 'Webhook received', [
            'webhook_id' => $webhook->id,
        ]);

        // Re-query Monnify transfer status before mutating ledger (mirror deposit verify).
        $status = '';
        try {
            $verified = $this->rail->getTransferStatus($reference);
            $status = strtoupper((string) ($verified['status'] ?? ''));
        } catch (\Throwable $e) {
            Log::warning('Monnify transfer status re-query failed; falling back to event', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
        }

        if ($status === '') {
            $status = strtoupper((string) ($eventData['status'] ?? ''));
        }
        if ($status === '' && str_contains($eventType, 'SUCCESS')) {
            $status = 'SUCCESS';
        }
        if ($status === '' && str_contains($eventType, 'FAILED')) {
            $status = 'FAILED';
        }
        if ($status === '' && str_contains($eventType, 'REVERSED')) {
            $status = 'REVERSED';
        }

        $withdrawal->update(['provider_status' => $status]);

        if (in_array($status, ['SUCCESS', 'COMPLETED', 'SUCCESSFUL'], true)) {
            $this->wallets->completeWithdrawalPayout($withdrawal);

            return;
        }

        if (in_array($status, ['FAILED', 'EXPIRED'], true)) {
            $this->paymentAlerts->disbursementFailed($reference, $status, ['withdrawal_id' => $withdrawal->id]);
            $this->wallets->failWithdrawalPayout($withdrawal, 'Monnify disbursement '.$status);

            return;
        }

        if ($status === 'REVERSED') {
            if ($withdrawal->internal_status === 'completed' || $withdrawal->status === 'completed') {
                Log::critical('Monnify payout reversed after completion — admin review required', [
                    'withdrawal_id' => $withdrawal->id,
                    'reference' => $reference,
                ]);
                PaymentTimelineEvent::record($withdrawal, 'reversed_alert', 'Reversed after completion, admin review required');
                $this->paymentAlerts->disbursementReversed($reference, ['withdrawal_id' => $withdrawal->id]);
                \App\Events\WithdrawalPayoutFailed::dispatch(
                    (int) $withdrawal->id,
                    (int) $withdrawal->user_id,
                    (float) $withdrawal->amount,
                    'reversed',
                    (string) ($withdrawal->currency ?: 'NGN')
                );
            } else {
                $this->wallets->failWithdrawalPayout($withdrawal, 'Monnify disbursement reversed');
            }
        }
    }
}
