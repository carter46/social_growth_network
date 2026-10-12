<?php

namespace Tests\Feature\Reporting;

use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Reporting\Metrics\PlatformRevenueMetric;
use App\Services\Reporting\ReportingRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformRevenueMetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_revenue_includes_fees_and_platform_sales_only(): void
    {
        $user = User::factory()->create();
        $userWallet = Wallet::factory()->create(['user_id' => $user->id, 'type' => WalletType::User]);
        $platform = app(WalletService::class)->getPlatformWallet();

        Transaction::query()->create([
            'user_id' => $platform->user_id,
            'wallet_id' => $platform->id,
            'reference' => 'TXN-FEE1',
            'type' => TransactionType::PlatformFee->value,
            'label' => 'Fee',
            'amount' => 1000,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        Transaction::query()->create([
            'user_id' => $platform->user_id,
            'wallet_id' => $platform->id,
            'reference' => 'TXN-SALE1',
            'type' => TransactionType::Purchase->value,
            'label' => 'Sale',
            'amount' => 5000,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $userWallet->id,
            'reference' => 'TXN-FUND1',
            'type' => TransactionType::Funding->value,
            'label' => 'Funding',
            'amount' => 50000,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $userWallet->id,
            'reference' => 'TXN-REL1',
            'type' => TransactionType::EscrowRelease->value,
            'label' => 'Release',
            'amount' => 20000,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        $sum = app(PlatformRevenueMetric::class)->sum(ReportingRange::preset('30d'));

        $this->assertEqualsWithDelta(6000.0, $sum, 0.01);
    }
}
