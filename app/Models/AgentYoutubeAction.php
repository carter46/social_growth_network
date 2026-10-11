<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "We accepted evidence of this action on this date." A record keeps blocking even if the
 * agent later unsubscribes, because unsubscribing and resubscribing is the fraud to prevent.
 */
class AgentYoutubeAction extends Model
{
    public const ACTION_SUBSCRIBE = 'subscribe';

    public const ACTION_LIKE = 'like';

    public const ACTION_COMMENT = 'comment';

    public const ACTION_WATCH = 'watch';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'agent_channel_id',
        'action',
        'target_channel_id',
        'target_video_id',
        'external_ref',
        'campaign_id',
        'campaign_participation_id',
        'evidence',
        'status',
        'verified_at',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
