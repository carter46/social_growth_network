<?php

namespace App\Modules\Wallet\Payments\Data;

final class PayoutRequest
{
    /**
     * @param  string  $amount  Net amount in $currency (USD) as a decimal string
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $amount,
        public readonly PayoutAccountDetails $destination,
        public readonly string $narration,
        public readonly string $currency = 'USD',
        public readonly ?PayoutQuote $quote = null,
    ) {}
}
