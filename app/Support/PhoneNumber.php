<?php

namespace App\Support;

/**
 * Light E.164 checks for payout profile phone numbers (Bachs persons.phone).
 */
final class PhoneNumber
{
    /**
     * ISO-2 country → international dialing prefix (with leading +).
     * Covers payout-supported countries; extend as new countries are added.
     *
     * @var array<string, string>
     */
    private const DIAL_CODES = [
        'AT' => '+43',
        'BE' => '+32',
        'CA' => '+1',
        'CY' => '+357',
        'DE' => '+49',
        'EE' => '+372',
        'ES' => '+34',
        'FI' => '+358',
        'FR' => '+33',
        'GB' => '+44',
        'GH' => '+233',
        'GR' => '+30',
        'HR' => '+385',
        'IE' => '+353',
        'IT' => '+39',
        'KE' => '+254',
        'LT' => '+370',
        'LU' => '+352',
        'LV' => '+371',
        'MT' => '+356',
        'NG' => '+234',
        'NL' => '+31',
        'PT' => '+351',
        'RW' => '+250',
        'SI' => '+386',
        'SK' => '+421',
        'TZ' => '+255',
        'UG' => '+256',
        'US' => '+1',
        'ZA' => '+27',
    ];

    public static function isE164(?string $phone): bool
    {
        if ($phone === null || $phone === '') {
            return false;
        }

        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', trim($phone));
    }

    /** @return array<string, string> */
    public static function dialCodes(): array
    {
        return self::DIAL_CODES;
    }

    public static function dialCode(?string $country): ?string
    {
        if ($country === null || $country === '') {
            return null;
        }

        return self::DIAL_CODES[strtoupper($country)] ?? null;
    }

    /**
     * Build E.164 from a country dial code and a national number.
     * Strips spaces/dashes and a single leading trunk 0.
     */
    public static function compose(?string $country, ?string $nationalNumber): ?string
    {
        $dial = self::dialCode($country);
        if ($dial === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $nationalNumber) ?? '';
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return null;
        }

        $e164 = $dial.$digits;

        return self::isE164($e164) ? $e164 : null;
    }

    /**
     * Split a stored E.164 value into dial + national parts for the given country.
     *
     * @return array{0: string, 1: string} [dialCode, nationalNumber]
     */
    public static function split(?string $phone, ?string $country = null): array
    {
        $phone = trim((string) $phone);
        $dial = self::dialCode($country) ?? '';

        if ($phone !== '' && $dial !== '' && str_starts_with($phone, $dial)) {
            return [$dial, substr($phone, strlen($dial))];
        }

        if ($phone !== '' && str_starts_with($phone, '+')) {
            $matched = self::matchDialPrefix($phone);
            if ($matched !== null) {
                return [$matched, substr($phone, strlen($matched))];
            }
        }

        return [$dial, ltrim($phone, '+')];
    }

    private static function matchDialPrefix(string $phone): ?string
    {
        $best = null;
        foreach (self::DIAL_CODES as $code) {
            if (str_starts_with($phone, $code) && ($best === null || strlen($code) > strlen($best))) {
                $best = $code;
            }
        }

        return $best;
    }

    /** @return array<int, mixed> */
    public static function e164Rules(bool $required = true): array
    {
        $rules = [$required ? 'required' : 'nullable', 'string', 'max:20'];
        $rules[] = function (string $attribute, mixed $value, \Closure $fail) {
            if ($value === null || $value === '') {
                return;
            }
            if (! self::isE164((string) $value)) {
                $fail(__('Enter a valid phone number for your country.'));
            }
        };

        return $rules;
    }
}
