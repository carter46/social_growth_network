<?php

namespace App\Support\Money;

final class Currency
{
    public function __construct(
        public readonly string $code,
        public readonly string $symbol,
        public readonly int $decimals,
        public readonly string $locale,
        public readonly string $name,
    ) {}

    public static function current(): self
    {
        return self::of((string) config('platform.currency', 'USD'));
    }

    public static function of(string $code): self
    {
        $code = strtoupper(trim($code)) ?: 'USD';
        $known = (array) config('platform.currencies.'.$code, []);

        return new self(
            $code,
            (string) ($known['symbol'] ?? $code.' '),
            (int) ($known['decimals'] ?? 2),
            (string) ($known['locale'] ?? 'en-US'),
            (string) ($known['name'] ?? $code),
        );
    }

    /**
     * @return array{code: string, symbol: string, decimals: int, locale: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'symbol' => $this->symbol,
            'decimals' => $this->decimals,
            'locale' => $this->locale,
        ];
    }
}
