<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletFundingSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $fundingId,
        public int $userId,
        public float $amount,
        public string $currency = '',
        public string $method = 'bank',
    ) {
        $this->currency = $this->currency ?: \App\Support\Money\Currency::current()->code;
    }
}
