<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignParticipation extends Model
{
    public const STATUS_STARTED = 'started';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_VERIFYING = 'verifying';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    public const STATUSES = [
        self::STATUS_STARTED,
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_VERIFYING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PAID,
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
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'verification_started_at' => 'datetime',
            'reward_amount' => 'decimal:2',
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
}
