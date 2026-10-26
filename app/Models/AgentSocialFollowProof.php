<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSocialFollowProof extends Model
{
    protected $fillable = [
        'submission_id',
        'social_link_id',
        'platform',
        'url',
        'icon_media_id',
        'proof_url',
        'proof_purge_at',
        'sort_order',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'social_link_id' => 'integer',
            'icon_media_id' => 'integer',
            'sort_order' => 'integer',
            'completed_at' => 'datetime',
            'proof_purge_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AgentSocialFollowSubmission::class, 'submission_id');
    }

    public function isComplete(): bool
    {
        return filled($this->proof_url) && $this->completed_at !== null;
    }

    public function displayName(): string
    {
        return ucfirst((string) $this->platform);
    }

    /** YouTube uses subscribe language; every other network uses follow. */
    public function isSubscribePlatform(): bool
    {
        return in_array(strtolower((string) $this->platform), ['youtube', 'yt'], true);
    }

    public function actionVerb(): string
    {
        return $this->isSubscribePlatform() ? 'Subscribe' : 'Follow';
    }

    public function shortLabel(): string
    {
        return $this->isSubscribePlatform()
            ? 'Subscribe'
            : 'Follow '.$this->displayName();
    }

    public function instruction(): string
    {
        $name = $this->displayName();

        if ($this->isSubscribePlatform()) {
            return "Subscribe to our {$name} channel, then upload a screenshot that shows you subscribed.";
        }

        return "Follow us on {$name}, then upload a screenshot that shows you followed.";
    }

    public function openButtonLabel(): string
    {
        return $this->isSubscribePlatform()
            ? 'Subscribe on YouTube'
            : 'Open '.$this->displayName();
    }

    public function logoUrl(): ?string
    {
        return $this->icon_media_id ? media_url_from_id((int) $this->icon_media_id) : null;
    }
}
