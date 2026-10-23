<?php

namespace App\Modules\Catalog\Services;

use App\Events\OrderCompleted;
use App\Events\OrderManualBankTransferPaymentFailed;
use App\Events\OrderManualBankTransferSubmitted;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\SystemSetting;
use App\Models\User;
use App\Modules\Wallet\Payments\Data\CheckoutRequest;
use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use App\Modules\Wallet\Payments\Exceptions\GatewayUnavailable;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Notifications\AdminPaymentAlertNotifier;
use App\Support\Money\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PlatformCheckoutService
{
    public const MANUAL_PAYMENT_WINDOW_MINUTES = 10;

    public const MANUAL_PAYMENT_MAX_SESSIONS = 3;
    public function __construct(
        private WalletService $walletService,
        private GatewayRegistry $gateways,
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

    /**
     * Ask the order's own gateway whether it was paid; fulfil it once when it was.
     */
    public function confirmGatewayOrder(Order $order): Order
    {
        if ($order->status === 'paid') {
            return $order;
        }

        if ($order->payment_method !== 'gateway' || ! filled($order->provider_payment_reference)) {
            throw new InvalidArgumentException('This order was not paid through a payment gateway.');
        }

        $driver = $this->gateways->collectionDriver((string) $order->payment_provider);
        if (! $driver) {
            throw new InvalidArgumentException('The payment provider for this order is no longer available. An admin will review it.');
        }

        $event = $driver->verify((string) $order->provider_payment_reference, $order->provider_transaction_reference);

        $metadata = (array) ($order->payment_metadata ?? []);
        $metadata['last_verify'] = ['status' => $event->status, 'at' => now()->toIso8601String(), 'code' => $event->failureCode];
        $order->update([
            'provider_transaction_reference' => $event->providerReference ?? $order->provider_transaction_reference,
            'payment_metadata' => $metadata,
        ]);

        if (! $event->succeeded()) {
            return $order->fresh();
        }

        if (! $this->gatewayPaymentCoversOrder($event, $order)) {
            app(AdminPaymentAlertNotifier::class)->amountMismatch(
                'Order '.$order->reference,
                money($order->total_amount),
                trim(($event->currency ?? '').' '.($event->amount ?? '?')),
                ['order_id' => $order->id],
                (string) $order->payment_provider,
            );

            throw new InvalidArgumentException('Paid amount does not match the order total. An admin will review it.');
        }

        return $this->fulfillPaidGatewayOrder($order->fresh());
    }

    /**
     * Exact match or overpayment (common with crypto). Underpayment is rejected.
     */
    private function gatewayPaymentCoversOrder(\App\Modules\Wallet\Payments\Data\PaymentEvent $event, Order $order): bool
    {
        $expectedCurrency = strtoupper((string) config('platform.currency', 'USD'));
        $expectedAmount = Money::round((string) $order->total_amount, 2);

        $candidates = [
            ['currency' => $event->currency, 'amount' => $event->amount],
            ['currency' => data_get($event->raw, 'pricing.currency'), 'amount' => data_get($event->raw, 'pricing.amount')],
            ['currency' => data_get($event->raw, 'charge.currency'), 'amount' => data_get($event->raw, 'charge.amount')],
            ['currency' => data_get($event->raw, 'settlement_currency'), 'amount' => data_get($event->raw, 'settlement_amount')],
            ['currency' => data_get($event->raw, 'charge.settlement_currency'), 'amount' => data_get($event->raw, 'charge.settlement_amount')],
        ];

        foreach ($candidates as $row) {
            $currency = strtoupper((string) ($row['currency'] ?? ''));
            $amount = $row['amount'] ?? null;
            if ($currency !== $expectedCurrency || $amount === null || $amount === '') {
                continue;
            }
            if (Money::cmp((string) $amount, $expectedAmount, 2) >= 0) {
                return true;
            }
        }

        return false;
    }

    public function manualBankTransferEnabledForCheckout(): bool
    {
        return SystemSetting::manualBankTransferEnabled()
            && SystemSetting::manualBankTransferConfigured();
    }

    /**
     * @param  array{variant_id?: int|null, quantity: int, idempotency_key?: string|null, payment_method?: string|null, redirect_url?: string|null}  $data
     */
    public function purchase(User $buyer, PlatformProduct $product, array $data): Order
    {
        $requested = (string) ($data['payment_method'] ?? 'wallet');

        // Resolved outside the order transaction because it may call the YouTube Data API.
        $data['campaign_brief'] = is_array($data['campaign_brief'] ?? null)
            ? $data['campaign_brief']
            : app(\App\Services\Campaigns\CampaignBriefService::class)->resolve($product, $data);

        if ($requested === Order::PAYMENT_MANUAL_BANK_TRANSFER) {
            if (! $this->manualBankTransferEnabledForCheckout()) {
                throw new InvalidArgumentException('Manual bank transfer is not available.');
            }

            return $this->purchaseViaManualBankTransfer($buyer, $product, $data);
        }

        if ($requested === 'gateway') {
            return $this->purchaseViaGateway($buyer, $product, $data);
        }

        return $this->purchaseViaWallet($buyer, $product, $data);
    }

    /**
     * Complete a pending gateway order after the gateway confirmed payment.
     */
    public function fulfillPaidGatewayOrder(Order $order): Order
    {
        // Money was received, so the order is still fulfilled; admins refund or cancel from the log.
        if ($order->status !== 'paid' && $this->orderHasRetiredProducts($order)) {
            Log::channel('financial')->warning('Gateway payment received for a service that is no longer offered', [
                'order_id' => $order->id,
                'reference' => $order->reference,
            ]);
        }

        return $this->fulfillPaidCatalogOrder($order, ['gateway']);
    }

    /**
     * @param  list<string>  $allowedMethods
     */
    public function fulfillPaidCatalogOrder(Order $order, array $allowedMethods, ?int $confirmedBy = null): Order
    {
        return DB::transaction(function () use ($order, $allowedMethods, $confirmedBy) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid') {
                return $locked->load('items.variant');
            }

            if ($locked->source !== 'platform' || ! in_array($locked->payment_method, $allowedMethods, true)) {
                throw new InvalidArgumentException('Order is not a fulfillable platform purchase.');
            }

            if (! in_array($locked->status, ['pending', 'processing'], true)) {
                throw new InvalidArgumentException('Order cannot be fulfilled in status '.$locked->status);
            }

            $this->walletService->creditPlatformFromGatewaySale($locked, (float) $locked->total_amount);

            $locked->status = 'paid';
            $locked->payment_confirmed_at = now();
            if ($confirmedBy) {
                $locked->payment_confirmed_by = $confirmedBy;
            }
            $locked->save();

            $locked->load('items.variant');

            app(\App\Services\Campaigns\CampaignFulfillmentService::class)->createFromPaidOrder($locked);

            DB::afterCommit(function () use ($locked) {
                OrderCompleted::dispatch($locked->id, $locked->user_id, null);
            });

            return $locked;
        });
    }

    public function cancelManualBankTransferOrder(Order $order, ?string $reason = null, bool $notifyAdminsOfFailure = false): Order
    {
        $alreadyCancelled = $order->status === 'cancelled';

        $cancelled = DB::transaction(function () use ($order, $reason) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_method !== Order::PAYMENT_MANUAL_BANK_TRANSFER) {
                throw new InvalidArgumentException('Order is not a manual bank transfer purchase.');
            }

            if ($locked->status === 'paid') {
                throw new InvalidArgumentException('Paid orders cannot be cancelled.');
            }

            if ($locked->status === 'cancelled') {
                return $locked;
            }

            $meta = $locked->payment_metadata ?? [];
            if ($reason) {
                $meta['cancel_reason'] = $reason;
            }
            $meta['manual_payment_cancelled_at'] = now()->toIso8601String();
            $locked->update([
                'status' => 'cancelled',
                'payment_metadata' => $meta,
            ]);

            return $locked->fresh();
        });

        if ($notifyAdminsOfFailure && ! $alreadyCancelled) {
            OrderManualBankTransferPaymentFailed::dispatch(
                (int) $cancelled->id,
                (int) $cancelled->user_id,
                (float) $cancelled->total_amount,
                (string) ($cancelled->currency ?? config('platform.currency')),
                (string) $cancelled->reference,
                (string) ($reason ?? 'Payment not completed.'),
            );
        }

        app(\App\Modules\Admin\Services\AuditLogService::class)->log(
            null,
            'order.manual_bank_transfer.cancelled',
            $cancelled,
            null,
            [
                'order_id' => $cancelled->id,
                'user_id' => $cancelled->user_id,
                'reason' => $reason,
                'auto_cancelled' => $notifyAdminsOfFailure,
            ],
            request()?->ip(),
        );

        return $cancelled;
    }

    /**
     * @param  array<string, mixed>  $proofMeta
     */
    public function submitManualBankTransferProof(Order $order, array $proofMeta): Order
    {
        if ($order->source !== 'platform') {
            throw new InvalidArgumentException('This order cannot accept payment proof.');
        }

        if ($order->payment_method !== Order::PAYMENT_MANUAL_BANK_TRANSFER || $order->status !== 'pending') {
            throw new InvalidArgumentException('This order cannot accept payment proof.');
        }

        if ($this->isManualPaymentExpired($order)) {
            throw new InvalidArgumentException('This payment window has expired.');
        }

        $this->assertOrderProductsOffered($order);

        $firstSubmission = $order->payment_submitted_at === null;

        $meta = array_merge($order->payment_metadata ?? [], $proofMeta);
        $order->update([
            'payment_metadata' => $meta,
            'payment_submitted_at' => now(),
        ]);

        if ($firstSubmission) {
            OrderManualBankTransferSubmitted::dispatch(
                (int) $order->id,
                (int) $order->user_id,
                (float) $order->total_amount,
                (string) ($order->currency ?? config('platform.currency')),
                (string) $order->reference,
            );
        }

        return $order->fresh();
    }

    public function initializeManualPaymentWindow(Order $order): Order
    {
        $meta = $order->payment_metadata ?? [];

        if (! isset($meta['manual_payment_expires_at'])) {
            $meta['manual_payment_expires_at'] = now()->addMinutes(self::MANUAL_PAYMENT_WINDOW_MINUTES)->toIso8601String();
            $meta['manual_payment_session'] = 1;
            $meta['manual_payment_expired'] = false;
            $order->update(['payment_metadata' => $meta]);
        }

        return $order->fresh();
    }

    public function manualPaymentSecondsRemaining(Order $order): int
    {
        $expiresAt = $order->payment_metadata['manual_payment_expires_at'] ?? null;
        if (! is_string($expiresAt) || $expiresAt === '') {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds(\Illuminate\Support\Carbon::parse($expiresAt), false));
    }

    public function isManualPaymentExpired(Order $order): bool
    {
        if ($order->payment_submitted_at) {
            return false;
        }

        $meta = $order->payment_metadata ?? [];
        if (($meta['manual_payment_expired'] ?? false) === true) {
            return true;
        }

        $expiresAt = $meta['manual_payment_expires_at'] ?? null;
        if (! is_string($expiresAt) || $expiresAt === '') {
            return false;
        }

        return now()->greaterThanOrEqualTo(\Illuminate\Support\Carbon::parse($expiresAt));
    }

    /**
     * @return array{status: string, can_restart?: bool, session?: int, message?: string}
     */
    public function processManualPaymentExpiry(Order $order): array
    {
        $order = $order->fresh();

        if ($order->status === 'cancelled') {
            return [
                'status' => 'cancelled',
                'message' => 'Your order was cancelled because payment was not completed in time.',
            ];
        }

        if ($order->payment_submitted_at) {
            return ['status' => 'submitted'];
        }

        if (! $this->isManualPaymentExpired($order)) {
            return ['status' => 'active'];
        }

        $meta = $order->payment_metadata ?? [];
        $session = (int) ($meta['manual_payment_session'] ?? 1);

        if ($session >= self::MANUAL_PAYMENT_MAX_SESSIONS) {
            $this->cancelManualBankTransferOrder(
                $order,
                'Payment not received within the allowed time.',
                notifyAdminsOfFailure: true,
            );

            return [
                'status' => 'cancelled',
                'message' => 'Your order is being cancelled because payment was not completed in time.',
            ];
        }

        if (($meta['manual_payment_expired'] ?? false) !== true) {
            $meta['manual_payment_expired'] = true;
            $meta['manual_payment_failed_at'] = now()->toIso8601String();
            $order->update(['payment_metadata' => $meta]);
        }

        return [
            'status' => 'failed',
            'can_restart' => true,
            'session' => $session,
        ];
    }

    public function restartManualPaymentWindow(Order $order): Order
    {
        if ($order->payment_submitted_at) {
            throw new InvalidArgumentException('Payment proof was already submitted.');
        }

        $meta = $order->payment_metadata ?? [];
        $session = (int) ($meta['manual_payment_session'] ?? 1);

        if ($session >= self::MANUAL_PAYMENT_MAX_SESSIONS) {
            throw new InvalidArgumentException('No payment restarts remaining.');
        }

        $this->assertOrderProductsOffered($order);

        $meta['manual_payment_session'] = $session + 1;
        $meta['manual_payment_expires_at'] = now()->addMinutes(self::MANUAL_PAYMENT_WINDOW_MINUTES)->toIso8601String();
        $meta['manual_payment_expired'] = false;
        $order->update(['payment_metadata' => $meta]);

        return $order->fresh();
    }

    /** True when any catalog line belongs to a platform that is no longer offered. */
    public function orderHasRetiredProducts(Order $order): bool
    {
        $order->loadMissing('items');

        $productIds = $order->items
            ->where('item_type', 'platform_product')
            ->pluck('item_id')
            ->filter()
            ->unique();

        if ($productIds->isEmpty()) {
            return false;
        }

        $offered = PlatformProduct::query()->whereIn('id', $productIds)->offered()->count();

        return $offered < $productIds->count();
    }

    public function assertOrderProductsOffered(Order $order): void
    {
        if ($this->orderHasRetiredProducts($order)) {
            throw new InvalidArgumentException('This service is no longer offered, so this order cannot be paid.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function purchaseViaWallet(User $buyer, PlatformProduct $product, array $data): Order
    {
        $wallet = $buyer->wallet;
        if (! $wallet) {
            throw new InvalidArgumentException('Create a wallet before paying with wallet balance.');
        }

        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = Order::query()
                ->where('user_id', $buyer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($buyer, $wallet, $product, $data, $idempotencyKey) {
                if ($idempotencyKey) {
                    $existing = Order::query()
                        ->where('user_id', $buyer->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        return $existing;
                    }
                }

                $checkout = $this->buildCheckout($product, $data);

                $order = Order::create([
                    'source' => 'platform',
                    'user_id' => $buyer->id,
                    'listing_id' => null,
                    'reference' => 'PLT-'.strtoupper(Str::random(8)),
                    'amount' => $checkout['total'],
                    'total_amount' => $checkout['total'],
                    'status' => 'paid',
                    'payment_method' => 'wallet',
                    'idempotency_key' => $idempotencyKey,
                ]);

                foreach ($checkout['lines'] as $line) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_type' => 'platform_product',
                        'item_id' => $line['product_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                        'platform_product_variant_id' => $line['variant_id'],
                        'options' => $line['options'],
                    ]);
                }

                $order->load('items.variant');

                $this->walletService->debitForPlatformPurchase($wallet, $order, (float) $checkout['total']);

                app(\App\Services\Campaigns\CampaignFulfillmentService::class)->createFromPaidOrder($order);

                DB::afterCommit(function () use ($order, $buyer) {
                    OrderCompleted::dispatch($order->id, $buyer->id, null);
                });

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            return $this->recoverIdempotent($buyer, $idempotencyKey, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function purchaseViaGateway(User $buyer, PlatformProduct $product, array $data): Order
    {
        try {
            $driver = $this->gateways->collection($data['gateway'] ?? null);
        } catch (GatewayUnavailable $e) {
            throw new InvalidArgumentException($e->getMessage());
        }
        $gatewayKey = $driver->key();

        $redirectUrl = (string) ($data['redirect_url'] ?? '');
        if ($redirectUrl === '') {
            throw new InvalidArgumentException('Missing payment return URL.');
        }

        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = Order::query()
                ->where('user_id', $buyer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                if ($existing->status === 'paid') {
                    return $existing;
                }
                if (
                    $existing->isAwaitingGatewayPayment()
                    && filled($existing->checkout_url)
                    && ! $existing->isCheckoutExpired()
                    && $existing->payment_provider === $gatewayKey
                ) {
                    return $existing;
                }
            }
        }

        try {
            $order = DB::transaction(function () use ($buyer, $product, $data, $idempotencyKey, $gatewayKey) {
                if ($idempotencyKey) {
                    $existing = Order::query()
                        ->where('user_id', $buyer->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        if ($existing->status === 'paid') {
                            return $existing;
                        }
                        if ($existing->isAwaitingGatewayPayment()) {
                            return $existing;
                        }
                    }
                }

                $checkout = $this->buildCheckout($product, $data);

                $paymentReference = 'PLT-PAY-'.strtoupper((string) Str::ulid());

                $order = Order::create([
                    'source' => 'platform',
                    'user_id' => $buyer->id,
                    'listing_id' => null,
                    'reference' => 'PLT-'.strtoupper(Str::random(8)),
                    'amount' => $checkout['total'],
                    'total_amount' => $checkout['total'],
                    'status' => 'pending',
                    'payment_method' => 'gateway',
                    'payment_provider' => $gatewayKey,
                    'provider_payment_reference' => $paymentReference,
                    'idempotency_key' => $idempotencyKey,
                ]);

                foreach ($checkout['lines'] as $line) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_type' => 'platform_product',
                        'item_id' => $line['product_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                        'platform_product_variant_id' => $line['variant_id'],
                        'options' => $line['options'],
                    ]);
                }

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            return $this->recoverIdempotent($buyer, $idempotencyKey, $e);
        }

        if ($order->status === 'paid') {
            return $order;
        }
        if (filled($order->checkout_url) && ! $order->isCheckoutExpired() && $order->payment_provider === $gatewayKey) {
            return $order;
        }

        if ($order->payment_provider !== $gatewayKey || $order->isCheckoutExpired() || filled($order->checkout_url) || ! filled($order->provider_payment_reference)) {
            $metadata = (array) ($order->payment_metadata ?? []);
            if (filled($order->provider_payment_reference)) {
                $metadata['replaced_references'] = array_slice(array_merge((array) ($metadata['replaced_references'] ?? []), [[
                    'provider' => (string) $order->payment_provider,
                    'reference' => (string) $order->provider_payment_reference,
                    'transaction_reference' => $order->provider_transaction_reference,
                    'replaced_at' => now()->toIso8601String(),
                ]]), -10);
            }
            $order->update([
                'payment_metadata' => $metadata,
                'payment_provider' => $gatewayKey,
                'provider_payment_reference' => 'PLT-PAY-'.strtoupper((string) Str::ulid()),
                'provider_transaction_reference' => null,
                'checkout_url' => null,
            ]);
            $order->refresh();
        }

        $separator = str_contains($redirectUrl, '?') ? '&' : '?';
        $returnUrl = $redirectUrl.$separator.'reference='.urlencode((string) $order->provider_payment_reference);
        $minutes = (int) config('payments.checkout_expiry_minutes', 40);

        try {
            $session = $driver->initializeCheckout(new CheckoutRequest(
                amount: Money::round((string) $order->total_amount, 2),
                currency: (string) config('platform.currency', 'USD'),
                reference: (string) $order->provider_payment_reference,
                customerEmail: (string) $buyer->email,
                customerName: (string) $buyer->name,
                callbackUrl: $returnUrl,
                description: 'Order '.$order->reference,
                cancelUrl: $returnUrl,
                expiresInMinutes: $minutes,
                metadata: ['type' => 'order', 'order_id' => $order->id],
            ));
        } catch (GatewayException $e) {
            Log::warning('order.checkout_failed', ['order_id' => $order->id, 'gateway' => $gatewayKey, 'code' => $e->providerCode, 'error' => $e->getMessage()]);

            throw new InvalidArgumentException('We could not open '.$driver->displayName().' right now. Try again, or choose another payment option.');
        }

        $order->update([
            'checkout_url' => $session->checkoutUrl,
            'provider_transaction_reference' => $session->providerReference,
            'checkout_expires_at' => $session->expiresAt ?? now()->addMinutes($minutes),
            'status' => 'processing',
        ]);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function purchaseViaManualBankTransfer(User $buyer, PlatformProduct $product, array $data): Order
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = Order::query()
                ->where('user_id', $buyer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        try {
            $order = DB::transaction(function () use ($buyer, $product, $data, $idempotencyKey) {
                if ($idempotencyKey) {
                    $existing = Order::query()
                        ->where('user_id', $buyer->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        return $existing;
                    }
                }

                $checkout = $this->buildCheckout($product, $data);

                $order = Order::create([
                    'source' => 'platform',
                    'user_id' => $buyer->id,
                    'listing_id' => null,
                    'reference' => 'PLT-'.strtoupper(Str::random(8)),
                    'amount' => $checkout['total'],
                    'total_amount' => $checkout['total'],
                    'status' => 'pending',
                    'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
                    'payment_provider' => 'manual',
                    'idempotency_key' => $idempotencyKey,
                    'payment_metadata' => [
                        'manual_payment_expires_at' => now()->addMinutes(self::MANUAL_PAYMENT_WINDOW_MINUTES)->toIso8601String(),
                        'manual_payment_session' => 1,
                        'manual_payment_expired' => false,
                    ],
                ]);

                foreach ($checkout['lines'] as $line) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_type' => 'platform_product',
                        'item_id' => $line['product_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                        'platform_product_variant_id' => $line['variant_id'],
                        'options' => $line['options'],
                    ]);
                }

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            return $this->recoverIdempotent($buyer, $idempotencyKey, $e);
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{lines: list<array<string, mixed>>, total: string}
     */
    private function buildCheckout(PlatformProduct $product, array $data): array
    {
        $product = PlatformProduct::query()
            ->with(['serviceCategory', 'productType.serviceCategory'])
            ->where('id', $product->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $product->isVisibleToPublic()) {
            throw new InvalidArgumentException('This product is no longer available.');
        }

        if ($product->taskSetupIssues() !== []) {
            throw new InvalidArgumentException('This product is not available to buy yet. Please try again later.');
        }

        $lines = [$this->prepareMainLine($product, $data)];

        return [
            'lines' => $lines,
            'total' => $this->sumLines($lines),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareMainLine(PlatformProduct $product, array $data): array
    {
        $variant = $this->resolveVariant($product, $data['variant_id'] ?? null);
        if ($variant) {
            $variant = PlatformProductVariant::query()
                ->where('id', $variant->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $variant->is_active || $variant->platform_product_id !== $product->id) {
                throw new InvalidArgumentException('Selected plan is unavailable.');
            }
        }

        $metric = $product->taskProfile()->legacyMetric();

        if (! $variant || ! $variant->isPerUnit() || (float) ($product->agent_reward_percent ?? 0) <= 0) {
            throw new InvalidArgumentException('This product is not available to buy yet. Please try again later.');
        }

        $quote = app(\App\Services\Catalog\PackageQuoteService::class)->quote(
            $product,
            $data['quantity'] ?? null,
            is_array($data['task_quantities'] ?? null) ? $data['task_quantities'] : [],
            $variant,
        );
        $qty = $quote['main_qty'];
        $unitPrice = $quote['unit_price'];
        $engagementQuantity = $qty;
        $lineTotal = $quote['total'];

        $lineOptions = [
            'product_title' => $product->title,
            'variant_label' => $variant?->displayLabel(),
            'pricing_mode' => PlatformProductVariant::PRICING_PER_UNIT,
            'engagement_quantity' => $engagementQuantity,
            'pricing_units' => $variant->effectivePricingUnits(),
            'pricing_price' => number_format((float) $variant->price, 2, '.', ''),
            'unit_label' => $variant->resolveUnitLabel($metric),
            'unit_price' => $unitPrice,
        ];

        if ($product->isCampaignProduct()
            && bccomp(PlatformProductVariant::agentRewardForUnitPrice($unitPrice, $product->agent_reward_percent), PlatformProductVariant::MIN_AGENT_REWARD, 4) < 0) {
            throw new InvalidArgumentException('This package is priced too low to pay agents a reward. Please contact support.');
        }

        if ($product->taskProfile()->isMultiTask()) {
            $lineOptions['main_task'] = $quote['main_task'];
            $lineOptions['task_quantities'] = $quote['task_quantities'];
            $lineOptions['task_limits'] = $quote['task_limits'];
            $lineOptions['extra_units'] = $quote['extra_units'];
        }

        if (! empty($data['target_url'])) {
            $targetUrl = \App\Services\Engagement\TargetUrlValidator::normalize((string) $data['target_url']);
            if ($product->requiresTargetUrl() && $targetUrl) {
                \App\Services\Engagement\TargetUrlValidator::assertValidForProduct($targetUrl, (string) $product->slug);
                $lineOptions['target_url'] = $targetUrl;
            } elseif ($targetUrl) {
                $lineOptions['target_url'] = $targetUrl;
            } elseif ($product->requiresTargetUrl()) {
                throw new InvalidArgumentException('A valid YouTube link is required for this campaign package.');
            }
        } elseif ($product->taskProfile()->legacyMetric()) {
            throw new InvalidArgumentException('A YouTube link is required for this campaign package.');
        }

        if (is_array($data['campaign_brief'] ?? null)) {
            $lineOptions = array_merge($lineOptions, $data['campaign_brief']);
        }

        if ($product->isCampaignProduct()) {
            $lineOptions = array_merge(
                $lineOptions,
                app(\App\Services\Campaigns\CampaignBriefService::class)->taskSnapshot($product),
            );
        }

        if (! empty($data['purchased_at'])) {
            $lineOptions['purchased_at'] = $data['purchased_at'];
        }

        return [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'options' => $lineOptions,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function sumLines(array $lines): string
    {
        $total = '0.00';
        foreach ($lines as $line) {
            $total = bcadd($total, (string) $line['line_total'], 2);
        }

        return $total;
    }

    private function resolveVariant(PlatformProduct $product, mixed $variantId): ?PlatformProductVariant
    {
        if ($variantId) {
            $variant = PlatformProductVariant::query()
                ->where('platform_product_id', $product->id)
                ->where('is_active', true)
                ->find($variantId);
            if ($variant) {
                return $variant;
            }
        }

        return $product->pricingVariant();
    }

    private function recoverIdempotent(User $buyer, ?string $idempotencyKey, UniqueConstraintViolationException $e): Order
    {
        if ($idempotencyKey) {
            $existing = Order::query()
                ->where('user_id', $buyer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        throw $e;
    }
}
