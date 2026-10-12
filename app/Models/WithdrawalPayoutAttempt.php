<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request to one provider for one withdrawal. The reference is sent as the provider's
 * idempotency / merchant reference and reused for every status check.
 */
class WithdrawalPayoutAttempt extends Model
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const NOT_CREATED = 'not_created';

    public const UNKNOWN = 'unknown';

    public const LIVE_STATUSES = [self::PENDING, self::PROCESSING, self::UNKNOWN];

    public const TRIGGER_INITIAL = 'initial';

    public const TRIGGER_AUTOMATIC_FALLBACK = 'automatic_fallback';

    public const TRIGGER_ADMIN_RETRY = 'admin_retry';

    protected $fillable = [
        'withdrawal_id',
        'attempt_no',
        'gateway_key',
        'reference',
        'provider_reference',
        'status',
        'live_flag',
        'trigger',
        'failure_code',
        'failure_reason',
        'failure_class',
        'recoverable',
        'amount',
        'return_charge',
        'provider_fee_charged',
        'reclaim_pending',
        'last_checked_at',
        'request_snapshot',
        'response_snapshot',
        'quote_snapshot',
        'authorised_by',
        'authorised_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'live_flag' => 'integer',
            'recoverable' => 'boolean',
            'amount' => 'decimal:2',
            'return_charge' => 'decimal:2',
            'provider_fee_charged' => 'decimal:4',
            'reclaim_pending' => 'boolean',
            'last_checked_at' => 'datetime',
            'request_snapshot' => 'array',
            'response_snapshot' => 'array',
            'quote_snapshot' => 'array',
            'authorised_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public static function referenceFor(int $withdrawalId, int $attemptNo): string
    {
        return 'WPO-'.$withdrawalId.'-'.$attemptNo;
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }

    public function authoriser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorised_by');
    }

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE_STATUSES, true);
    }

    public function isClosedWithoutPayment(): bool
    {
        return in_array($this->status, [self::FAILED, self::NOT_CREATED], true);
    }

    /**
     * Keep live_flag in step with status so the unique index allows one live attempt.
     */
    protected static function booted(): void
    {
        static::saving(function (self $attempt) {
            $attempt->live_flag = in_array($attempt->status, self::LIVE_STATUSES, true) ? 1 : null;
        });
    }
}
