<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignBudget;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Campaigns\CampaignBudgetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CampaignRefundMathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('escrows')) {
            Schema::create('escrows', fn (Blueprint $table) => $table->id());
        }
    }

    private function campaign(array $overrides = [], ?array $line = null): Campaign
    {
        $creator = User::factory()->creator()->create();
        $orderId = null;
        $itemId = null;

        if ($line !== null) {
            $order = Order::factory()->completed()->create(['user_id' => $creator->id]);
            $item = OrderItem::query()->create([
                'order_id' => $order->id,
                'item_type' => 'platform_product',
                'item_id' => 1,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
            ]);
            $orderId = $order->id;
            $itemId = $item->id;
        }

        return Campaign::query()->create(array_merge([
            'creator_id' => $creator->id,
            'order_id' => $orderId,
            'order_item_id' => $itemId,
            'title' => 'Refund math',
            'target_url' => 'https://example.com/video',
            'engagement_metric' => 'likes',
            'quantity' => 1000,
            'completed_count' => 100,
            'locked_creator_price' => 5000,
            'locked_agent_reward' => 1,
            'status' => Campaign::STATUS_ACTIVE,
        ], $overrides));
    }

    public function test_fixed_package_refunds_the_package_price_pro_rata(): void
    {
        $campaign = $this->campaign([], ['quantity' => 1, 'unit_price' => 5000, 'line_total' => 5000]);

        $this->assertSame('4500.00', app(CampaignBudgetService::class)->refundableAmount($campaign));
    }

    public function test_per_unit_line_refunds_the_unit_price_of_each_remaining_unit(): void
    {
        $campaign = $this->campaign(
            ['quantity' => 10, 'completed_count' => 3, 'locked_creator_price' => 250],
            ['quantity' => 10, 'unit_price' => 250, 'line_total' => 2500],
        );

        $this->assertSame('1750.00', app(CampaignBudgetService::class)->refundableAmount($campaign));
    }

    public function test_campaign_without_an_order_item_uses_unit_price_times_quantity(): void
    {
        $campaign = $this->campaign(['quantity' => 3, 'completed_count' => 1, 'locked_creator_price' => 1000]);

        $this->assertSame('2000.00', app(CampaignBudgetService::class)->refundableAmount($campaign));
    }

    public function test_odd_splits_round_down_to_the_kobo_and_nothing_is_owed_when_complete(): void
    {
        $budgets = app(CampaignBudgetService::class);
        $odd = $this->campaign(['quantity' => 3, 'completed_count' => 1], ['quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]);
        $done = $this->campaign(['quantity' => 5, 'completed_count' => 5], ['quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]);

        $this->assertSame('666.66', $budgets->refundableAmount($odd));
        $this->assertSame('0.00', $budgets->refundableAmount($done));
    }

    public function test_cancelled_fixed_package_refund_never_exceeds_what_the_creator_paid(): void
    {
        $platform = app(WalletService::class)->getPlatformWallet();
        $platform->forceFill(['balance' => 20000, 'locked_balance' => 0])->save();

        $campaign = $this->campaign([], ['quantity' => 1, 'unit_price' => 5000, 'line_total' => 5000]);
        $creatorWallet = Wallet::factory()->create(['user_id' => $campaign->creator_id, 'balance' => 0, 'locked_balance' => 0]);

        $budgets = app(CampaignBudgetService::class);
        $budgets->holdForCampaign($campaign, 900);
        $campaign->update(['status' => Campaign::STATUS_CANCELLED]);

        $this->assertSame('4500.00', $budgets->refundIfCancelled($campaign->fresh()));
        $this->assertEquals(4500.0, (float) $creatorWallet->fresh()->balance);
        $this->assertEquals(0.0, (float) $platform->fresh()->locked_balance);
        $this->assertSame(CampaignBudget::STATUS_REFUNDED, CampaignBudget::query()->where('campaign_id', $campaign->id)->value('status'));
    }
}
