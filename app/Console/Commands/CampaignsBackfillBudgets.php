<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Campaigns\CampaignBudgetService;
use Illuminate\Console\Command;

class CampaignsBackfillBudgets extends Command
{
    protected $signature = 'campaigns:backfill-budgets {--dry-run : List what would be held without moving funds}';

    protected $description = 'Hold the remaining agent budget for active campaigns created before budget holds existed';

    public function handle(CampaignBudgetService $budgets, WalletService $wallets): int
    {
        if (! CampaignBudgetService::enabled()) {
            $this->error('Run the migrations first (campaign_budgets table is missing).');

            return self::FAILURE;
        }

        $rows = $budgets->backfillCandidates();
        if ($rows === []) {
            $this->info('No legacy campaigns need a budget hold.');

            return self::SUCCESS;
        }

        $total = array_reduce($rows, fn (string $sum, array $row) => bcadd($sum, $row['amount'], 2), '0.00');

        $this->table(
            ['Campaign', 'Title', 'Remaining units', 'Amount to hold'],
            array_map(fn (array $row) => [$row['campaign_id'], $row['title'], $row['units'], $row['amount']], $rows),
        );

        $platform = $wallets->getPlatformWallet()->fresh();
        $available = number_format((float) $platform->availableBalance(), 2, '.', '');
        $this->line("Total to hold: {$total}. Platform wallet available: {$available}.");

        if (bccomp($available, $total, 2) < 0) {
            $this->error('Refusing to run: the platform wallet available balance is lower than the total to hold.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run only. Nothing was held.');

            return self::SUCCESS;
        }

        $done = 0;
        foreach ($rows as $row) {
            $campaign = Campaign::query()->find($row['campaign_id']);
            if (! $campaign || $campaign->budget()->exists()) {
                continue;
            }
            $budgets->backfill($campaign, $row['units']);
            $done++;
        }

        $this->info("Held budgets for {$done} campaign(s).");

        return self::SUCCESS;
    }
}
