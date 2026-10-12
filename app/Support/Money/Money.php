<?php

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * Decimal-string money arithmetic on bcmath. Amounts are never floats in storage.
 */
final class Money
{
    public static function ledgerScale(): int
    {
        return (int) config('platform.ledger_scale', 4);
    }

    public static function unitPriceScale(): int
    {
        return (int) config('platform.unit_price_scale', 6);
    }

    public static function cashScale(): int
    {
        return (int) config('platform.cash_scale', 2);
    }

    /**
     * Normalise any numeric input to a plain decimal string (no exponent, no grouping).
     */
    public static function normalize(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            $value = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        $value = trim((string) $value);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            if (is_numeric($value)) {
                return self::normalize((float) $value);
            }

            throw new InvalidArgumentException('Not a decimal amount: '.$value);
        }

        return $value;
    }

    /**
     * Round half-up (away from zero) to the given scale.
     */
    public static function round(string|int|float|null $value, ?int $scale = null): string
    {
        $scale ??= self::ledgerScale();
        $value = self::normalize($value);
        $negative = str_starts_with($value, '-');
        $abs = ltrim($value, '-');
        $half = '0.'.str_repeat('0', $scale).'5';
        $rounded = bcadd($abs, $half, $scale);
        if (bccomp($rounded, '0', $scale) === 0) {
            return bcadd('0', '0', $scale);
        }

        return $negative ? '-'.$rounded : $rounded;
    }

    public static function add(string|int|float|null $a, string|int|float|null $b, ?int $scale = null): string
    {
        $scale ??= self::ledgerScale();

        return bcadd(self::normalize($a), self::normalize($b), $scale);
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b, ?int $scale = null): string
    {
        $scale ??= self::ledgerScale();

        return bcsub(self::normalize($a), self::normalize($b), $scale);
    }

    public static function mul(string|int|float|null $a, string|int|float|null $b, ?int $scale = null): string
    {
        $scale ??= self::ledgerScale();

        return self::round(bcmul(self::normalize($a), self::normalize($b), $scale + 4), $scale);
    }

    public static function div(string|int|float|null $a, string|int|float|null $b, ?int $scale = null): string
    {
        $scale ??= self::ledgerScale();
        $divisor = self::normalize($b);
        if (bccomp($divisor, '0', 12) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return self::round(bcdiv(self::normalize($a), $divisor, $scale + 4), $scale);
    }

    public static function cmp(string|int|float|null $a, string|int|float|null $b, ?int $scale = null): int
    {
        $scale ??= self::ledgerScale();

        return bccomp(self::normalize($a), self::normalize($b), $scale);
    }

    public static function max(string|int|float|null ...$values): string
    {
        $best = null;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            $value = self::normalize($value);
            if ($best === null || bccomp($value, $best, 8) > 0) {
                $best = $value;
            }
        }

        return $best ?? '0';
    }

    public static function isPositive(string|int|float|null $value, ?int $scale = null): bool
    {
        return self::cmp($value, '0', $scale) > 0;
    }

    /**
     * Format for display, e.g. "$1,234.50". Shows at least $decimals and, when $maxDecimals is
     * larger, keeps up to that many significant decimals (rewards like $0.0025).
     */
    public static function format(
        string|int|float|null $amount,
        int $decimals = 2,
        ?int $maxDecimals = null,
        ?Currency $currency = null,
    ): string {
        $currency ??= Currency::current();
        $number = self::formatNumber($amount, $decimals, $maxDecimals);

        return str_starts_with($number, '-')
            ? '-'.$currency->symbol.substr($number, 1)
            : $currency->symbol.$number;
    }

    public static function formatNumber(string|int|float|null $amount, int $decimals = 2, ?int $maxDecimals = null): string
    {
        $maxDecimals = max($decimals, $maxDecimals ?? $decimals);
        $value = self::round($amount ?? 0, $maxDecimals);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        if (strlen($fraction) < $decimals) {
            $fraction = str_pad($fraction, $decimals, '0');
        }

        $whole = number_format((float) $whole, 0, '.', ',');
        if ($whole === '0' && $fraction === str_repeat('0', strlen($fraction))) {
            $negative = false;
        }

        return ($negative ? '-' : '').$whole.($fraction !== '' ? '.'.$fraction : '');
    }
}
