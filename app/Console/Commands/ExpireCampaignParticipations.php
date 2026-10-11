<?php

namespace App\Console\Commands;

use App\Services\Campaigns\ParticipationExpiryService;
use Illuminate\Console\Command;

class ExpireCampaignParticipations extends Command
{
    protected $signature = 'campaigns:expire-participations {--limit=200}';

    protected $description = 'Expire started tasks past their completion deadline (frees the slot) and refund cancelled campaigns';

    public function handle(ParticipationExpiryService $expiry): int
    {
        $n = $expiry->processOverdue(max(1, (int) $this->option('limit')));
        $this->info("Expired {$n} task(s).");

        return self::SUCCESS;
    }
}
