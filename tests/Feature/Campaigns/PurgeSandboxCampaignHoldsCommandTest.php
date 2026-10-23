<?php

namespace Tests\Feature\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignBudget;
use App\Models\CampaignParticipation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeSandboxCampaignHoldsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_releases_hold_without_refunding_creator(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $creator = User::factory()->create(['email_verified_at' => now()]);
        $creator->assignRole('user');
        $creatorWallet = Wallet::factory()->create([
            'user_id' => $creator->id,
            'balance' => 0,
            'locked_balance' => 0,
        ]);

        $wallets = app(WalletService::class);
        $platform = $wallets->getPlatformWallet();
        $wallets->adminAdjust($platform, '500', 'seed', $admin->id);

        $order = Order::factory()->completed()->create(['user_id' => $creator->id]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'platform_product',
            'item_id' => 1,
            'quantity' => 10,
            'unit_price' => 5,
            'line_total' => 50,
        ]);

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'title' => 'Sandbox hold purge',
            'target_url' => 'https://example.com/video',
            'engagement_metric' => 'likes',
            'quantity' => 10,
            'completed_count' => 0,
            'locked_creator_price' => 5,
            'locked_agent_reward' => 5,
            'status' => Campaign::STATUS_ACTIVE,
            'created_at' => now()->subDays(3),
        ]);

        $wallets->holdCampaignBudget($campaign->id, '50.0000', $order->id);
        CampaignBudget::query()->create([
            'campaign_id' => $campaign->id,
            'funded_amount' => '50.0000',
            'paid_amount' => 0,
            'refunded_amount' => 0,
            'released_amount' => 0,
            'status' => CampaignBudget::STATUS_HELD,
        ]);

        CampaignParticipation::query()->create([
            'campaign_id' => $campaign->id,
            'agent_id' => User::factory()->create()->id,
            'status' => CampaignParticipation::STATUS_STARTED,
            'reward_amount' => '5.0000',
        ]);

        $lockedBefore = (string) $platform->fresh()->locked_balance;
        $creatorBefore = (string) $creatorWallet->fresh()->balance;

        $this->artisan('campaigns:purge-sandbox-holds', [
            '--before' => now()->toDateString(),
            '--execute' => true,
            '--admin' => $admin->id,
        ])->assertSuccessful();

        $this->assertSame(Campaign::STATUS_CANCELLED, $campaign->fresh()->status);
        $this->assertSame(CampaignBudget::STATUS_SETTLED, $campaign->budget()->value('status'));
        $this->assertTrue(bccomp((string) $platform->fresh()->locked_balance, $lockedBefore, 4) < 0);
        $this->assertSame($creatorBefore, (string) $creatorWallet->fresh()->balance);
        $this->assertSame(
            CampaignParticipation::STATUS_EXPIRED,
            CampaignParticipation::query()->where('campaign_id', $campaign->id)->value('status')
        );
    }
}
