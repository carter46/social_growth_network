<?php

namespace App\Modules\Wallet\Payments\Contracts;

use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;

/**
 * Gateways that fund an intermediate balance before paying out. After a confirmed failure
 * the returned funds are moved back to the platform balance.
 */
interface ReclaimsFailedPayouts
{
    /** True when the funds are back on the platform balance (or there was nothing to move). */
    public function reclaimFailedPayout(string $reference, PayoutAccountDetails $destination): bool;
}
