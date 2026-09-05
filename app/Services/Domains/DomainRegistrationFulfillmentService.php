<?php

namespace App\Services\Domains;

use App\Models\DomainQuote;
use App\Models\DomainRegistration;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformProduct;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DomainRegistrationFulfillmentService
{
    public function __construct(
        private DomainProviderManager $providers,
        private PlatformDomainPricingPolicy $pricing,
        private DomainAuditLogger $audit,
        private DomainNameserverService $nameservers,
    ) {}

    public function fulfillOrder(Order $order): void
    {
        if (! config('domains.auto_register_on_purchase', true)) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $this->fulfillOrderItem($order, $item);
        }
    }

    private function fulfillOrderItem(Order $order, OrderItem $item): void
    {
        if (! $this->isDomainPurchaseLine($item)) {
            return;
        }

        if (DomainRegistration::query()->where('order_item_id', $item->id)->exists()) {
            return;
        }

        $options = $item->options ?? [];
        $fqdn = strtolower((string) ($options['domain_fqdn'] ?? $options['domain_name'] ?? ''));
        $quoteId = (int) ($options['domain_quote_id'] ?? 0);

        if ($fqdn === '' || $quoteId <= 0) {
            return;
        }

        $quote = DomainQuote::query()->find($quoteId);
        if (! $quote) {
            return;
        }

        $registrantContact = $options['registrant_contact'] ?? null;
        if (! is_array($registrantContact)) {
            $registration = DomainRegistration::query()->create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'domain_quote_id' => $quote->id,
                'fqdn' => $fqdn,
                'provider_key' => $quote->provider_key,
                'provider_cost_at_checkout' => $quote->provider_cost,
                'provider_currency_at_checkout' => $quote->provider_currency,
                'status' => DomainRegistration::STATUS_FAILED,
                'error_message' => 'Registrant contact details are missing.',
            ]);
            $this->audit->log('domains.fulfillment.failed', $registration, ['reason' => 'missing_registrant']);

            return;
        }

        $registration = DomainRegistration::query()->create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'domain_quote_id' => $quote->id,
            'fqdn' => $fqdn,
            'provider_key' => $quote->provider_key,
            'provider_cost_at_checkout' => $quote->provider_cost,
            'provider_currency_at_checkout' => $quote->provider_currency,
            'registrant_contact' => $registrantContact,
            'status' => DomainRegistration::STATUS_PROCESSING,
        ]);

        $this->audit->log('domains.fulfillment.started', $registration, [
            'fqdn' => $fqdn,
            'order_id' => $order->id,
        ]);

        try {
            $provider = $this->providers->providerRecord($quote->provider_key);
            $adapter = $this->providers->adapterFor($provider);

            $availability = $adapter->checkAvailability($provider, $fqdn);
            if (! $availability->available || ! $availability->isRegistration()) {
                $this->markFailed($registration, 'Domain is no longer available for registration.');

                return;
            }

            $freshQuote = $adapter->getRegistrationQuote($provider, $fqdn, $availability);
            $tolerance = max(0, (float) config('domains.price_drift_tolerance_percent', 2));
            $checkoutCost = (string) $quote->provider_cost;
            $freshCost = number_format($freshQuote->providerCost, 4, '.', '');

            if (! $this->pricing->driftWithinTolerance($checkoutCost, $freshCost, $tolerance)) {
                $this->markReconciliation($registration, 'Provider registration cost increased beyond tolerance.');

                return;
            }

            $result = $adapter->registerDomain($provider, $fqdn, [
                'provider_cost' => $freshQuote->providerCost,
                'premium' => $quote->premium,
                'purchase_type' => $quote->purchase_type,
                'quote_id' => $quote->id,
                'idempotency_key' => 'domain-'.$order->id.'-'.$item->id,
                'registrant_contact' => $registrantContact,
            ]);

            if ($result->success) {
                $confirmedNs = $this->nameservers->resolveAfterRegistration(
                    $quote->provider_key,
                    $fqdn,
                    $result->providerMeta,
                );

                $registration->update([
                    'status' => DomainRegistration::STATUS_REGISTERED,
                    'provider_reference' => $result->providerReference,
                    'provider_meta' => $result->providerMeta,
                    'registered_at' => now(),
                    'error_message' => null,
                    'nameservers' => $confirmedNs !== [] ? $confirmedNs : null,
                    'nameservers_updated_at' => $confirmedNs !== [] ? now() : null,
                    'nameservers_synced_at' => $confirmedNs !== [] ? now() : null,
                ]);
                $this->audit->log('domains.fulfillment.registered', $registration->fresh());

                return;
            }

            $this->markFailed($registration, $result->errorMessage ?? 'Registration failed.');
        } catch (\Throwable $e) {
            Log::error('Domain registration fulfillment failed', [
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'fqdn' => $fqdn,
                'message' => $e->getMessage(),
            ]);

            $this->markReconciliation($registration, $e->getMessage());
        }
    }

    private function markFailed(DomainRegistration $registration, string $message): void
    {
        $registration->update([
            'status' => DomainRegistration::STATUS_FAILED,
            'error_message' => Str::limit($message, 500),
            'last_attempt_at' => now(),
        ]);
        $this->audit->log('domains.fulfillment.failed', $registration->fresh(), [
            'message' => Str::limit($message, 200),
        ]);
    }

    private function markReconciliation(DomainRegistration $registration, string $message): void
    {
        $registration->update([
            'status' => DomainRegistration::STATUS_RECONCILIATION_REQUIRED,
            'error_message' => Str::limit($message, 500),
            'last_attempt_at' => now(),
        ]);
        $this->audit->log('domains.fulfillment.reconciliation_required', $registration->fresh(), [
            'message' => Str::limit($message, 200),
        ]);
    }

    private function isDomainPurchaseLine(OrderItem $item): bool
    {
        $options = $item->options ?? [];

        if (($options['domain_mode'] ?? '') === 'connect') {
            return false;
        }

        if (filled($options['domain_fqdn'] ?? null) && filled($options['domain_quote_id'] ?? null)) {
            return true;
        }

        if ($item->item_type !== 'platform_product') {
            return false;
        }

        $product = PlatformProduct::query()->find($item->item_id);

        return false;
    }
}
