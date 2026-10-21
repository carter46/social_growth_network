<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignParticipation extends Model
{
    public const STATUS_STARTED = 'started';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_VERIFYING = 'verifying';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    /** The completion deadline passed before the agent submitted. The slot is freed, and the same agent may start again while a place is still open. */
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_STARTED,
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_VERIFYING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PAID,
        self::STATUS_EXPIRED,
    ];

    public const FINAL_STATUSES = [
        self::STATUS_REJECTED,
        self::STATUS_PAID,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'campaign_id',
        'agent_id',
        'status',
        'proof_url',
        'proof_notes',
        'pre_count',
        'post_count',
        'verify_attempts',
        'verification_started_at',
        'started_at',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'paid_at',
        'reward_amount',
        'expires_at',
        'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'verification_started_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'reward_amount' => 'decimal:4',
            'pre_count' => 'integer',
            'post_count' => 'integer',
            'verify_attempts' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(CampaignParticipationTask::class, 'participation_id')->orderBy('position');
    }

    /** Deadline passed while the agent was still working (review time never counts). */
    public function isPastDeadline(): bool
    {
        return $this->status === self::STATUS_STARTED
            && $this->expires_at !== null
            && now()->greaterThanOrEqualTo($this->expires_at);
    }

    public function secondsRemaining(): ?int
    {
        if ($this->status !== self::STATUS_STARTED || $this->expires_at === null) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}
