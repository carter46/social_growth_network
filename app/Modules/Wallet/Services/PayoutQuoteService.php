<?php

namespace App\Modules\Wallet\Services;

use App\Models\PayoutFeeRule;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Payments\Data\PayoutQuote;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Provider fee for one destination: the gateway's live quote, else a verified (non-stale) fee entry.
 * Null means the fee is unknown and the method must be shown as unavailable.
 */
class PayoutQuoteService
{
    public function __construct(
        private GatewayRegistry $registry,
    ) {}

    /** First gateway on the route for this destination, or null when none is verified. */
    public function gatewayFor(PayoutAccountDetails $destination): ?string
    {
        $key = array_key_first($this->registry->payoutRoute($destination->country, $destination->method));
        if ($key !== null) {
            return $key;
        }

        // Active Connect recipient: allow Bachs even when coverage rows are not synced yet.
        if ($destination->userId !== null
            && \App\Models\PayoutConnection::for((int) $destination->userId, 'bachs')?->isActive()
            && $this->registry->payoutDriver('bachs')?->isConfigured()
            && config('payout_methods.countries.'.strtoupper($destination->country).'.methods.'.$destination->method) !== null) {
            return 'bachs';
        }

        return null;
    }

    public function quote(string $amount, PayoutAccountDetails $destination, ?string $gatewayKey = null): ?PayoutQuote
    {
        $gatewayKey ??= $this->gatewayFor($destination);
        if ($gatewayKey === null) {
            return null;
        }

        return $this->live($amount, $destination, $gatewayKey)
            ?? $this->fromVerifiedEntry($amount, $destination, $gatewayKey);
    }

    public function live(string $amount, PayoutAccountDetails $destination, string $gatewayKey, bool $useCache = true): ?PayoutQuote
    {
        $driver = $this->registry->payoutDriver($gatewayKey);
        if (! $driver || ! $driver->isConfigured()) {
            return null;
        }

        $amount = Money::round($amount, 2);
        $cacheKey = 'payout_quote:'.$gatewayKey.':'.$destination->country.':'.$destination->method.':'.$destination->currency.':'.$amount;
        $minutes = (int) config('payments.payout.quote_cache_minutes', 5);

        if ($useCache && ($cached = Cache::get($cacheKey)) !== null) {
            return $cached === false ? null : PayoutQuote::fromArray($cached);
        }

        try {
            $quote = $driver->quoteTransfer($amount, $destination)?->withGateway($gatewayKey);
        } catch (\Throwable $e) {
            Log::info('payments.quote.failed', ['gateway' => $gatewayKey, 'country' => $destination->country, 'error' => $e->getMessage()]);

            return null;
        }

        Cache::put($cacheKey, $quote?->toArray() ?? false, now()->addMinutes($minutes));

        return $quote;
    }

    public function fromVerifiedEntry(string $amount, PayoutAccountDetails $destination, string $gatewayKey): ?PayoutQuote
    {
        $rule = $this->verifiedRule($destination->country, $destination->method, $gatewayKey);
        if (! $rule) {
            return null;
        }

        return new PayoutQuote(
            providerFee: self::verifiedFee($rule, $amount),
            feeCurrency: 'USD',
            destinationCurrency: strtoupper($destination->currency),
            source: PayoutQuote::SOURCE_VERIFIED_ENTRY,
            gatewayKey: $gatewayKey,
            quotedAt: CarbonImmutable::now(),
        );
    }

    public function verifiedRule(string $country, string $method, ?string $gatewayKey): ?PayoutFeeRule
    {
        return PayoutFeeRule::bestMatch(
            $country,
            $method,
            $gatewayKey,
            fn (PayoutFeeRule $r) => $r->hasVerifiedProviderFee() && ! $r->verifiedFeeIsStale(),
        );
    }

    /** Verified flat + percent fee for an amount, rounded up to the cent. */
    public static function verifiedFee(PayoutFeeRule $rule, string $amount): string
    {
        $fee = Money::add(
            (string) ($rule->verified_provider_fee_flat ?? '0'),
            Money::div(Money::mul($amount, (string) ($rule->verified_provider_fee_percent ?? '0'), 8), '100', 8),
            8,
        );

        return self::ceilCents($fee);
    }

    public static function ceilCents(string $value): string
    {
        $value = Money::normalize($value);
        $floor = bcadd($value, '0', 2);

        return bccomp($value, $floor, 8) > 0 ? bcadd($floor, '0.01', 2) : $floor;
    }
}
