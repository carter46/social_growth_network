<?php

namespace App\Console\Commands;

use App\Enums\TransactionType;
use App\Models\SystemSetting;
use App\Modules\Wallet\Payments\PayoutGate;
use App\Modules\Wallet\Services\LedgerReconciliationService;
use App\Support\Money\Currency;
use App\Support\Money\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only check after currency:convert-to-usd. An admin reviews this before marking the
 * cutover verified on the payment settings page, which is what lets payouts resume.
 */
class CurrencyCutoverReportCommand extends Command
{
    protected $signature = 'currency:cutover-report {--limit=20 : Wallet mismatches to list}';

    protected $description = 'Read-only report confirming the NGN to USD conversion before payouts are enabled';

    private const CURRENCY_TABLES = ['wallets', 'wallet_fundings', 'withdrawals', 'transactions'];

    public function handle(LedgerReconciliationService $ledger): int
    {
        $problems = [];

        $site = Currency::current()->code;
        $this->line('Site currency: '.$site);
        if ($site !== 'USD') {
            $problems[] = 'Site currency is '.$site.', not USD.';
        }

        $batch = $this->latestBatch();
        if ($batch === null) {
            $problems[] = 'No conversion batch found in currency_conversion_archive.';
        } else {
            $this->reportBatch($batch);
        }

        foreach (self::CURRENCY_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'currency')) {
                continue;
            }
            $left = DB::table($table)->whereNotNull('currency')->where('currency', '!=', 'USD')->count();
            if ($left > 0) {
                $problems[] = $left.' row(s) in '.$table.' are not marked USD.';
            }
        }

        $rounding = Schema::hasTable('transactions')
            ? DB::table('transactions')->where('type', TransactionType::CurrencyConversionRounding->value)->count()
            : 0;
        $this->line('Conversion rounding entries: '.$rounding);

        $mismatches = $ledger->mismatches();
        if ($mismatches === []) {
            $this->info('Every wallet balance matches its ledger, and every locked balance matches its active holds.');
        } else {
            $problems[] = count($mismatches).' wallet(s) do not match their ledger or holds.';
            $this->table(
                ['Wallet', 'Balance', 'Ledger', 'Difference', 'Locked', 'Active holds', 'Lock difference'],
                array_map(fn ($r) => [
                    $r['wallet_id'], $r['balance'], $r['ledger'], $r['residual'],
                    $r['locked_balance'], $r['holds'], $r['lock_residual'],
                ], array_slice($mismatches, 0, max(1, (int) $this->option('limit')))),
            );
        }

        $this->line('Payout gate: '.(PayoutGate::isOpen() ? 'open' : 'closed'));
        foreach (PayoutGate::reasons() as $reason) {
            $this->line('  - '.$reason);
        }

        if ($problems !== []) {
            $this->newLine();
            $this->error('Do not mark the cutover verified yet:');
            foreach ($problems as $problem) {
                $this->line('  - '.$problem);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('No problems found. An admin can mark the cutover verified on the payment settings page.');

        return self::SUCCESS;
    }

    private function latestBatch(): ?string
    {
        if (! Schema::hasTable('currency_conversion_archive')) {
            return null;
        }

        $batch = DB::table('currency_conversion_archive')->orderByDesc('id')->value('batch');

        return $batch !== null ? (string) $batch : null;
    }

    private function reportBatch(string $batch): void
    {
        $rows = DB::table('currency_conversion_archive')->where('batch', $batch);
        $rate = (clone $rows)->value('rate');
        $this->line('Conversion batch: '.$batch.' at '.Money::normalize((string) $rate).' NGN per USD, '.(clone $rows)->count().' archived value(s)');

        $wallets = (clone $rows)->where('table_name', 'wallets')->where('column_name', 'balance')
            ->get(['original_value', 'new_value']);
        $before = '0';
        $after = '0';
        foreach ($wallets as $row) {
            $before = Money::add($before, $row->original_value, 4);
            $after = Money::add($after, $row->new_value, 4);
        }
        $current = Money::round((string) (DB::table('wallets')->sum('balance') ?? '0'), 4);
        $this->line('Wallet balances: '.$before.' NGN before, '.$after.' USD at conversion, '.$current.' USD now');

        $settings = (clone $rows)->where('table_name', 'system_settings')->get(['column_name', 'original_value', 'new_value']);
        foreach ($settings as $row) {
            $now = SystemSetting::get((string) $row->column_name);
            $this->line(sprintf('  setting %-24s %s NGN -> %s USD (now %s)', $row->column_name, $row->original_value, $row->new_value, $now ?? 'unset'));
        }
    }
}
