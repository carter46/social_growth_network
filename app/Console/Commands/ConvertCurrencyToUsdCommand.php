<?php

namespace App\Console\Commands;

use App\Enums\TransactionType;
use App\Models\CurrencyConversionArchive;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Support\Money\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One-time NGN to USD conversion of every stored amount. See the cutover runbook:
 * settle open payments first, enable maintenance mode, back up, dry run, then run.
 */
class ConvertCurrencyToUsdCommand extends Command
{
    protected $signature = 'currency:convert-to-usd
        {--rate= : Naira per US dollar, e.g. 1550}
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Convert every stored NGN amount to USD at one rate, with an archive of the original values';

    /** table => column => scale */
    private const AMOUNT_COLUMNS = [
        'wallets' => ['balance' => 4, 'locked_balance' => 4],
        'wallet_holds' => ['amount' => 4],
        'transactions' => ['amount' => 4],
        'wallet_fundings' => ['amount' => 2],
        'withdrawals' => [
            'amount' => 2, 'fee_amount' => 2, 'provider_fee' => 2, 'platform_fee' => 2,
            'net_amount' => 2, 'absorbed_fee' => 2, 'bounce_fee_amount' => 2,
        ],
        'withdrawal_payout_attempts' => ['amount' => 2, 'return_charge' => 2],
        'orders' => ['amount' => 2, 'total_amount' => 2],
        'order_items' => ['unit_price' => 6, 'line_total' => 2],
        'platform_product_variants' => ['price' => 2, 'unit_price' => 6],
        'platform_products' => ['base_price' => 2, 'agent_reward_per_completion' => 2],
        'campaigns' => ['locked_creator_price' => 6, 'locked_agent_reward' => 4],
        'campaign_participations' => ['reward_amount' => 4],
        'campaign_budgets' => ['funded_amount' => 4, 'paid_amount' => 4, 'refunded_amount' => 4, 'released_amount' => 4],
        'referral_commissions' => ['base_amount' => 4, 'amount' => 4],
        'domain_quotes' => ['retail_price' => 2],
        'escrows' => ['amount' => 2, 'refund_amount' => 2],
        'listings' => ['price' => 2],
        'listing_versions' => ['price' => 2],
        'payout_accounts' => ['bounce_fee_disclosed' => 2],
        // Entered in the site currency on the Fees & Limits page.
        'payout_fee_rules' => [
            'flat_fee' => 2, 'min_fee' => 2, 'max_fee' => 2, 'verified_provider_fee_flat' => 2,
            'min_withdrawal' => 2, 'max_withdrawal' => 2,
        ],
    ];

    /** Money keys inside JSON columns: table => column => [key => scale] */
    private const JSON_COLUMNS = [
        'order_items' => ['options' => ['unit_price' => 6, 'line_total' => 2, 'retail_price' => 2, 'price' => 2, 'total' => 2, 'pricing_price' => 2]],
        'campaigns' => ['meta' => ['funding_shortfall' => 4]],
    ];

    /** Nested money keys inside JSON columns (dot paths): table => column => [path => scale] */
    private const JSON_NESTED_COLUMNS = [
        'campaigns' => ['meta' => ['options.unit_price' => 6, 'options.pricing_price' => 2, 'options.line_total' => 2]],
    ];

    /** First key is the current name; the rest are older names for the same figure. */
    private const MONEY_SNAPSHOT_KEYS = ['sales.total', 'sales.total_ngn'];

    /** Revenue snapshots stored as money; converted in place. */
    private const MONEY_VALUE_SNAPSHOT_KEYS = ['revenue.today', 'revenue.daily'];

    private const CURRENCY_COLUMNS = ['wallets', 'wallet_fundings', 'withdrawals', 'transactions'];

    /** Money-looking columns left alone on purpose: percentages, or amounts already in the provider's currency. */
    private const NOT_CONVERTED = [
        'payout_fee_rules.percent_fee', 'payout_fee_rules.verified_provider_fee_percent',
        'payout_coverage.provider_min', 'payout_coverage.provider_max',
        'domain_quotes.provider_cost',
    ];

    /** Crypto and treasury tables hold coin or provider amounts, not wallet money. */
    private const NOT_CONVERTED_TABLES = [
        'crypto_deposit_wallets', 'incoming_crypto_transactions', 'crypto_sell_requests',
        'exchange_rates', 'exchange_rate_history', 'otc_pricing_settings', 'wallet_balance_history',
        'currency_conversion_archive',
    ];

    private const SETTINGS = [
        'deposit_min_amount', 'deposit_max_amount',
        'withdrawal_min_amount', 'withdrawal_max_amount',
        'services_budget_low', 'services_budget_high',
        'withdrawal_bounce_fee',
    ];

    private string $rate;

    private string $batch;

    private bool $dryRun;

    /** @var array<int, array{old: string, new: string}> wallet_id => ledger sums */
    private array $ledger = [];

    /** @var array<int, array{old: string, new: string, user_id: int|null}> */
    private array $balances = [];

    /** @var list<array{0: string, 1: int, 2: int, 3: string, 4: string, 5: string}> */
    private array $report = [];

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $largestDiffs = [];

    public function handle(): int
    {
        $rate = (string) $this->option('rate');
        if (! is_numeric($rate) || (float) $rate <= 0) {
            $this->error('Give the rate in naira per US dollar, e.g. --rate=1550.');

            return self::FAILURE;
        }
        $this->rate = Money::normalize($rate);
        $this->dryRun = (bool) $this->option('dry-run');
        $this->batch = 'usd-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6));

        if (! $this->dryRun && Schema::hasTable('currency_conversion_archive') && CurrencyConversionArchive::query()->exists()) {
            $this->error('A conversion has already been recorded in currency_conversion_archive. Refusing to convert twice.');

            return self::FAILURE;
        }

        $blockers = $this->preChecks();
        if ($blockers !== []) {
            $this->error('Settle or cancel these first:');
            foreach ($blockers as $line) {
                $this->line('  - '.$line);
            }

            return self::FAILURE;
        }

        if (! $this->dryRun && ! app()->isDownForMaintenance()) {
            $this->error('Turn on maintenance mode (php artisan down) and take a backup before converting.');

            return self::FAILURE;
        }

        $this->info(($this->dryRun ? '[dry run] ' : '').'Converting at '.$this->rate.' NGN per USD, batch '.$this->batch);

        $run = function () {
            foreach (self::AMOUNT_COLUMNS as $table => $columns) {
                $this->convertTable($table, $columns);
            }
            foreach (self::JSON_COLUMNS as $table => $columns) {
                $this->convertJson($table, $columns);
            }
            foreach (self::JSON_NESTED_COLUMNS as $table => $columns) {
                $this->convertJson($table, $columns, true);
            }
            $this->convertSnapshots();
            $this->convertValueSnapshots();
            $this->convertSettings();
            $this->setCurrencyColumns();
            $this->reconcileLedger();
        };

        if ($this->dryRun) {
            $run();
        } else {
            DB::transaction($run);
        }

        $this->printReport();

        if ($this->dryRun) {
            $this->warn('Dry run: nothing was written.');
        } else {
            $this->info('Done. Set PLATFORM_CURRENCY=USD, clear the config cache, then follow the rest of the runbook.');
        }

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function preChecks(): array
    {
        $blockers = [];

        if (Schema::hasTable('orders')) {
            $orders = DB::table('orders')->whereIn('status', ['pending', 'processing'])->pluck('id');
            if ($orders->isNotEmpty()) {
                $blockers[] = $orders->count().' pending order(s): '.$orders->take(20)->implode(', ');
            }
        }

        if (Schema::hasTable('wallet_fundings')) {
            $fundings = DB::table('wallet_fundings')->whereIn('status', ['pending', 'processing'])->pluck('id');
            if ($fundings->isNotEmpty()) {
                $blockers[] = $fundings->count().' pending wallet funding(s): '.$fundings->take(20)->implode(', ');
            }
        }

        if (Schema::hasTable('withdrawals')) {
            $withdrawals = DB::table('withdrawals')->whereNotIn('status', ['completed', 'rejected', 'failed', 'cancelled'])->pluck('id');
            if ($withdrawals->isNotEmpty()) {
                $blockers[] = $withdrawals->count().' open withdrawal(s): '.$withdrawals->take(20)->implode(', ');
            }
        }

        if (Schema::hasTable('payment_webhooks')) {
            $webhooks = DB::table('payment_webhooks')->whereIn('status', ['received', 'failed'])->pluck('id');
            if ($webhooks->isNotEmpty()) {
                $blockers[] = $webhooks->count().' unprocessed payment webhook(s): '.$webhooks->take(20)->implode(', ');
            }
        }

        return $blockers;
    }

    /**
     * @param  array<string, int>  $columns
     */
    private function convertTable(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $columns = array_filter($columns, fn ($scale, $column) => Schema::hasColumn($table, $column), ARRAY_FILTER_USE_BOTH);
        if ($columns === []) {
            return;
        }

        $extra = match ($table) {
            'transactions' => Schema::hasColumn('transactions', 'wallet_id') ? ['wallet_id'] : [],
            'wallets' => ['user_id'],
            default => [],
        };

        $stats = [];
        foreach ($columns as $column => $scale) {
            $stats[$column] = ['rows' => 0, 'old' => '0', 'new' => '0'];
        }

        DB::table($table)->select(array_merge(['id'], array_keys($columns), $extra))->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $columns, &$stats) {
                $archive = [];
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $column => $scale) {
                        $old = $row->{$column};
                        if ($old === null) {
                            continue;
                        }
                        $old = Money::normalize((string) $old);
                        $new = $this->convert($old, $scale);
                        $updates[$column] = $new;
                        $stats[$column]['rows']++;
                        $stats[$column]['old'] = bcadd($stats[$column]['old'], $old, 8);
                        $stats[$column]['new'] = bcadd($stats[$column]['new'], $new, 8);
                        $this->trackDiff($table.'.'.$column.' #'.$row->id, $old, $new);
                        $archive[] = $this->archiveRow($table, (int) $row->id, $column, $old, $new);
                    }

                    $this->trackLedger($table, $row, $updates);

                    if (! $this->dryRun && $updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
                $this->writeArchive($archive);
            });

        foreach ($stats as $column => $s) {
            $this->report[] = [$table.'.'.$column, $s['rows'], 0, Money::round($s['old'], 2), Money::round($s['new'], 4), ''];
        }
    }

    /**
     * @param  array<string, array<string, int>>  $columns
     */
    private function convertJson(string $table, array $columns, bool $nested = false): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column => $keys) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }
            $changed = 0;

            DB::table($table)->select(['id', $column])->whereNotNull($column)->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $column, $keys, $nested, &$changed) {
                    $archive = [];
                    foreach ($rows as $row) {
                        $data = json_decode((string) $row->{$column}, true);
                        if (! is_array($data)) {
                            continue;
                        }
                        $touched = false;
                        foreach ($keys as $key => $scale) {
                            $value = $nested ? data_get($data, $key) : ($data[$key] ?? null);
                            if ($value === null || ! is_numeric($value)) {
                                continue;
                            }
                            $old = Money::normalize((string) $value);
                            $new = $this->convert($old, $scale);
                            if ($nested) {
                                data_set($data, $key, $new);
                            } else {
                                $data[$key] = $new;
                            }
                            $touched = true;
                            $archive[] = $this->archiveRow($table, (int) $row->id, $column.'.'.$key, $old, $new);
                        }
                        if (isset($data['retail_currency']) && strtoupper((string) $data['retail_currency']) === 'NGN') {
                            $data['retail_currency'] = 'USD';
                            $touched = true;
                        }
                        if ($touched) {
                            $changed++;
                            if (! $this->dryRun) {
                                DB::table($table)->where('id', $row->id)->update([$column => json_encode($data)]);
                            }
                        }
                    }
                    $this->writeArchive($archive);
                });

            $this->report[] = [$table.'.'.$column.' (JSON'.($nested ? ', nested' : '').')', $changed, 0, '', '', ''];
        }
    }

    private function convertValueSnapshots(): void
    {
        if (! Schema::hasTable('analytics_kpi_snapshots')) {
            return;
        }

        $count = 0;
        DB::table('analytics_kpi_snapshots')->select(['id', 'value'])
            ->whereIn('kpi_key', self::MONEY_VALUE_SNAPSHOT_KEYS)->whereNotNull('value')->orderBy('id')
            ->chunkById(500, function ($rows) use (&$count) {
                $archive = [];
                foreach ($rows as $row) {
                    if (! is_numeric($row->value)) {
                        continue;
                    }
                    $old = Money::normalize((string) $row->value);
                    $new = $this->convert($old, 4);
                    $count++;
                    $archive[] = $this->archiveRow('analytics_kpi_snapshots', (int) $row->id, 'value', $old, $new);
                    if (! $this->dryRun) {
                        DB::table('analytics_kpi_snapshots')->where('id', $row->id)->update(['value' => $new]);
                    }
                }
                $this->writeArchive($archive);
            });

        $this->report[] = ['analytics_kpi_snapshots.value ('.implode(', ', self::MONEY_VALUE_SNAPSHOT_KEYS).')', $count, 0, '', '', ''];
    }

    /**
     * Money-looking columns that this command does not convert, so the dry run never skips one silently.
     *
     * @return list<string>
     */
    private function unconvertedMoneyColumns(): array
    {
        $covered = [];
        foreach (self::AMOUNT_COLUMNS as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                $covered[$table.'.'.$column] = true;
            }
        }
        $known = array_flip(self::NOT_CONVERTED);

        $found = [];
        $pattern = '/(amount|price|fee|balance|budget|reward|cost|total|_min|_max)$/';
        foreach (Schema::getTables() as $tableInfo) {
            $table = (string) ($tableInfo['name'] ?? '');
            if ($table === '' || in_array($table, self::NOT_CONVERTED_TABLES, true)) {
                continue;
            }
            foreach (Schema::getColumns($table) as $col) {
                $name = (string) $col['name'];
                $type = strtolower((string) ($col['type_name'] ?? $col['type'] ?? ''));
                if (! str_contains($type, 'decimal') && ! str_contains($type, 'numeric')) {
                    continue;
                }
                if (! preg_match($pattern, $name)) {
                    continue;
                }
                $key = $table.'.'.$name;
                if (! isset($covered[$key]) && ! isset($known[$key])) {
                    $found[] = $key;
                }
            }
        }

        return $found;
    }

    /** Revenue snapshots: convert the value and move the old _ngn key to the neutral one. */
    private function convertSnapshots(): void
    {
        if (! Schema::hasTable('analytics_kpi_snapshots')) {
            return;
        }

        $count = 0;
        DB::table('analytics_kpi_snapshots')->select(['id', 'kpi_key', 'value'])
            ->whereIn('kpi_key', self::MONEY_SNAPSHOT_KEYS)->orderBy('id')
            ->chunkById(500, function ($rows) use (&$count) {
                $archive = [];
                foreach ($rows as $row) {
                    $old = Money::normalize((string) $row->value);
                    $new = $this->convert($old, 4);
                    $count++;
                    $archive[] = $this->archiveRow('analytics_kpi_snapshots', (int) $row->id, 'value', $old, $new);
                    if (! $this->dryRun) {
                        DB::table('analytics_kpi_snapshots')->where('id', $row->id)->update([
                            'value' => $new,
                            'kpi_key' => self::MONEY_SNAPSHOT_KEYS[0],
                        ]);
                    }
                }
                $this->writeArchive($archive);
            });

        $this->report[] = ['analytics_kpi_snapshots.value', $count, 0, '', '', 'renamed to '.self::MONEY_SNAPSHOT_KEYS[0]];
    }

    private function convertSettings(): void
    {
        $archive = [];
        $count = 0;
        foreach (self::SETTINGS as $key) {
            $value = SystemSetting::get($key);
            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }
            $old = Money::normalize((string) $value);
            $new = $this->convert($old, 2);
            $count++;
            $archive[] = $this->archiveRow('system_settings', null, $key, $old, $new);
            $this->line(sprintf('  setting %-24s %s NGN -> %s USD', $key, $old, $new));
            if (! $this->dryRun) {
                SystemSetting::set($key, $new);
            }
        }
        $this->writeArchive($archive);
        $this->report[] = ['system_settings', $count, 0, '', '', ''];
    }

    private function setCurrencyColumns(): void
    {
        foreach (self::CURRENCY_COLUMNS as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'currency')) {
                continue;
            }
            $query = DB::table($table)->where('currency', '!=', 'USD');
            $count = (clone $query)->count();
            if (! $this->dryRun && $count > 0) {
                $query->update(['currency' => 'USD']);
            }
            $this->report[] = [$table.'.currency', $count, 0, '', '', 'set to USD'];
        }

        if (Schema::hasTable('domain_quotes') && Schema::hasColumn('domain_quotes', 'retail_currency')) {
            $query = DB::table('domain_quotes')->where('retail_currency', 'NGN');
            $count = (clone $query)->count();
            if (! $this->dryRun && $count > 0) {
                $query->update(['retail_currency' => 'USD']);
            }
            $this->report[] = ['domain_quotes.retail_currency', $count, 0, '', '', 'set to USD'];
        }
    }

    /**
     * Balances are converted directly, so the converted ledger can drift from them by sub-cent
     * rounding. Post one adjustment entry per wallet so balance minus ledger is unchanged in USD.
     */
    private function reconcileLedger(): void
    {
        $adjusted = 0;
        $flagged = [];

        foreach ($this->balances as $walletId => $balance) {
            $ledger = $this->ledger[$walletId] ?? ['old' => '0', 'new' => '0'];
            $gapBefore = bcsub($balance['old'], $ledger['old'], 8);
            $expectedGap = $this->convert($gapBefore, 4);
            $gapAfter = bcsub($balance['new'], $ledger['new'], 4);
            $residual = bcsub($gapAfter, $expectedGap, 4);

            if (bccomp($residual, '0', 4) === 0) {
                continue;
            }
            if (bccomp(ltrim($residual, '-'), '0.01', 4) >= 0 || $balance['user_id'] === null) {
                $flagged[] = 'wallet #'.$walletId.' residual '.$residual;

                continue;
            }

            $adjusted++;
            if (! $this->dryRun) {
                Transaction::create([
                    'user_id' => $balance['user_id'],
                    'wallet_id' => $walletId,
                    'reference' => 'TXN-'.strtoupper(Str::random(10)),
                    'type' => TransactionType::CurrencyConversionRounding->value,
                    'label' => 'Currency conversion rounding',
                    'description' => 'Rounding from the NGN to USD conversion at '.$this->rate.' (batch '.$this->batch.')',
                    'amount' => $residual,
                    'currency' => 'USD',
                    'status' => 'completed',
                ]);
            }
        }

        $this->report[] = ['ledger rounding entries', $adjusted, count($flagged), '', '', ''];
        foreach ($flagged as $line) {
            $this->warn('  Review: '.$line.' (not adjusted)');
        }
    }

    private function convert(string $amount, int $scale): string
    {
        return Money::round(bcdiv($amount, $this->rate, 12), $scale);
    }

    /**
     * @param  array<string, string>  $updates
     */
    private function trackLedger(string $table, object $row, array $updates): void
    {
        if ($table === 'transactions' && isset($row->wallet_id) && isset($updates['amount'])) {
            $id = (int) $row->wallet_id;
            $this->ledger[$id] ??= ['old' => '0', 'new' => '0'];
            $this->ledger[$id]['old'] = bcadd($this->ledger[$id]['old'], Money::normalize((string) $row->amount), 8);
            $this->ledger[$id]['new'] = bcadd($this->ledger[$id]['new'], $updates['amount'], 4);
        }

        if ($table === 'wallets' && isset($updates['balance'])) {
            $this->balances[(int) $row->id] = [
                'old' => Money::normalize((string) $row->balance),
                'new' => $updates['balance'],
                'user_id' => isset($row->user_id) ? (int) $row->user_id : null,
            ];
        }
    }

    private function trackDiff(string $label, string $old, string $new): void
    {
        $exact = bcdiv($old, $this->rate, 12);
        $diff = ltrim(bcsub($new, $exact, 12), '-');
        $this->largestDiffs[] = [$label, $diff, $old.' -> '.$new];
        if (count($this->largestDiffs) > 40) {
            usort($this->largestDiffs, fn ($a, $b) => bccomp($b[1], $a[1], 12));
            $this->largestDiffs = array_slice($this->largestDiffs, 0, 10);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function archiveRow(string $table, ?int $rowId, string $column, string $old, string $new): array
    {
        return [
            'batch' => $this->batch,
            'table_name' => $table,
            'row_id' => $rowId,
            'column_name' => $column,
            'original_value' => $old,
            'new_value' => $new,
            'rate' => $this->rate,
            'created_at' => now(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeArchive(array $rows): void
    {
        if ($this->dryRun || $rows === []) {
            return;
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('currency_conversion_archive')->insert($chunk);
        }
    }

    private function printReport(): void
    {
        $this->table(
            ['Column', 'Rows', 'Flagged', 'Total before (NGN)', 'Total after (USD)', 'Note'],
            $this->report,
        );

        usort($this->largestDiffs, fn ($a, $b) => bccomp($b[1], $a[1], 12));
        $top = array_slice(array_filter($this->largestDiffs, fn ($d) => bccomp($d[1], '0', 12) > 0), 0, 10);
        if ($top !== []) {
            $this->line('Largest rounding differences:');
            $this->table(['Field', 'Difference (USD)', 'Value'], array_map(
                fn ($d) => [$d[0], rtrim(rtrim($d[1], '0'), '.'), $d[2]],
                $top,
            ));
        }

        $unconverted = $this->unconvertedMoneyColumns();
        if ($unconverted !== []) {
            $this->warn('These money-looking columns are not converted by this command. Check each one before the real run:');
            foreach ($unconverted as $column) {
                $this->line('  - '.$column);
            }
        } else {
            $this->info('Every money-looking column is either converted or deliberately left alone.');
        }
    }
}
