<?php

namespace App\Modules\Wallet\Payments\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Provider fee and FX for one destination. Amounts are decimal strings.
 */
final class PayoutQuote
{
    public const SOURCE_LIVE = 'live';

    public const SOURCE_PUBLISHED = 'published';

    public const SOURCE_VERIFIED_ENTRY = 'verified_entry';

    public function __construct(
        public readonly string $providerFee,
        public readonly string $feeCurrency,
        public readonly string $destinationCurrency,
        public readonly ?string $fxRate = null,
        public readonly ?string $destinationAmount = null,
        public readonly ?CarbonInterface $expiresAt = null,
        public readonly string $source = self::SOURCE_LIVE,
        public readonly ?string $quoteId = null,
        public readonly ?string $gatewayKey = null,
        public readonly ?CarbonInterface $quotedAt = null,
    ) {}

    public function withGateway(string $key): self
    {
        return new self(
            $this->providerFee, $this->feeCurrency, $this->destinationCurrency, $this->fxRate,
            $this->destinationAmount, $this->expiresAt, $this->source, $this->quoteId, $key,
            $this->quotedAt ?? CarbonImmutable::now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_fee' => $this->providerFee,
            'fee_currency' => $this->feeCurrency,
            'destination_currency' => $this->destinationCurrency,
            'fx_rate' => $this->fxRate,
            'destination_amount' => $this->destinationAmount,
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'source' => $this->source,
            'quote_id' => $this->quoteId,
            'gateway_key' => $this->gatewayKey,
            'quoted_at' => ($this->quotedAt ?? CarbonImmutable::now())->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            providerFee: (string) ($data['provider_fee'] ?? '0'),
            feeCurrency: (string) ($data['fee_currency'] ?? 'USD'),
            destinationCurrency: (string) ($data['destination_currency'] ?? 'USD'),
            fxRate: isset($data['fx_rate']) ? (string) $data['fx_rate'] : null,
            destinationAmount: isset($data['destination_amount']) ? (string) $data['destination_amount'] : null,
            expiresAt: ! empty($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : null,
            source: (string) ($data['source'] ?? self::SOURCE_LIVE),
            quoteId: $data['quote_id'] ?? null,
            gatewayKey: $data['gateway_key'] ?? null,
            quotedAt: ! empty($data['quoted_at']) ? CarbonImmutable::parse($data['quoted_at']) : null,
        );
    }
}
