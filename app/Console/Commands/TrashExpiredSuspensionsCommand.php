<?php

namespace App\Console\Commands;

use App\Services\Users\AccountLifecycleService;
use Illuminate\Console\Command;

class TrashExpiredSuspensionsCommand extends Command
{
    protected $signature = 'users:trash-expired-suspensions
                            {--limit= : Max accounts to process this run}';

    protected $description = 'Move accounts suspended longer than the configured window into trash (OTP-reactivatable)';

    public function handle(AccountLifecycleService $lifecycle): int
    {
        $limit = $this->option('limit');
        $result = $lifecycle->trashExpiredSuspensions(
            $limit !== null && $limit !== '' ? (int) $limit : null
        );

        $this->info("Trashed {$result['trashed']} account(s); skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
