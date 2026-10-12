<?php

namespace App\Modules\Wallet\Payments\Data;

final class ResolvedAccount
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $accountName,
        public readonly string $gatewayKey,
        public readonly array $raw = [],
    ) {}
}
