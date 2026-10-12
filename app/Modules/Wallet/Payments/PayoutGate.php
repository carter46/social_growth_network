<?php

namespace App\Modules\Wallet\Payments;

use App\Models\SystemSetting;
use App\Support\Money\Currency;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Gateway payouts send wallet amounts as USD, so they stay closed until the site runs in USD,
 * an admin has verified the currency conversion, and an admin has switched payouts on.
 */
final class PayoutGate
{
    public const SETTING_ENABLED = 'payouts_enabled';

    public const SETTING_CUTOVER_VERIFIED_AT = 'currency_cutover_verified_at';

    public const SETTING_CUTOVER_VERIFIED_BY = 'currency_cutover_verified_by';

    public const PAYOUT_CURRENCY = 'USD';

    /** @return list<string> why payouts are closed; empty when open */
    public static function reasons(): array
    {
        $reasons = [];

        if (Currency::current()->code !== self::PAYOUT_CURRENCY) {
            $reasons[] = 'The site currency is '.Currency::current()->code.'. Gateway payouts only run once the site is in '.self::PAYOUT_CURRENCY.'.';
        }
        if (! self::cutoverVerifiedAt()) {
            $reasons[] = 'The '.self::PAYOUT_CURRENCY.' currency cutover has not been verified by an admin.';
        }
        if (! SystemSetting::enabled(self::SETTING_ENABLED, false)) {
            $reasons[] = 'Gateway payouts are switched off in Payment Settings.';
        }

        return $reasons;
    }

    public static function isOpen(): bool
    {
        return self::reasons() === [];
    }

    public static function assertOpen(): void
    {
        $reasons = self::reasons();
        if ($reasons !== []) {
            throw new InvalidArgumentException('Payouts are paused. '.implode(' ', $reasons));
        }
    }

    public static function cutoverVerifiedAt(): ?CarbonImmutable
    {
        $value = SystemSetting::get(self::SETTING_CUTOVER_VERIFIED_AT);

        return filled($value) ? CarbonImmutable::parse((string) $value) : null;
    }

    /** True when the wallet amounts of a withdrawal in $currency may be sent through a gateway. */
    public static function currencyAllowed(?string $currency): bool
    {
        return strtoupper((string) ($currency ?: Currency::current()->code)) === self::PAYOUT_CURRENCY;
    }
}
