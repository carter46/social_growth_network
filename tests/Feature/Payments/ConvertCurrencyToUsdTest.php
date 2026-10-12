<?php

namespace Tests\Feature\Payments;

use App\Enums\TransactionType;
use App\Models\CurrencyConversionArchive;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConvertCurrencyToUsdTest extends TestCase
{
    use RefreshDatabase;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => '1000',
            'locked_balance' => '0',
            'currency' => 'NGN',
        ]);
        foreach (['333.33', '333.33', '333.34'] as $amount) {
            Transaction::create([
                'user_id' => $user->id,
                'wallet_id' => $this->wallet->id,
                'reference' => 'TXN-'.strtoupper(Str::random(10)),
                'type' => TransactionType::Funding->value,
                'label' => 'Deposit',
                'amount' => $amount,
                'currency' => 'NGN',
                'status' => 'completed',
            ]);
        }
        SystemSetting::set('withdrawal_min_amount', '15500');
    }

    private function maintenance(bool $down): void
    {
        $this->app->instance(MaintenanceMode::class, new class($down) implements MaintenanceMode
        {
            public function __construct(private bool $down) {}

            public function activate(array $payload): void {}

            public function deactivate(): void {}

            public function active(): bool
            {
                return $this->down;
            }

            public function data(): array
            {
                return [];
            }
        });
    }

    public function test_rate_is_required(): void
    {
        $this->artisan('currency:convert-to-usd', ['--dry-run' => true])->assertFailed();
    }

    public function test_open_withdrawals_block_the_conversion(): void
    {
        Withdrawal::query()->create([
            'user_id' => $this->wallet->user_id,
            'wallet_id' => $this->wallet->id,
            'amount' => '500',
            'currency' => 'NGN',
            'status' => 'pending',
            'internal_status' => 'pending_review',
            'reference' => 'WDR-'.strtoupper(Str::random(10)),
            'bank_name' => 'Example Bank',
            'account_number' => '0123456789',
            'account_name' => 'Ada Lovelace',
        ]);
        $this->maintenance(true);

        $this->artisan('currency:convert-to-usd', ['--rate' => '1550'])
            ->expectsOutputToContain('open withdrawal')
            ->assertFailed();

        $this->assertSame('1000.0000', (string) $this->wallet->fresh()->balance);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('currency:convert-to-usd', ['--rate' => '1550', '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame('1000.0000', (string) $this->wallet->fresh()->balance);
        $this->assertSame('NGN', $this->wallet->fresh()->currency);
        $this->assertSame('15500', (string) SystemSetting::get('withdrawal_min_amount'));
        $this->assertSame(0, CurrencyConversionArchive::query()->count());
    }

    public function test_real_run_needs_maintenance_mode(): void
    {
        $this->maintenance(false);

        $this->artisan('currency:convert-to-usd', ['--rate' => '1550'])
            ->expectsOutputToContain('maintenance mode')
            ->assertFailed();

        $this->assertSame('1000.0000', (string) $this->wallet->fresh()->balance);
    }

    public function test_conversion_archives_rounds_reconciles_and_refuses_a_second_run(): void
    {
        $this->maintenance(true);

        $this->artisan('currency:convert-to-usd', ['--rate' => '1550'])->assertSuccessful();

        $wallet = $this->wallet->fresh();
        $this->assertSame('0.6452', (string) $wallet->balance);
        $this->assertSame('USD', $wallet->currency);
        $this->assertSame('10.00', (string) SystemSetting::get('withdrawal_min_amount'));

        $rounding = Transaction::query()->where('type', TransactionType::CurrencyConversionRounding->value)->get();
        $this->assertCount(1, $rounding);
        $this->assertSame(0, bccomp('-0.0001', (string) $rounding->first()->amount, 4));

        $ledger = Transaction::query()->where('wallet_id', $wallet->id)->get()
            ->reduce(fn (string $sum, Transaction $t) => bcadd($sum, (string) $t->amount, 4), '0');
        $this->assertSame(0, bccomp((string) $wallet->balance, $ledger, 4));

        $archived = CurrencyConversionArchive::query()->where('table_name', 'wallets')->where('column_name', 'balance')->where('row_id', $wallet->id)->first();
        $this->assertNotNull($archived);
        $this->assertSame(0, bccomp('1000', (string) $archived->original_value, 4));
        $this->assertTrue(CurrencyConversionArchive::query()->where('table_name', 'system_settings')->where('column_name', 'withdrawal_min_amount')->exists());

        $this->artisan('currency:convert-to-usd', ['--rate' => '1550'])
            ->expectsOutputToContain('already been recorded')
            ->assertFailed();
        $this->assertSame('0.6452', (string) $wallet->fresh()->balance);
    }

    public function test_fee_rules_bounce_fee_and_revenue_snapshots_are_converted(): void
    {
        $this->maintenance(true);
        SystemSetting::set('withdrawal_bounce_fee', '1550');
        $ruleId = DB::table('payout_fee_rules')->insertGetId([
            'flat_fee' => '3100', 'percent_fee' => '1.5', 'min_fee' => '1550', 'max_fee' => null,
            'verified_provider_fee_flat' => '775', 'min_withdrawal' => '15500', 'max_withdrawal' => '1550000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $snapshotId = DB::table('analytics_kpi_snapshots')->insertGetId([
            'kpi_key' => 'revenue.today', 'period' => 'today', 'value' => '31000', 'captured_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('currency:convert-to-usd', ['--rate' => '1550'])->assertSuccessful();

        $rule = DB::table('payout_fee_rules')->find($ruleId);
        $this->assertSame(0, bccomp('2', (string) $rule->flat_fee, 2));
        $this->assertSame(0, bccomp('1.5', (string) $rule->percent_fee, 3));
        $this->assertSame(0, bccomp('1', (string) $rule->min_fee, 2));
        $this->assertNull($rule->max_fee);
        $this->assertSame(0, bccomp('0.5', (string) $rule->verified_provider_fee_flat, 2));
        $this->assertSame(0, bccomp('10', (string) $rule->min_withdrawal, 2));
        $this->assertSame(0, bccomp('1000', (string) $rule->max_withdrawal, 2));
        $this->assertSame('1.00', (string) SystemSetting::get('withdrawal_bounce_fee'));

        $snapshot = DB::table('analytics_kpi_snapshots')->find($snapshotId);
        $this->assertSame('revenue.today', $snapshot->kpi_key);
        $this->assertSame(0, bccomp('20', (string) $snapshot->value, 4));
    }

    public function test_cutover_report_passes_after_a_clean_conversion(): void
    {
        $this->maintenance(true);
        $this->artisan('currency:convert-to-usd', ['--rate' => '1550'])->assertSuccessful();
        config(['platform.currency' => 'USD']);

        $this->artisan('currency:cutover-report')
            ->expectsOutputToContain('No problems found')
            ->assertSuccessful();
    }

    public function test_cutover_report_fails_before_conversion(): void
    {
        config(['platform.currency' => 'NGN']);

        $this->artisan('currency:cutover-report')
            ->expectsOutputToContain('Do not mark the cutover verified yet')
            ->assertFailed();
    }
}
