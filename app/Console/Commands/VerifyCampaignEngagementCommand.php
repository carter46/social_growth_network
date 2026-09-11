<?php

namespace App\Console\Commands;

use App\Services\Campaigns\EngagementVerificationService;
use Illuminate\Console\Command;

class VerifyCampaignEngagementCommand extends Command
{
    protected $signature = 'campaigns:verify-engagement {--limit=5 : Max campaigns to touch per run}';

    protected $description = 'Single-flight count-change verification for likes/comments campaigns';

    public function handle(EngagementVerificationService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $n = $service->processBatch($limit);
        $this->info("Processed {$n} verification step(s).");

        return self::SUCCESS;
    }
}
