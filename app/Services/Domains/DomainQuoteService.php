<?php

namespace App\Services\Domains;

use App\Data\Domains\DomainTld;
use App\Models\DomainProvider;
use App\Models\DomainQuote;
use App\Models\PlatformProduct;
use App\Models\User;
use App\Services\Domains\Exceptions\DomainBusinessException;
use App\Support\Domains\DomainFqdn;
use App\Support\Domains\DomainProductTldPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class DomainQuoteService
{
    public function __construct(
        private DomainProviderManager $providers,
        private PlatformDomainPricingPolicy $pricing,
        private DomainAuditLogger $audit,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     fqdn: string,
     *     retail_price: string,
     *     premium: bool,
     *     quote_token: string|null,
     *     message: string|null,
     *     suggestions: list<array{tld: string, label: string, fqdn: string, retail_price: string, premium: bool, quote_token: string, available: bool}>
     * }
     */
    public function quoteForUser(User $user, PlatformProduct $product, string $sld, string $tld): array
    {
        $label = DomainFqdn::validateLabel($sld);
        if ($label['error'] !== null && $label['value'] === '') {
            return $this->quoteFailure('', '0.00', $label['error'], []);
        }

        if ($label['error'] !== null) {
            try {
                $fqdn = DomainFqdn::parse($label['value'], $tld)['fqdn'];
            } catch (InvalidArgumentException) {
                $fqdn = '';
            }

            return $this->quoteFailure($fqdn, '0.00', $label['error'], []);
        }

        try {
            $parsed = DomainFqdn::parse($label['value'], $tld);
        } catch (InvalidArgumentException $e) {
            return $this->quoteFailure('', '0.00', $e->getMessage(), []);
        }

        $fqdn = $parsed['fqdn'];

        if (! DomainProductTldPolicy::isAllowed($product, $parsed['tld'])) {
            return $this->quoteFailure(
                $fqdn,
                '0.00',
                'Selected extension is not available for this product.',
                $this->alternativeSuggestions($user, $product, $parsed['sld'], $parsed['tld']),
            );
        }

        $primary = $this->attemptQuoteForTld($user, $product, $parsed['sld'], $parsed['tld']);

        $primary['suggestions'] = $this->alternativeSuggestions(
            $user,
            $product,
            $parsed['sld'],
            $parsed['tld'],
            excludeQuoteToken: $primary['quote_token'] ?? null,
        );

        return $primary;
    }

    /**
     * @return array{
     *     available: bool,
     *     fqdn: string,
     *     retail_price: string,
     *     premium: bool,
     *     quote_token: string|null,
     *     message: string|null
     * }
     */
    private function attemptQuoteForTld(User $user, PlatformProduct $product, string $sld, string $tld): array
    {
        $parsed = DomainFqdn::parse($sld, $tld);
        $fqdn = $parsed['fqdn'];

        if (! DomainProductTldPolicy::isAllowed($product, $parsed['tld'])) {
            return $this->quoteFailure($fqdn, '0.00', 'Selected extension is not available for this product.');
        }

        try {
            $result = $this->providers->quoteThroughTld($parsed['tld'], $fqdn, function ($adapter, DomainProvider $provider) use ($fqdn) {
                $availability = $adapter->checkAvailability($provider, $fqdn);

                if (! $availability->available || ! $availability->isRegistration()) {
                    return [
                        'available' => false,
                        'provider' => $provider,
                        'availability' => $availability,
                    ];
                }

                $registrationQuote = $adapter->getRegistrationQuote($provider, $fqdn, $availability);

                return [
                    'available' => true,
                    'provider' => $provider,
                    'registration' => $registrationQuote,
                ];
            });
        } catch (DomainBusinessException $e) {
            return $this->quoteFailure($fqdn, '0.00', $e->getMessage());
        } catch (\Throwable) {
            return $this->quoteFailure($fqdn, '0.00', 'Domain search is temporarily unavailable. Please try again shortly.');
        }

        if (! ($result['available'] ?? false)) {
            return $this->quoteFailure(
                $fqdn,
                '0.00',
                $result['availability']->message ?? 'Domain is not available.',
            );
        }

        /** @var DomainProvider $provider */
        $provider = $result['provider'];
        $registration = $result['registration'];
        $retail = $this->pricing->retailFromProviderCost(
            $registration->providerCost,
            $registration->providerCurrency,
            $product,
        );

        $plainToken = Str::random(64);
        $ttl = max(1, (int) config('domains.quote_ttl_minutes', 15));

        $quote = DomainQuote::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'provider_key' => $provider->key,
            'token_hash' => hash('sha256', $plainToken),
            'fqdn' => $fqdn,
            'tld' => $parsed['tld'],
            'sld' => $parsed['sld'],
            'provider_cost' => $registration->providerCost,
            'provider_currency' => $registration->providerCurrency,
            'retail_price' => $retail['retail_price'],
            'retail_currency' => $retail['retail_currency'],
            'premium' => $registration->premium,
            'purchase_type' => $registration->purchaseType,
            'provider_meta' => $registration->providerMeta,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->audit->log('domains.quote.created', $quote, [
            'fqdn' => $fqdn,
            'provider_key' => $provider->key,
            'retail_price' => $retail['retail_price'],
        ], $user->id);

        return [
            'available' => true,
            'fqdn' => $fqdn,
            'retail_price' => $retail['retail_price'],
            'premium' => $registration->premium,
            'quote_token' => $plainToken,
            'message' => null,
        ];
    }

    /**
     * @return list<array{tld: string, label: string, fqdn: string, retail_price: string, premium: bool, quote_token: string, available: bool}>
     */
    private function alternativeSuggestions(
        User $user,
        PlatformProduct $product,
        string $sld,
        string $excludeTld,
        ?string $excludeQuoteToken = null,
    ): array {
        $limit = max(1, (int) config('domains.suggestion_limit', 3));
        $maxAttempts = max($limit, (int) config('domains.suggestion_max_attempts', 8));
        $featured = DomainProductTldPolicy::featuredTlds($product);
        $allowed = DomainProductTldPolicy::allowedTlds($product);
        $preferred = collect(config('domains.suggestion_preferred_tlds', ['online', 'net', 'pro']))
            ->map(fn ($tld) => ltrim(strtolower((string) $tld), '.'))
            ->filter()
            ->values()
            ->all();

        $candidates = collect($allowed)
            ->reject(fn (string $tld) => $tld === $excludeTld)
            ->sortBy(function (string $tld) use ($featured, $preferred) {
                $preferredIndex = array_search($tld, $preferred, true);
                if ($preferredIndex !== false) {
                    return $preferredIndex;
                }

                $featuredIndex = array_search($tld, $featured, true);

                return $featuredIndex === false
                    ? 1000 + ord($tld[0] ?? 'z')
                    : 100 + $featuredIndex;
            })
            ->values();

        $suggestions = [];
        $attempts = 0;

        foreach ($candidates as $candidateTld) {
            if (count($suggestions) >= $limit || $attempts >= $maxAttempts) {
                break;
            }

            $attempts++;
            $result = $this->attemptQuoteForTld($user, $product, $sld, $candidateTld);
            if (! ($result['available'] ?? false) || empty($result['quote_token'])) {
                continue;
            }

            if ($excludeQuoteToken !== null && hash_equals($excludeQuoteToken, (string) $result['quote_token'])) {
                continue;
            }

            $suggestions[] = [
                'tld' => $candidateTld,
                'label' => '.'.ltrim($candidateTld, '.'),
                'fqdn' => (string) $result['fqdn'],
                'retail_price' => (string) $result['retail_price'],
                'premium' => (bool) ($result['premium'] ?? false),
                'quote_token' => (string) $result['quote_token'],
                'available' => true,
            ];
        }

        return $suggestions;
    }

    /**
     * @param  list<array{tld: string, label: string, fqdn: string, retail_price: string, premium: bool, quote_token: string, available: bool}>  $suggestions
     * @return array{
     *     available: bool,
     *     fqdn: string,
     *     retail_price: string,
     *     premium: bool,
     *     quote_token: string|null,
     *     message: string|null,
     *     suggestions: list<array{tld: string, label: string, fqdn: string, retail_price: string, premium: bool, quote_token: string, available: bool}>
     * }
     */
    private function quoteFailure(
        string $fqdn,
        string $retailPrice,
        string $message,
        array $suggestions = [],
    ): array {
        return [
            'available' => false,
            'fqdn' => $fqdn,
            'retail_price' => $retailPrice,
            'premium' => false,
            'quote_token' => null,
            'message' => $message,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Finalize a reserved quote after gateway payment (no plain token required).
     *
     * @return array{quote: DomainQuote, validated_retail: string, registration: mixed}
     */
    public function consumeReservedQuote(User $user, DomainQuote $quote, int $orderId): array
    {
        return DB::transaction(function () use ($user, $quote, $orderId) {
            $locked = DomainQuote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->reserved_order_id !== $orderId || $locked->user_id !== $user->id) {
                throw new InvalidArgumentException('Invalid or expired domain quote.');
            }

            if ($locked->isConsumed()) {
                return [
                    'quote' => $locked,
                    'validated_retail' => (string) $locked->retail_price,
                    'registration' => null,
                ];
            }

            $validated = $this->validateLockedQuote($locked, $user);

            $locked->update([
                'consumed_at' => now(),
                'reserved_at' => null,
                'reserved_order_id' => null,
            ]);

            $this->audit->log('domains.quote.consumed', $locked->fresh(), [
                'order_id' => $orderId,
                'gateway' => true,
            ], $user->id);

            return [
                'quote' => $locked->fresh(),
                'validated_retail' => $validated['validated_retail'],
                'registration' => $validated['registration'],
            ];
        });
    }

    /**
     * @return array{quote: DomainQuote, validated_retail: string, registration: mixed}
     */
    public function previewForCheckout(User $user, string $plainToken, string $expectedFqdn, ?int $expectedProductId = null): array
    {
        return DB::transaction(function () use ($user, $plainToken, $expectedFqdn, $expectedProductId) {
            return $this->validateQuoteForCheckout($user, $plainToken, $expectedFqdn, $expectedProductId, consume: false);
        });
    }

    /**
     * @return array{quote: DomainQuote, validated_retail: string, registration: mixed}
     */
    public function reserveForGateway(User $user, string $plainToken, string $expectedFqdn, int $orderId, ?int $expectedProductId = null): array
    {
        return DB::transaction(function () use ($user, $plainToken, $expectedFqdn, $orderId, $expectedProductId) {
            $validated = $this->validateQuoteForCheckout($user, $plainToken, $expectedFqdn, $expectedProductId, consume: false, expectedOrderId: $orderId);

            $quote = $validated['quote'];
            $quote->update([
                'reserved_at' => now(),
                'reserved_order_id' => $orderId,
            ]);

            $this->audit->log('domains.quote.reserved', $quote->fresh(), [
                'order_id' => $orderId,
            ], $user->id);

            return $validated;
        });
    }

    /**
     * @return array{quote: DomainQuote, validated_retail: string, registration: mixed}
     */
    public function consumeForPurchase(User $user, string $plainToken, string $expectedFqdn, ?int $expectedProductId = null, ?int $expectedOrderId = null): array
    {
        return DB::transaction(function () use ($user, $plainToken, $expectedFqdn, $expectedProductId, $expectedOrderId) {
            $validated = $this->validateQuoteForCheckout($user, $plainToken, $expectedFqdn, $expectedProductId, consume: true, expectedOrderId: $expectedOrderId);

            $quote = $validated['quote'];
            $quote->update([
                'consumed_at' => now(),
                'reserved_at' => null,
                'reserved_order_id' => null,
            ]);

            $this->audit->log('domains.quote.consumed', $quote->fresh(), [
                'order_id' => $expectedOrderId,
            ], $user->id);

            return [
                'quote' => $quote->fresh(),
                'validated_retail' => $validated['validated_retail'],
                'registration' => $validated['registration'],
            ];
        });
    }

    public function releaseReservationForOrder(int $orderId): void
    {
        DomainQuote::query()
            ->where('reserved_order_id', $orderId)
            ->whereNull('consumed_at')
            ->update([
                'reserved_at' => null,
                'reserved_order_id' => null,
            ]);
    }

    /**
     * @return array{quote: DomainQuote, validated_retail: string, registration: mixed}
     */
    private function validateQuoteForCheckout(
        User $user,
        string $plainToken,
        string $expectedFqdn,
        ?int $expectedProductId,
        bool $consume,
        ?int $expectedOrderId = null,
    ): array {
        $hash = hash('sha256', $plainToken);

        $quote = DomainQuote::query()
            ->where('token_hash', $hash)
            ->lockForUpdate()
            ->first();

        if (! $quote || $quote->user_id !== $user->id) {
            throw new InvalidArgumentException('Invalid or expired domain quote.');
        }

        if ($quote->isExpired()) {
            throw new InvalidArgumentException('Domain quote has expired. Please check availability again.');
        }

        if ($quote->isConsumed()) {
            throw new InvalidArgumentException('Domain quote has already been used.');
        }

        $normalized = DomainFqdn::normalizeFqdn($expectedFqdn);
        if ($quote->fqdn !== $normalized) {
            throw new InvalidArgumentException('Domain quote does not match the selected domain.');
        }

        if ($expectedProductId !== null && (int) $quote->platform_product_id !== $expectedProductId) {
            throw new InvalidArgumentException('Domain quote does not match this product.');
        }

        if (! $consume && $quote->reserved_order_id !== null && $expectedOrderId !== null && (int) $quote->reserved_order_id !== (int) $expectedOrderId) {
            throw new InvalidArgumentException('Domain quote is reserved for another checkout.');
        }

        if (! $consume && $quote->reserved_order_id !== null && $expectedOrderId === null) {
            throw new InvalidArgumentException('Domain quote is reserved for another checkout.');
        }

        return $this->validateLockedQuote($quote, $user);
    }

    /**
     * @return array{quote: DomainQuote, validated_retail: string, registration: mixed}
     */
    private function validateLockedQuote(DomainQuote $quote, User $user): array
    {
        if ($quote->isExpired()) {
            throw new InvalidArgumentException('Domain quote has expired. Please check availability again.');
        }

        $provider = $this->providers->providerRecord($quote->provider_key, requireEnabled: true);
        $adapter = $this->providers->adapterFor($provider);
        $availability = $adapter->checkAvailability($provider, $quote->fqdn);

        if (! $availability->available || ! $availability->isRegistration()) {
            throw new InvalidArgumentException('Domain is no longer available. Please search again.');
        }

        $registration = $adapter->getRegistrationQuote($provider, $quote->fqdn, $availability);
        $product = $quote->product ?? PlatformProduct::query()->findOrFail($quote->platform_product_id);
        $retail = $this->pricing->retailFromProviderCost(
            $registration->providerCost,
            $registration->providerCurrency,
            $product,
        );

        $tolerance = max(0, (float) config('domains.price_drift_tolerance_percent', 2));
        if (! $this->pricing->driftWithinTolerance((string) $quote->retail_price, $retail['retail_price'], $tolerance)) {
            $this->audit->log('domains.quote.drift_rejected', $quote, [
                'quoted' => (string) $quote->retail_price,
                'current' => $retail['retail_price'],
            ], $user->id);
            throw new InvalidArgumentException('Domain price changed. Please check availability again.');
        }

        return [
            'quote' => $quote,
            'validated_retail' => $retail['retail_price'],
            'registration' => $registration,
        ];
    }

    /**
     * @return list<array{tld: string, label: string}>
     */
    public function tldOptionsForUi(?PlatformProduct $product = null): array
    {
        return $this->tldOptionsScoped($product, 'all');
    }

    /**
     * @return list<array{tld: string, label: string}>
     */
    public function registryTldOptionsForUi(): array
    {
        return $this->mapRegistryTlds(null);
    }

    /**
     * @return list<array{tld: string, label: string}>
     */
    public function featuredTldOptionsForUi(PlatformProduct $product): array
    {
        return $this->tldOptionsScoped($product, 'featured');
    }

    /**
     * @return list<array{tld: string, label: string}>
     */
    public function advancedTldOptionsForUi(PlatformProduct $product): array
    {
        return $this->tldOptionsScoped($product, 'advanced');
    }

    /**
     * @return list<array{tld: string, label: string}>
     */
    private function tldOptionsScoped(?PlatformProduct $product, string $scope): array
    {
        if ($product === null) {
            return $this->mapRegistryTlds(null);
        }

        $allowed = DomainProductTldPolicy::allowedTlds($product);
        $scopeTlds = match ($scope) {
            'featured' => DomainProductTldPolicy::featuredTlds($product),
            'advanced' => DomainProductTldPolicy::advancedTlds($product),
            default => $allowed,
        };

        return $this->mapRegistryTlds($scopeTlds);
    }

    /**
     * @param  list<string>|null  $onlyTlds
     * @return list<array{tld: string, label: string}>
     */
    private function mapRegistryTlds(?array $onlyTlds): array
    {
        try {
            $tlds = $this->providers->mergedTldList();
        } catch (\Throwable) {
            return [];
        }

        if ($tlds === []) {
            return [];
        }

        $allowedSet = $onlyTlds !== null ? array_fill_keys($onlyTlds, true) : null;
        $common = config('domains.common_tlds', []);
        $preset = DomainProductTldPolicy::defaultFeaturedTlds();

        $sorted = collect($tlds)
            ->when($allowedSet !== null, fn ($collection) => $collection->filter(
                fn (DomainTld $row) => isset($allowedSet[$row->tld]),
            ))
            ->sortBy(function (DomainTld $row) use ($common, $preset) {
                $presetIndex = array_search($row->tld, $preset, true);
                if ($presetIndex !== false) {
                    return $presetIndex;
                }

                $index = array_search($row->tld, $common, true);

                return $index === false ? 1000 + ord($row->tld[0] ?? 'z') : 100 + $index;
            })
            ->values();

        return $sorted
            ->map(fn (DomainTld $row) => ['tld' => $row->tld, 'label' => $row->label()])
            ->all();
    }

    public function registrationProduct(): PlatformProduct
    {
        $slug = (string) config('domains.registration_product_slug', 'domain-registration');

        return PlatformProduct::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function cheapestRetailPrice(PlatformProduct $product): ?float
    {
        $cacheKey = 'domain.cheapest_retail.'.$product->id;
        $ttl = max(1, (int) config('domains.tld_cache_ttl_minutes', 60));

        $retail = Cache::remember($cacheKey, now()->addMinutes($ttl), function () use ($product) {
            try {
                $tlds = $this->providers->mergedTldList();
            } catch (\Throwable) {
                return null;
            }

            if ($tlds === []) {
                return null;
            }

            $cheapest = collect($tlds)->sortBy(fn (DomainTld $row) => $row->registrationCost)->first();

            if (! $cheapest instanceof DomainTld || ($cheapest->registrationCost ?? 0) <= 0) {
                return null;
            }

            try {
                $priced = $this->pricing->retailFromProviderCost(
                    $cheapest->registrationCost,
                    $cheapest->currency,
                    $product,
                );

                return (float) $priced['retail_price'];
            } catch (\Throwable) {
                return null;
            }
        });

        return is_numeric($retail) ? (float) $retail : null;
    }

    /**
     * @return array{tld: string, provider_cost: float, provider_currency: string, retail: float}|null
     */
    public function pricingFloorExample(PlatformProduct $product): ?array
    {
        try {
            $tlds = $this->providers->mergedTldList();
        } catch (\Throwable) {
            return null;
        }

        $cheapest = collect($tlds)->sortBy(fn (DomainTld $row) => $row->registrationCost)->first();

        if (! $cheapest instanceof DomainTld || ($cheapest->registrationCost ?? 0) <= 0) {
            return null;
        }

        try {
            $priced = $this->pricing->retailFromProviderCost(
                $cheapest->registrationCost,
                $cheapest->currency,
                $product,
            );

            return [
                'tld' => $cheapest->tld,
                'provider_cost' => $cheapest->registrationCost,
                'provider_currency' => $cheapest->currency,
                'retail' => (float) $priced['retail_price'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
