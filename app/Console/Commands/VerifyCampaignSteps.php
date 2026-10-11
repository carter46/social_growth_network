<?php

namespace App\Console\Commands;

use App\Services\Campaigns\StepVerificationService;
use Illuminate\Console\Command;

class VerifyCampaignSteps extends Command
{
    protected $signature = 'campaigns:verify-steps {--limit=25}';

    protected $description = 'Re-check submitted bundled task steps (comments, subscriptions) that are due';

    public function handle(StepVerificationService $service): int
    {
        $n = $service->processDue(max(1, (int) $this->option('limit')));
        $this->info("Re-checked {$n} task(s).");

        return self::SUCCESS;
    }
}
