<?php

namespace App\Modules\Wallet\Payments\Contracts;

use App\Modules\Wallet\Payments\Data\CheckoutRequest;
use App\Modules\Wallet\Payments\Data\CheckoutSession;
use App\Modules\Wallet\Payments\Data\PaymentEvent;

/**
 * Money in: hosted checkout for orders and wallet deposits.
 */
interface CollectionGateway extends GatewayDriver
{
    /**
     * Currencies this gateway can charge in for our merchant account.
     *
     * @return list<string>
     */
    public function currencies(): array;

    public function initializeCheckout(CheckoutRequest $request): CheckoutSession;

    /**
     * Ask the provider for the current state of a payment. Never trust a webhook body alone.
     *
     * @param  string  $reference  Our reference (DEP-…, PLT-PAY-…)
     * @param  string|null  $providerReference  The provider's id for the checkout, when we stored one
     */
    public function verify(string $reference, ?string $providerReference = null): PaymentEvent;
}
