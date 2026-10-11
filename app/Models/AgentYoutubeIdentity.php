<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The agent's linked YouTube channel. Linking proves ownership of a public channel, not account access. */
class AgentYoutubeIdentity extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REPLACED = 'replaced';

    public const STATUS_REJECTED = 'rejected';

    /** Change request waiting for admin approval; the current verified row stays active meanwhile. */
    public const STATUS_CHANGE_REQUESTED = 'change_req';

    public const METHOD_CODE = 'code';

    public const METHOD_OAUTH = 'oauth';

    public const METHOD_ADMIN = 'admin';

    public const CHECK_WINDOW_HOURS = 24;

    protected $fillable = [
        'user_id',
        'channel_id',
        'active_channel_id',
        'channel_url',
        'handle',
        'title',
        'thumbnail_url',
        'method',
        'status',
        'verification_code',
        'code_expires_at',
        'verified_at',
        'last_checked_at',
        'check_attempts',
        'oauth_token',
        'failure_reason',
        'reviewed_by',
    ];

    protected $hidden = ['oauth_token'];

    protected function casts(): array
    {
        return [
            'code_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'check_attempts' => 'integer',
            'oauth_token' => 'encrypted',
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

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending verification',
            self::STATUS_VERIFIED => 'Verified',
            self::STATUS_FAILED => 'Verification failed',
            self::STATUS_REPLACED => 'Replaced',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CHANGE_REQUESTED => 'Change requested',
            default => ucfirst((string) $this->status),
        };
    }
}
