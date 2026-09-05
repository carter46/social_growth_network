<?php

namespace Database\Seeders\Demo;

use App\Enums\TransactionType;
use App\Models\AnalyticsKpiSnapshot;
use App\Models\KycSubmission;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Support\Demo\DemoGate;
use Database\Seeders\Demo\Support\DemoContext;
use Database\Seeders\Demo\Support\DemoTimeline;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoPlatformSeeder extends Seeder
{
    public function run(): void
    {
        DemoGate::assertCanSeed();

        app(WalletService::class)->getPlatformWallet();

        $timeline = DemoTimeline::fromNow();
        $ctx = new DemoContext(app(\App\Support\Demo\DemoBatchTracker::class), $timeline);
        $ctx->startBatch('Demo platform '.$timeline->monthsAgo(0)->toDateString(), 'DemoPlatformSeeder');

        $this->runChild(DemoAdminsSeeder::class, $ctx, $timeline);
        $this->runChild(DemoUsersSeeder::class, $ctx, $timeline);
        $this->runChild(DemoKycSeeder::class, $ctx, $timeline);
        $this->runChild(DemoWalletSeeder::class, $ctx, $timeline);
        $this->runChild(DemoSupportSeeder::class, $ctx, $timeline);
        $this->runChild(DemoNotificationsSeeder::class, $ctx, $timeline);
        $this->runChild(DemoAuditSeeder::class, $ctx, $timeline);

        $this->rebuildWalletBalances();
        $this->runChild(DemoAnalyticsSeeder::class, $ctx, $timeline);

        $this->assertConsistency($ctx);

        foreach ($ctx->checklist as $line) {
            $this->command?->info($line);
        }
        $batchId = $ctx->tracker->batch()?->id;
        $this->command?->info('✓ Platform ready for demo'.($batchId ? " (batch #{$batchId})" : ''));
        $this->command?->info('  Launch cleanup: php artisan demo:clear --force');
    }

    private function runChild(string $class, DemoContext $ctx, DemoTimeline $timeline): void
    {
        /** @var Seeder $seeder */
        $seeder = app($class);
        $seeder->run($ctx, $timeline);
    }

    private function rebuildWalletBalances(): void
    {
        $wallets = Wallet::query()->whereNotNull('user_id')->get();
        foreach ($wallets as $wallet) {
            $sum = round((float) Transaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('status', 'completed')
                ->sum('amount'), 2);

            $wallet->forceFill([
                'balance' => $sum,
                'locked_balance' => 0,
            ])->save();
        }
    }

    private function assertConsistency(DemoContext $ctx): void
    {
        $alice = $ctx->member('alice');
        if ((int) $alice->kyc_level < 1) {
            throw new RuntimeException('Consistency: Alice should be KYC-verified.');
        }

        $approvedWithoutLevel = KycSubmission::query()
            ->where('status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('kyc_level', '<', 1))
            ->count();
        if ($approvedWithoutLevel > 0) {
            throw new RuntimeException('Consistency: approved KYC without matching kyc_level.');
        }

        $resolvedWithoutReplies = SupportTicket::query()
            ->whereIn('status', ['resolved', 'closed'])
            ->whereDoesntHave('replies')
            ->count();
        if ($resolvedWithoutReplies > 0) {
            throw new RuntimeException('Consistency: resolved/closed tickets missing replies.');
        }

        $wallet = Wallet::query()->where('user_id', $alice->id)->first();
        if (! $wallet) {
            throw new RuntimeException('Consistency: Alice wallet missing.');
        }

        $ledger = round((float) Transaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('status', 'completed')
            ->sum('amount'), 2);
        if (abs((float) $wallet->balance - $ledger) > 0.05) {
            throw new RuntimeException('Consistency: Alice wallet balance does not match ledger.');
        }

        foreach (Wallet::query()->where('type', 'user')->cursor() as $w) {
            $sum = round((float) Transaction::query()
                ->where('wallet_id', $w->id)
                ->where('status', 'completed')
                ->sum('amount'), 2);
            if ($sum < -0.05) {
                throw new RuntimeException("Consistency: wallet #{$w->id} ledger is negative ({$sum}).");
            }
            if (abs((float) $w->balance - $sum) > 0.05) {
                throw new RuntimeException("Consistency: wallet #{$w->id} balance ≠ ledger.");
            }
        }

        $validTypes = array_map(fn (TransactionType $t) => $t->value, TransactionType::cases());
        $invalidTypes = Transaction::query()->whereNotIn('type', $validTypes)->count();
        if ($invalidTypes > 0) {
            throw new RuntimeException('Consistency: invalid TransactionType values present.');
        }

        if (SupportTicket::query()->count() < 1) {
            throw new RuntimeException('Consistency: expected support tickets.');
        }
        if (KycSubmission::query()->count() < 1) {
            throw new RuntimeException('Consistency: expected KYC submissions.');
        }

        if (User::role('admin')->count() < 1) {
            throw new RuntimeException('Consistency: expected super admin persona.');
        }

        if (UserActivity::query()->where('context_key', 'like', 'dashboard.%')->count() < 1) {
            throw new RuntimeException('Consistency: expected route-level dashboard activity.');
        }

        if (AnalyticsKpiSnapshot::query()->where('period', 'daily')->count() < 1) {
            throw new RuntimeException('Consistency: expected KPI snapshots.');
        }

        // Platform orders may exist from other demo paths; marketplace orders are no longer seeded.
        Order::query()->where('source', 'marketplace')->delete();
    }
}
