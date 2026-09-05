<?php

namespace Tests\Feature\Demo;

use App\Models\AnalyticsKpiSnapshot;
use App\Models\AuditLog;
use App\Models\KycSubmission;
use App\Models\Order;
use App\Models\SupportTicketReply;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\Wallet;
use Database\Seeders\Demo\DemoPlatformSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DemoPlatformSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_platform_seeder_builds_consistent_graph(): void
    {
        config(['demo.allow_demo_data' => true]);

        $this->seed([
            SystemSettingSeeder::class,
            DemoPlatformSeeder::class,
        ]);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'super.admin@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'finance.admin@example.com']);

        $this->assertGreaterThanOrEqual(1, User::role('admin')->count());
        $this->assertGreaterThanOrEqual(1, User::role('demo_finance')->count());
        $this->assertGreaterThanOrEqual(1, User::role('demo_compliance')->count());
        $this->assertGreaterThanOrEqual(1, User::role('demo_support')->count());
        $this->assertGreaterThanOrEqual(1, User::role('demo_moderator')->count());

        $this->assertGreaterThanOrEqual(1, KycSubmission::query()->where('status', 'approved')->count());
        $this->assertGreaterThanOrEqual(1, SupportTicketReply::query()->count());

        $this->assertGreaterThanOrEqual(1, Transaction::query()
            ->where('type', 'purchase')
            ->where('amount', '>', 0)
            ->count());

        $this->assertGreaterThanOrEqual(1, UserActivity::query()->where('context_key', 'dashboard.wallet.view')->count());
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->count());
        $this->assertGreaterThanOrEqual(1, AnalyticsKpiSnapshot::query()->where('period', 'daily')->count());

        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $this->assertGreaterThanOrEqual(1, (int) $alice->kyc_level);
        $this->assertTrue(
            Order::query()->where('user_id', $alice->id)->where('status', 'completed')->exists()
        );

        $wallet = Wallet::query()->where('user_id', $alice->id)->firstOrFail();
        $ledger = (float) Transaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('status', 'completed')
            ->sum('amount');
        $this->assertEqualsWithDelta(round($ledger, 2), (float) $wallet->balance, 0.05);

        $registrationDays = User::query()
            ->selectRaw('date(created_at) as d')
            ->groupBy('d')
            ->pluck('d');

        $this->assertGreaterThanOrEqual(2, $registrationDays->count());
    }

    public function test_demo_seeder_is_blocked_without_gate(): void
    {
        config(['demo.allow_demo_data' => false]);

        $this->expectException(RuntimeException::class);

        $this->seed(DemoPlatformSeeder::class);
    }
}
