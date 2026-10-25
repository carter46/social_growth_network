<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentSocialFollowSubmission extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'status',
        'started_at',
        'expires_at',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(AgentSocialFollowProof::class, 'submission_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function canEditProofs(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_REJECTED, self::STATUS_EXPIRED], true);
    }

    public function isTimedRunActive(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->started_at !== null
            && $this->expires_at !== null;
    }

    public function secondsRemaining(): ?int
    {
        if (! $this->isTimedRunActive()) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    public function totalSeconds(): ?int
    {
        if ($this->started_at === null || $this->expires_at === null) {
            return null;
        }

        return max(0, (int) $this->started_at->diffInSeconds($this->expires_at, false));
    }

    /** Minutes allowed by number of social accounts to follow. */
    public static function completionMinutesForCount(int $count): int
    {
        return match (true) {
            $count <= 1 => 3,
            $count === 2 => 5,
            $count === 3 => 7,
            default => 10,
        };
    }

    public function label(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_SUBMITTED => 'Under review',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_EXPIRED => 'Expired',
            default => 'In progress',
        };
    }
}
