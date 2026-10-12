<?php

namespace App\Modules\Wallet\Payments\Contracts;

use App\Modules\Wallet\Payments\Data\CoverageRoute;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Payments\Data\PayoutQuote;
use App\Modules\Wallet\Payments\Data\PayoutRequest;
use App\Modules\Wallet\Payments\Data\PayoutResult;
use App\Modules\Wallet\Payments\Data\ResolvedAccount;

/**
 * Money out: agent withdrawals. Amounts are sent in USD; the provider converts and delivers.
 */
interface PayoutGateway extends GatewayDriver
{
    /** @return list<string> ISO country codes */
    public function payoutCountries(): array;

    /** @return list<string> method keys from config/payout_methods.php */
    public function payoutMethods(string $country): array;

    /**
     * @return list<array{name: string, code: string}>
     */
    public function listBanks(string $country, string $method = 'bank_account'): array;

    public function supportsResolve(string $country, string $method): bool;

    public function resolveAccount(PayoutAccountDetails $account): ResolvedAccount;

    /**
     * Send money. Must never throw for a provider answer: every outcome is a PayoutResult.
     * A timeout or 5xx is PayoutResult::unknown(), never "failed".
     */
    public function initiateTransfer(PayoutRequest $request): PayoutResult;

    /**
     * @param  string  $reference  Our attempt reference (WPO-{withdrawal}-{attempt})
     */
    public function transferStatus(string $reference, ?string $providerReference = null): PayoutResult;

    /** Available balance in the given currency, or null when the provider doesn't expose one. */
    public function balance(string $currency = 'USD'): ?string;

    /** Live fee and FX quote for this destination, or null when the provider has no quote API. */
    public function quoteTransfer(string $amount, PayoutAccountDetails $destination): ?PayoutQuote;

    /**
     * What the provider's API reports for our merchant account.
     *
     * @return list<CoverageRoute>
     */
    public function coverage(): array;

    /**
     * Map a provider failure code to a FailureClass constant. Only documented codes may be
     * not_created or account_details; anything unknown must be "other".
     */
    public function failureClass(?string $code): string;
}
