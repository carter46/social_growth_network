<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WithdrawalRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $withdrawalId,
        public int $userId,
        public float $amount,
        public string $currency = '',
        public ?string $reason = null,
    ) {
        $this->currency = $this->currency ?: \App\Support\Money\Currency::current()->code;
    }
}
