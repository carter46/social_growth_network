<?php

namespace App\Observers;

use App\Models\Campaign;
use App\Services\Campaigns\CampaignStatusNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CampaignObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private CampaignStatusNotifier $notifier,
    ) {}

    public function created(Campaign $campaign): void
    {
        $this->notifier->notify($campaign, null);
    }

    public function updated(Campaign $campaign): void
    {
        if (! $campaign->wasChanged('status')) {
            return;
        }

        $this->notifier->notify($campaign, $campaign->getPrevious()['status'] ?? null);
    }
}
