<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignWatchSession extends Model
{
    protected $fillable = [
        'campaign_participation_id',
        'token',
        'required_seconds',
        'started_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'required_seconds' => 'integer',
            'started_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(CampaignParticipation::class, 'campaign_participation_id');
    }

    public function isClaimable(): bool
    {
        if ($this->claimed_at) {
            return false;
        }

        return $this->started_at
            && $this->started_at->copy()->addSeconds($this->required_seconds)->lte(now());
    }
}
