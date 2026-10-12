<?php

namespace App\Modules\Wallet\Payments\Contracts;

use App\Modules\Wallet\Payments\Data\PaymentEvent;
use App\Modules\Wallet\Payments\Exceptions\InvalidWebhookSignature;
use Illuminate\Http\Request;

/**
 * One payment provider. A driver implements CollectionGateway, PayoutGateway, or both.
 */
interface GatewayDriver
{
    public function key(): string;

    public function displayName(): string;

    /**
     * Fields the admin settings card renders. Each entry:
     * name, label, type (secret|text|select), required (bool), store (credentials|meta),
     * optional options (value => label) and hint.
     *
     * @return list<array{name: string, label: string, type: string, required: bool, store: string, options?: array<string, string>, hint?: string}>
     */
    public function credentialFields(): array;

    /** Enabled by an admin and every required credential is present. */
    public function isConfigured(): bool;

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * Verify the provider signature and normalise the delivery.
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(Request $request): PaymentEvent;

    /**
     * Normalise a stored (already verified) webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function normalizeWebhookPayload(array $payload): PaymentEvent;
}
