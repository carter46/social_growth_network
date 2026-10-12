<?php

namespace App\Console\Commands;

use App\Modules\Wallet\Services\LedgerReconciliationService;
use Illuminate\Console\Command;

/**
 * Read-only: compares each wallet's balance with its completed ledger entries and its locked
 * balance with its active holds. Reports differences; changes nothing.
 */
class WalletsReconcileLedgerCommand extends Command
{
    protected $signature = 'wallets:reconcile-ledger
        {--tolerance=0 : Ignore differences up to this amount}
        {--limit=50 : Rows to list}';

    protected $description = 'Read-only check that wallet balances and locks match the ledger and holds';

    public function handle(LedgerReconciliationService $ledger): int
    {
        $tolerance = is_numeric($this->option('tolerance')) ? (string) $this->option('tolerance') : '0';
        $rows = $ledger->mismatches($tolerance);

        if ($rows === []) {
            $this->info('Every wallet balance matches its ledger, and every locked balance matches its active holds.');

            return self::SUCCESS;
        }

        $this->warn(count($rows).' wallet(s) differ. Nothing was changed.');
        $this->table(
            ['Wallet', 'User', 'Balance', 'Ledger', 'Difference', 'Locked', 'Active holds', 'Lock difference'],
            array_map(fn ($r) => [
                $r['wallet_id'], $r['user_id'] ?? '-', $r['balance'], $r['ledger'], $r['residual'],
                $r['locked_balance'], $r['holds'], $r['lock_residual'],
            ], array_slice($rows, 0, max(1, (int) $this->option('limit')))),
        );

        return self::FAILURE;
    }
}
