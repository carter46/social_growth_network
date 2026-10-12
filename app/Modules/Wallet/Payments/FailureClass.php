<?php

namespace App\Modules\Wallet\Payments;

/**
 * How a payout failure may be handled. See WithdrawalPayoutService for the single fallback rule.
 */
final class FailureClass
{
    /** The provider confirms no transfer exists. One automatic switch to the next gateway is allowed. */
    public const NOT_CREATED = 'not_created';

    /** Temporary provider or rail problem. An admin may authorise a retry on another gateway. */
    public const RECOVERABLE = 'recoverable';

    /** Wrong, closed or mismatched account. Never retried elsewhere; bounce fee may apply. */
    public const ACCOUNT_DETAILS = 'account_details';

    /** Unclassified. Treated like recoverable (admin decides), never an automatic switch or bounce fee. */
    public const OTHER = 'other';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::NOT_CREATED, self::RECOVERABLE, self::ACCOUNT_DETAILS, self::OTHER];
    }
}
