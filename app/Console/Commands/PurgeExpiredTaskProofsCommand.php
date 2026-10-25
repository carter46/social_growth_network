<?php

namespace App\Console\Commands;

use App\Services\Media\TaskProofRetentionService;
use Illuminate\Console\Command;

class PurgeExpiredTaskProofsCommand extends Command
{
    protected $signature = 'proofs:purge-expired {--limit=200 : Max proof files to delete this run}';

    protected $description = 'Delete approved task / follow screenshot files whose 7-day retention has ended';

    public function handle(TaskProofRetentionService $retention): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $count = $retention->purgeDue($limit);
        $this->info("Purged {$count} expired task proof file(s).");

        return self::SUCCESS;
    }
}
