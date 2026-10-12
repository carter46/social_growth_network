<?php

namespace App\Support\Payouts;

final class AccountFormat
{
    public static function normalizeIban(string $iban): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $iban));
    }

    public static function validIban(string $iban): bool
    {
        $iban = self::normalizeIban($iban);
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) (($remainder.$chunk) % 97);
        }

        return $remainder === 1;
    }

    public static function ibanCountry(string $iban): string
    {
        return substr(self::normalizeIban($iban), 0, 2);
    }

    public static function validAbaRouting(string $routing): bool
    {
        if (! preg_match('/^\d{9}$/', $routing)) {
            return false;
        }

        $d = array_map('intval', str_split($routing));
        $sum = 3 * ($d[0] + $d[3] + $d[6]) + 7 * ($d[1] + $d[4] + $d[7]) + ($d[2] + $d[5] + $d[8]);

        return $sum % 10 === 0;
    }

    public static function validSortCode(string $sortCode): bool
    {
        return (bool) preg_match('/^\d{6}$/', self::digits($sortCode));
    }

    public static function validSwift(string $swift): bool
    {
        return (bool) preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper(trim($swift)));
    }

    public static function validCanadianTransit(string $transit): bool
    {
        return (bool) preg_match('/^\d{5}$/', self::digits($transit));
    }

    public static function validCanadianInstitution(string $institution): bool
    {
        return (bool) preg_match('/^\d{3}$/', self::digits($institution));
    }

    public static function digits(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    public static function mask(string $value, int $visible = 4): string
    {
        $value = (string) preg_replace('/\s+/', '', $value);
        if ($value === '') {
            return '';
        }

        $len = strlen($value);
        if ($len <= $visible) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', min(6, $len - $visible)).substr($value, -$visible);
    }
}
