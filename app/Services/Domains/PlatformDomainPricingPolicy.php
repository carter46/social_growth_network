<?php

namespace App\Services\Domains;

use App\Models\PlatformProduct;
use App\Services\Domains\Exceptions\DomainBusinessException;

class PlatformDomainPricingPolicy
{
    private const FX_SCALE = 4;

    private const RETAIL_SCALE = 2;

    /**
     * Provider cost plus markup, in the site currency, rounded up to the cent.
     *
     * @return array{retail_price: string, retail_currency: string, markup_percent: float, fx_rate: float|null}
     */
    public function retailFromProviderCost(float|string $providerCost, string $providerCurrency, PlatformProduct $product): array
    {
        $cost = $this->normalizeCost($providerCost);

        if (bccomp($cost, '0', self::FX_SCALE) <= 0) {
            throw new DomainBusinessException('Invalid provider cost.');
        }

        $meta = $product->meta ?? [];
        $markupRaw = $meta['domain_markup_percent'] ?? 0;
        if (! is_numeric($markupRaw)) {
            throw new DomainBusinessException('Invalid domain markup configuration.');
        }

        $markup = (string) $markupRaw;
        if (bccomp($markup, '0', self::FX_SCALE) < 0) {
            throw new DomainBusinessException('Invalid domain markup configuration.');
        }

        $siteCurrency = strtoupper((string) config('platform.currency', 'USD'));
        $providerCurrency = strtoupper($providerCurrency);
        $fxRate = null;

        if ($providerCurrency === $siteCurrency) {
            $siteCost = $cost;
        } elseif ($providerCurrency === 'USD' && $siteCurrency === 'NGN') {
            // Only used before the currency cutover, while the site still shows NGN.
            $fx = $meta['domain_fx_policy'] ?? [];
            $rate = isset($fx['usd_ngn_rate']) ? (string) $fx['usd_ngn_rate'] : '0';
            if (bccomp($rate, '0', self::FX_SCALE) <= 0) {
                throw new DomainBusinessException('USD to NGN rate is not configured.');
            }
            $siteCost = bcmul($cost, $rate, self::FX_SCALE);
            $fxRate = (float) $rate;
        } else {
            throw new DomainBusinessException('Unsupported provider currency.');
        }

        $markupFactor = bcadd('1', bcdiv($markup, '100', self::FX_SCALE), self::FX_SCALE);
        $retailExact = bcmul($siteCost, $markupFactor, self::FX_SCALE);

        return [
            'retail_price' => $this->ceilToCent($retailExact),
            'retail_currency' => $siteCurrency,
            'markup_percent' => (float) $markup,
            'fx_rate' => $fxRate,
        ];
    }

    public function priceDriftPercent(string $quotedRetail, string $currentRetail): string
    {
        if (bccomp($quotedRetail, '0', self::RETAIL_SCALE) <= 0) {
            return '100';
        }

        $diff = bcsub($currentRetail, $quotedRetail, self::RETAIL_SCALE);
        if (bccomp($diff, '0', self::RETAIL_SCALE) < 0) {
            $diff = bcmul($diff, '-1', self::RETAIL_SCALE);
        }

        return bcmul(bcdiv($diff, $quotedRetail, self::FX_SCALE), '100', self::FX_SCALE);
    }

    public function driftWithinTolerance(string $quotedRetail, string $currentRetail, float|string $tolerancePercent): bool
    {
        $drift = $this->priceDriftPercent($quotedRetail, $currentRetail);

        return bccomp($drift, (string) $tolerancePercent, self::FX_SCALE) <= 0;
    }

    private function normalizeCost(float|string $providerCost): string
    {
        if (is_float($providerCost) && (! is_finite($providerCost) || $providerCost <= 0)) {
            throw new DomainBusinessException('Invalid provider cost.');
        }

        return number_format((float) $providerCost, self::FX_SCALE, '.', '');
    }

    private function ceilToCent(string $amount): string
    {
        $cents = bcmul($amount, '100', self::FX_SCALE);
        [$whole, $fraction] = array_pad(explode('.', $cents, 2), 2, '');
        if (rtrim($fraction, '0') !== '') {
            $whole = bcadd($whole, '1', 0);
        }

        return bcdiv($whole, '100', self::RETAIL_SCALE);
    }
}
