<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletDepositFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $fundingId,
        public float $amount,
        public string $reason = '',
        public string $currency = '',
    ) {
        $this->currency = $this->currency ?: \App\Support\Money\Currency::current()->code;
    }
}
