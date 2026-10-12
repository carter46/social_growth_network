<?php

namespace App\Modules\Wallet\Payments\Data;

use App\Modules\Wallet\Payments\FailureClass;

/**
 * Outcome of a payout call or status check.
 */
final class PayoutResult
{
    public const SUCCEEDED = 'succeeded';

    public const PROCESSING = 'processing';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public const NOT_CREATED = 'not_created';

    public const UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureReason = null,
        public readonly ?string $failureClass = null,
        public readonly ?string $returnCharge = null,
        public readonly ?string $fee = null,
        public readonly array $raw = [],
        public readonly bool $requiresAuthorization = false,
        public readonly array $beneficiaryIds = [],
    ) {}

    public static function unknown(?string $reason = null, array $raw = [], ?string $providerReference = null): self
    {
        return new self(self::UNKNOWN, $providerReference, failureReason: $reason, raw: $raw);
    }

    /**
     * The status lookup reached the provider and it has no payout with this reference. Still
     * "unknown" for reconciliation (a new payout may not be listed yet); only fallback and
     * admin retries use it, after the provider has already refused the attempt.
     */
    public static function notFound(?string $reason = null, array $raw = []): self
    {
        return new self(self::UNKNOWN, null, failureReason: $reason, raw: $raw + ['lookup' => 'not_found']);
    }

    public function confirmedAbsent(): bool
    {
        return $this->status === self::UNKNOWN && ($this->raw['lookup'] ?? null) === 'not_found';
    }

    public static function notCreated(?string $code, ?string $reason, array $raw = []): self
    {
        return new self(self::NOT_CREATED, null, $code, $reason, FailureClass::NOT_CREATED, raw: $raw);
    }

    public static function failed(?string $code, ?string $reason, string $class, array $raw = [], ?string $providerReference = null, ?string $returnCharge = null): self
    {
        return new self(self::FAILED, $providerReference, $code, $reason, $class, $returnCharge, raw: $raw);
    }

    public function isLive(): bool
    {
        return in_array($this->status, [self::PENDING, self::PROCESSING, self::UNKNOWN], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::SUCCEEDED, self::FAILED, self::NOT_CREATED], true);
    }
}
