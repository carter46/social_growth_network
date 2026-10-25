<?php

namespace App\Models;

use App\Enums\CampaignTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of a bundled task. "done" means the agent finished it and saved evidence;
 * only "accepted" counts toward payment, and that is reached only after final submission.
 */
class CampaignParticipationTask extends Model
{
    public const STATUS_LOCKED = 'locked';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DONE = 'done';

    public const STATUS_CHECKING = 'checking';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LABELS = [
        self::STATUS_LOCKED => 'Locked',
        self::STATUS_ACTIVE => 'Current step',
        self::STATUS_DONE => 'Done, not yet submitted',
        self::STATUS_CHECKING => 'Being checked',
        self::STATUS_VERIFIED => 'Verified',
        self::STATUS_NEEDS_REVIEW => 'Awaiting admin review',
        self::STATUS_ACCEPTED => 'Accepted',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_EXPIRED => 'Expired',
    ];

    protected $table = 'campaign_participation_tasks';

    protected $fillable = [
        'participation_id',
        'campaign_id',
        'task',
        'position',
        'status',
        'evidence_method',
        'proof_url',
        'proof_purge_at',
        'proof_notes',
        'proof_meta',
        'done_at',
        'checked_at',
        'next_check_at',
        'accepted_at',
        'reviewed_by',
        'rejection_reason',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'attempts' => 'integer',
            'proof_meta' => 'array',
            'done_at' => 'datetime',
            'checked_at' => 'datetime',
            'next_check_at' => 'datetime',
            'accepted_at' => 'datetime',
            'proof_purge_at' => 'datetime',
        ];
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(CampaignParticipation::class, 'participation_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function taskEnum(): ?CampaignTask
    {
        return CampaignTask::tryFrom((string) $this->task);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }
}
