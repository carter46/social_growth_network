<?php

namespace Tests\Feature\Payments;

use App\Enums\TransactionType;
use App\Models\Campaign;
use App\Models\CampaignBudget;
use App\Models\CampaignParticipation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReferralCommission;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Services\LedgerReconciliationService;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Campaigns\CampaignBudgetService;
use App\Services\Referrals\ReferralCommissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CampaignEndStateRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private int $nextParticipation = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();

        if (! Schema::hasTable('escrows')) {
            Schema::create('escrows', fn (Blueprint $table) => $table->id());
        }

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('admin');
        $this->agent = User::factory()->agent()->create();
        Wallet::factory()->create(['user_id' => $this->agent->id, 'balance' => 0, 'locked_balance' => 0]);

        $wallets = app(WalletService::class);
        $wallets->adminAdjust($wallets->getPlatformWallet(), '1000', 'Opening balance', $this->admin->id);
    }

    private function wallets(): WalletService
    {
        return app(WalletService::class);
    }

    private function budgets(): CampaignBudgetService
    {
        return app(CampaignBudgetService::class);
    }

    private function balance(Wallet $wallet): string
    {
        return bcadd((string) $wallet->fresh()->balance, '0', 4);
    }

    /** 10 units at 2.50 each (25.00 paid), agent reward 1.00 per unit. */
    private function campaign(?User $creator = null): Campaign
    {
        $creator ??= User::factory()->creator()->create();
        Wallet::query()->firstOrCreate(['user_id' => $creator->id], ['balance' => 0, 'locked_balance' => 0]);
        $order = Order::factory()->completed()->create(['user_id' => $creator->id]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'platform_product',
            'item_id' => 1,
            'quantity' => 10,
            'unit_price' => 2.5,
            'line_total' => 25,
        ]);

        return Campaign::query()->create([
            'creator_id' => $creator->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'title' => 'End state refund',
            'target_url' => 'https://example.com/video',
            'engagement_metric' => 'likes',
            'quantity' => 10,
            'completed_count' => 0,
            'locked_creator_price' => 2.5,
            'locked_agent_reward' => 1,
            'status' => Campaign::STATUS_ACTIVE,
        ]);
    }

    private function deliver(Campaign $campaign, int $units): void
    {
        for ($i = 0; $i < $units; $i++) {
            $participation = (new CampaignParticipation)->forceFill(['id' => $this->nextParticipation++]);
            $this->budgets()->payReward($participation, $campaign, $this->agent, '1.0000');
        }
        $campaign->forceFill(['completed_count' => (int) $campaign->completed_count + $units])->save();
    }

    private function creatorWallet(Campaign $campaign): Wallet
    {
        return Wallet::query()->where('user_id', $campaign->creator_id)->firstOrFail();
    }

    public function test_rejected_completed_and_cancelled_campaigns_refund_undelivered_units_once(): void
    {
        foreach ([Campaign::STATUS_REJECTED, Campaign::STATUS_COMPLETED, Campaign::STATUS_CANCELLED] as $status) {
            $campaign = $this->campaign();
            $this->assertNotNull($this->budgets()->holdForCampaign($campaign));
            $this->deliver($campaign, 3);
            $campaign->forceFill(['status' => $status])->save();

            $this->assertSame('17.50', $this->budgets()->refundUndelivered($campaign->fresh()), $status);
            $this->assertNull($this->budgets()->refundUndelivered($campaign->fresh()), $status);

            $this->assertSame('17.5000', $this->balance($this->creatorWallet($campaign)), $status);
            $this->assertSame(1, Transaction::query()->where('reference', 'CMP-REFUND-'.$campaign->id)->count());
            $this->assertSame(CampaignBudget::STATUS_REFUNDED, CampaignBudget::query()->where('campaign_id', $campaign->id)->value('status'));
        }

        $platform = $this->wallets()->getPlatformWallet()->fresh();
        $this->assertSame('0.0000', bcadd((string) $platform->locked_balance, '0', 4));
        $this->assertSame('938.5000', $this->balance($platform));
        $this->assertSame([], app(LedgerReconciliationService::class)->mismatches());
    }

    public function test_suspended_campaign_keeps_its_hold(): void
    {
        $campaign = $this->campaign();
        $this->budgets()->holdForCampaign($campaign);
        $campaign->forceFill(['status' => Campaign::STATUS_SUSPENDED])->save();

        $this->assertNull($this->budgets()->refundUndelivered($campaign->fresh()));
        $this->assertSame(CampaignBudget::STATUS_HELD, CampaignBudget::query()->where('campaign_id', $campaign->id)->value('status'));
    }

    public function test_unfunded_campaign_is_refunded_from_the_platform_wallet(): void
    {
        $campaign = $this->campaign();
        $this->deliver($campaign, 2);
        $campaign->forceFill(['status' => Campaign::STATUS_CANCELLED])->save();

        $this->assertSame('20.00', $this->budgets()->refundIfCancelled($campaign->fresh()));
        $this->assertNull($this->budgets()->refundIfCancelled($campaign->fresh()));

        $this->assertSame('20.0000', $this->balance($this->creatorWallet($campaign)));
        $this->assertSame('978.0000', $this->balance($this->wallets()->getPlatformWallet()));
        $this->assertSame(2, Transaction::query()->where('type', TransactionType::UnfundedCampaignRewardExpense->value)->count());
        $this->assertSame([], app(LedgerReconciliationService::class)->mismatches());
    }

    public function test_referral_reversal_runs_once_and_the_owed_part_is_taken_from_later_earnings(): void
    {
        $referrer = User::factory()->agent()->create();
        $referrerWallet = Wallet::factory()->create(['user_id' => $referrer->id, 'balance' => 0, 'locked_balance' => 0]);
        $creator = User::factory()->creator()->create(['referred_by_id' => $referrer->id]);
        $campaign = $this->campaign($creator);

        $commission = ReferralCommission::query()->create([
            'referrer_id' => $referrer->id,
            'referred_user_id' => $creator->id,
            'kind' => ReferralCommission::KIND_CREATOR_SPEND,
            'source_type' => (new Order)->getMorphClass(),
            'source_id' => $campaign->order_id,
            'base_amount' => 25,
            'rate' => 20,
            'amount' => 5,
            'status' => ReferralCommission::STATUS_PENDING,
        ]);
        $referrals = app(ReferralCommissionService::class);
        $this->assertSame(1, $referrals->releasePending($referrer));
        $this->wallets()->adminAdjust($referrerWallet, '-4', 'Spent', $this->admin->id);

        $this->budgets()->holdForCampaign($campaign);
        $this->deliver($campaign, 3);
        $campaign->forceFill(['status' => Campaign::STATUS_CANCELLED])->save();
        $this->assertSame('17.50', $this->budgets()->refundUndelivered($campaign->fresh()));

        // 17.50 of 25.00 refunded: 3.50 of the 5.00 commission is reversed; 1.00 was available.
        $commission->refresh();
        $this->assertSame(ReferralCommission::STATUS_REVERSED, $commission->status);
        $this->assertSame('1.0000', (string) $commission->reversed_amount);
        $this->assertSame('2.5000', (string) $commission->owed_amount);
        $this->assertSame('0.0000', $this->balance($referrerWallet));

        $this->assertSame(0, $referrals->reverseForSource(Order::query()->findOrFail($campaign->order_id), 'Again', '17.50'));
        $this->assertSame(1, Transaction::query()->where('reference', 'REF-REV-'.$commission->id)->count());

        $this->wallets()->creditFundedReward($referrer, '3.00', 'Campaign reward', TransactionType::CampaignReward, TransactionType::UnfundedCampaignRewardExpense, 'CMP-UNF-900');

        $commission->refresh();
        $this->assertSame('0.0000', (string) $commission->owed_amount);
        $this->assertSame('3.5000', (string) $commission->reversed_amount);
        $this->assertSame('0.5000', $this->balance($referrerWallet));
        $this->assertSame([], app(LedgerReconciliationService::class)->mismatches());
    }

    public function test_reconcile_command_reports_differences_without_changing_anything(): void
    {
        $campaign = $this->campaign();
        $this->budgets()->holdForCampaign($campaign);
        $this->deliver($campaign, 4);

        $this->artisan('wallets:reconcile-ledger')
            ->expectsOutputToContain('Every wallet balance matches')
            ->assertSuccessful();

        $wallet = Wallet::query()->where('user_id', $this->agent->id)->firstOrFail();
        $wallet->forceFill(['balance' => '5.0000'])->save();

        $this->artisan('wallets:reconcile-ledger')->assertFailed();
        $this->assertSame('5.0000', $this->balance($wallet));
    }
}
