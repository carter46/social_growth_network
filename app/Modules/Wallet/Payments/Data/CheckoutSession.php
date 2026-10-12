<?php

namespace App\Modules\Wallet\Payments\Data;

use Carbon\CarbonInterface;

final class CheckoutSession
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $checkoutUrl,
        public readonly ?string $providerReference = null,
        public readonly ?CarbonInterface $expiresAt = null,
        public readonly array $raw = [],
    ) {}
}
