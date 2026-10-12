<?php

namespace App\Modules\Wallet\Payments\Data;

/**
 * A provider notification or verification result in provider-neutral form.
 */
final class PaymentEvent
{
    public const TYPE_COLLECTION = 'collection';

    public const TYPE_PAYOUT = 'payout';

    /** Connected-account changes (account, capability and transfer events). providerReference is the account id. */
    public const TYPE_CONNECT = 'connect';

    public const TYPE_IGNORED = 'ignored';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVERSED = 'reversed';

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $type,
        public readonly string $gateway,
        public readonly string $status,
        public readonly ?string $reference = null,
        public readonly ?string $providerReference = null,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $eventId = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureReason = null,
        public readonly array $raw = [],
    ) {}

    public static function ignored(string $gateway, ?string $eventId = null, array $raw = []): self
    {
        return new self(self::TYPE_IGNORED, $gateway, self::STATUS_PENDING, eventId: $eventId, raw: $raw);
    }

    public function succeeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    public function failed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_REVERSED], true);
    }
}
