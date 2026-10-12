<?php

namespace App\Modules\Wallet\Payments\Data;

/**
 * One payout route a provider reports as available for our merchant account.
 */
final class CoverageRoute
{
    public function __construct(
        public readonly string $country,
        public readonly string $method,
        public readonly string $currency,
        public readonly bool $resolveSupported = false,
        public readonly ?string $providerMin = null,
        public readonly ?string $providerMax = null,
    ) {}
}
