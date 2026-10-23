<?php

namespace Tests\Feature\Reporting;

use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Campaign;
use App\Models\CampaignBudget;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Reporting\Metrics\PlatformRevenueMetric;
use App\Services\Reporting\ReportingRange;
use App\Support\SandboxCutover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformRevenueMetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_revenue_is_platform_share_plus_fees_not_full_order(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $creator = User::factory()->create();
        $platform = app(WalletService::class)->getPlatformWallet();

        Transaction::query()->create([
            'user_id' => $platform->user_id,
            'wallet_id' => $platform->id,
            'reference' => 'TXN-FEE1',
            'type' => TransactionType::PlatformFee->value,
            'label' => 'Fee',
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        $order = Order::factory()->platform()->create([
            'user_id' => $creator->id,
            'status' => 'paid',
            'total_amount' => 100,
            'amount' => 100,
            'payment_confirmed_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'platform_product',
            'item_id' => 1,
            'quantity' => 10,
            'unit_price' => 10,
            'line_total' => 100,
        ]);
        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'order_id' => $order->id,
            'order_item_id' => OrderItem::query()->where('order_id', $order->id)->value('id'),
            'title' => 'Rev test',
            'target_url' => 'https://example.com',
            'engagement_metric' => 'likes',
            'quantity' => 10,
            'completed_count' => 0,
            'locked_creator_price' => 10,
            'locked_agent_reward' => 7,
            'status' => Campaign::STATUS_ACTIVE,
        ]);
        CampaignBudget::query()->create([
            'campaign_id' => $campaign->id,
            'funded_amount' => '70.0000',
            'paid_amount' => 0,
            'refunded_amount' => 0,
            'released_amount' => 0,
            'status' => CampaignBudget::STATUS_HELD,
        ]);

        // Full purchase on platform wallet must NOT all count as revenue.
        Transaction::query()->create([
            'user_id' => $platform->user_id,
            'wallet_id' => $platform->id,
            'order_id' => $order->id,
            'reference' => 'TXN-SALE1',
            'type' => TransactionType::Purchase->value,
            'label' => 'Sale',
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        $metric = app(PlatformRevenueMetric::class);
        $range = ReportingRange::preset('30d');
        $breakdown = $metric->breakdown($range);

        // Platform keep = 100 − 70 agent budget = 30, plus $10 fee = 40.
        $this->assertEqualsWithDelta(40.0, $metric->sum($range), 0.01);
        $this->assertEqualsWithDelta(100.0, $breakdown['creator_spend'], 0.01);
        $this->assertEqualsWithDelta(30.0, $breakdown['platform_share'], 0.01);
        $this->assertEqualsWithDelta(10.0, $breakdown['fee_revenue'], 0.01);
    }

    public function test_sandbox_cutover_excludes_old_paid_orders(): void
    {
        $creator = User::factory()->create();
        SandboxCutover::remember(now()->subDay());

        Order::factory()->platform()->create([
            'user_id' => $creator->id,
            'status' => 'paid',
            'total_amount' => 50,
            'amount' => 50,
            'created_at' => now()->subDays(3),
        ]);
        Order::factory()->platform()->create([
            'user_id' => $creator->id,
            'status' => 'paid',
            'total_amount' => 25,
            'amount' => 25,
            'created_at' => now(),
        ]);

        $breakdown = app(PlatformRevenueMetric::class)->breakdown(ReportingRange::preset('30d'));

        $this->assertEqualsWithDelta(25.0, $breakdown['creator_spend'], 0.01);
        $this->assertEqualsWithDelta(25.0, $breakdown['platform_share'], 0.01);
    }
}
