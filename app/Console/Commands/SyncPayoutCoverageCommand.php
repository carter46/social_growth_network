<?php

namespace App\Console\Commands;

use App\Modules\Wallet\Services\PayoutCoverageService;
use Illuminate\Console\Command;

class SyncPayoutCoverageCommand extends Command
{
    protected $signature = 'payments:sync-coverage {--gateway= : Only this gateway key}';

    protected $description = 'Refresh payout coverage from each configured gateway\'s API';

    public function handle(PayoutCoverageService $coverage): int
    {
        $summary = $coverage->sync($this->option('gateway') ?: null);

        if ($summary === []) {
            $this->warn('No configured payout gateways.');

            return self::SUCCESS;
        }

        foreach ($summary as $key => $s) {
            $s['ok']
                ? $this->info($key.': '.$s['verified'].' verified, '.$s['removed'].' no longer reported')
                : $this->error($key.': sync failed, last verified state kept ('.$s['error'].')');
        }

        return self::SUCCESS;
    }
}
