<?php

namespace App\Console\Commands;

use App\Services\Campaigns\YoutubeIdentityService;
use Illuminate\Console\Command;

class CheckYoutubeIdentityCodes extends Command
{
    protected $signature = 'youtube:check-identity-codes {--limit=50}';

    protected $description = 'Look for pending verification codes in agents\' channel descriptions; fail them after 24 hours';

    public function handle(YoutubeIdentityService $identities): int
    {
        $n = $identities->runScheduledChecks(max(1, (int) $this->option('limit')));
        $this->info("Checked {$n} pending channel(s).");

        return self::SUCCESS;
    }
}
