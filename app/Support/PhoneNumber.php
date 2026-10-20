<?php

namespace App\Support;

/**
 * Light E.164 checks for payout profile phone numbers (Bachs persons.phone).
 */
final class PhoneNumber
{
    public static function isE164(?string $phone): bool
    {
        if ($phone === null || $phone === '') {
            return false;
        }

        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', trim($phone));
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
                $fail(__('Enter a phone number with country code, like +15551234567.'));
            }
        };

        return $rules;
    }
}
