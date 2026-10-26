<?php

namespace App\Console\Commands;

use App\Services\Users\AccountLifecycleService;
use Illuminate\Console\Command;

class PurgeExpiredTrashCommand extends Command
{
    protected $signature = 'users:purge-expired-trash
                            {--limit= : Max accounts to process this run}';

    protected $description = 'Write historical records for long-trashed accounts, then anonymize soft-deleted tombstones (ledger-safe; not hard-deleted)';

    public function handle(AccountLifecycleService $lifecycle): int
    {
        $limit = $this->option('limit');
        $result = $lifecycle->purgeExpiredTrash(
            $limit !== null && $limit !== '' ? (int) $limit : null
        );

        $this->info("Purged {$result['purged']} account(s); skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
