<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WithdrawalPayoutFailed
{
    use Dispatchable, SerializesModels;

    /** @param  'failed'|'expired'|'reversed'  $outcome */
    public function __construct(
        public int $withdrawalId,
        public int $userId,
        public float $amount,
        public string $outcome,
        public string $currency = '',
    ) {
        $this->currency = $this->currency ?: \App\Support\Money\Currency::current()->code;
    }
}
