<?php

namespace App\Modules\Wallet\Payments\Contracts;

use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;

/**
 * Gateways that register a payee before money moves (and may need to review it).
 * Called when an agent adds a payout account so any review runs before the first withdrawal.
 */
interface PreparesBeneficiaries
{
    /**
     * @return array{ids: array<string, mixed>, usable: bool, status: string|null, message: string|null}
     *
     * @throws \App\Modules\Wallet\Payments\Exceptions\GatewayException
     */
    public function prepareBeneficiary(PayoutAccountDetails $destination): array;
}
