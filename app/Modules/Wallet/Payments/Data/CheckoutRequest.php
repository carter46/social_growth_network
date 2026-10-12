<?php

namespace App\Modules\Wallet\Payments\Data;

final class CheckoutRequest
{
    /**
     * @param  string  $amount  Decimal string, e.g. "42.00"
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $reference,
        public readonly string $customerEmail,
        public readonly string $customerName,
        public readonly string $callbackUrl,
        public readonly string $description,
        public readonly ?string $cancelUrl = null,
        public readonly int $expiresInMinutes = 40,
        public readonly array $metadata = [],
    ) {}
}
