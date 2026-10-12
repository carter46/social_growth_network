<?php

namespace Tests\Feature\Payments;

use App\Enums\TransactionType;
use App\Models\PayoutAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Payments\Data\PayoutResult;
use App\Modules\Wallet\Services\WalletService;
use App\Modules\Wallet\Services\WithdrawalPayoutService;
use App\Services\Reporting\Metrics\PlatformRevenueMetric;
use App\Services\Reporting\ReportingRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BuildsPayouts;
use Tests\TestCase;

class WalletAccountingTest extends TestCase
{
    use BuildsPayouts;
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private PayoutAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();

        $this->installPayoutGateways();
        $this->coverRoute();
        $this->admin = $this->adminUser();
        $this->agent = $this->agentWithBalance('100.00');
        $this->account = $this->payoutAccount($this->agent);
    }

    private function platformWallet(): Wallet
    {
        return app(WalletService::class)->getPlatformWallet()->fresh();
    }

    /** @return array<string, string> type => amount */
    private function platformEntries(int $withdrawalId): array
    {
        return Transaction::query()
            ->where('wallet_id', $this->platformWallet()->id)
            ->where('withdrawal_id', $withdrawalId)
            ->get()
            ->mapWithKeys(fn (Transaction $t) => [$t->type => bcadd((string) $t->amount, '0', 4)])
            ->all();
    }

    public function test_withdrawal_fee_is_split_into_income_recovered_and_cost(): void
    {
        $before = (string) $this->platformWallet()->balance;
        $w = $this->pendingWithdrawal($this->agent, $this->account, '50.00', '1.00', '0.50');
        $this->gateways['bachs']->transferResults = [
            new PayoutResult(PayoutResult::SUCCEEDED, 'prov-1', fee: '1.20'),
        ];

        app(WithdrawalPayoutService::class)->approveAndSend($w, $this->admin->id);

        $this->assertSame('completed', $w->fresh()->status);
        $entries = $this->platformEntries($w->id);
        $this->assertSame('0.5000', $entries[TransactionType::WithdrawalFee->value]);
        $this->assertSame('1.0000', $entries[TransactionType::PayoutProviderFeeRecovered->value]);
        $this->assertSame('-1.2000', $entries[TransactionType::PayoutProviderFeeCost->value]);
        $this->assertArrayNotHasKey(TransactionType::PayoutFeeAbsorbed->value, $entries);
        $this->assertSame(0, bccomp(bcadd($before, '0.30', 4), (string) $this->platformWallet()->balance, 4));
    }

    public function test_cost_falls_back_to_quoted_fee_when_provider_reports_none(): void
    {
        $w = $this->pendingWithdrawal($this->agent, $this->account, '50.00', '1.00', '0.00');
        $this->gateways['bachs']->transferResults = [new PayoutResult(PayoutResult::SUCCEEDED, 'prov-1')];

        app(WithdrawalPayoutService::class)->approveAndSend($w, $this->admin->id);

        $entries = $this->platformEntries($w->id);
        $this->assertArrayNotHasKey(TransactionType::WithdrawalFee->value, $entries);
        $this->assertSame('1.0000', $entries[TransactionType::PayoutProviderFeeRecovered->value]);
        $this->assertSame('-1.0000', $entries[TransactionType::PayoutProviderFeeCost->value]);
    }

    public function test_funded_reward_debits_the_platform_and_a_retry_does_not_pay_twice(): void
    {
        $wallets = app(WalletService::class);
        $platform = $this->platformWallet();
        $platform->forceFill(['balance' => '10.0000'])->save();

        foreach ([1, 2] as $_) {
            $wallets->creditFundedReward($this->agent, '0.0012', 'Referral commission', TransactionType::ReferralCommission, TransactionType::ReferralCommissionExpense, 'REF-COM-77');
        }

        $this->assertSame('9.9988', bcadd((string) $this->platformWallet()->balance, '0', 4));
        $this->assertSame('100.0012', bcadd((string) $this->agent->wallet()->first()->balance, '0', 4));
        $this->assertSame(1, Transaction::query()->where('reference', 'REF-COM-77')->count());
        $expense = Transaction::query()->where('reference', 'REF-COM-77-P')->firstOrFail();
        $this->assertSame(TransactionType::ReferralCommissionExpense->value, $expense->type);
        $this->assertSame('-0.0012', bcadd((string) $expense->amount, '0', 4));
    }

    public function test_funded_reward_is_paid_when_the_platform_wallet_is_short(): void
    {
        $this->platformWallet()->forceFill(['balance' => '0.0000', 'locked_balance' => '0.0000'])->save();

        app(WalletService::class)->creditFundedReward($this->agent, '2.50', 'Campaign reward', TransactionType::CampaignReward, TransactionType::UnfundedCampaignRewardExpense, 'CMP-UNF-5');

        $this->assertSame('-2.5000', bcadd((string) $this->platformWallet()->balance, '0', 4));
        $this->assertSame('102.5000', bcadd((string) $this->agent->wallet()->first()->balance, '0', 4));
    }

    public function test_sub_cent_rewards_are_visible_add_up_and_become_withdrawable(): void
    {
        $agent = $this->agentWithBalance('9.9990', 'Grace Hopper');
        $wallets = app(WalletService::class);
        $wallets->creditFundedReward($agent, '0.0005', 'Reward', TransactionType::CampaignReward, TransactionType::UnfundedCampaignRewardExpense, 'CMP-UNF-901');

        $wallet = $agent->wallet()->first();
        $this->assertSame('9.9995', $wallet->availableBalance());
        $this->assertSame('9.99', $wallet->withdrawableBalance());
        $this->assertSame('0.0095', $wallet->subCentRemainder());
        $this->assertSame('$9.9995', money($wallet->availableBalance()));
        $this->assertSame('$0.0012', money('0.0012'));

        $wallets->creditFundedReward($agent, '0.0005', 'Reward', TransactionType::CampaignReward, TransactionType::UnfundedCampaignRewardExpense, 'CMP-UNF-902');
        $wallet->refresh();
        $this->assertSame('10.0000', $wallet->availableBalance());
        $this->assertSame('10.00', $wallet->withdrawableBalance());

        $ledger = Transaction::query()->where('wallet_id', $wallet->id)->where('reference', 'like', 'CMP-UNF-90%')->get()
            ->reduce(fn (string $sum, Transaction $t) => bcadd($sum, (string) $t->amount, 4), '0');
        $this->assertSame('0.0010', $ledger);
    }

    public function test_revenue_counts_only_the_platform_fee(): void
    {
        $w = $this->pendingWithdrawal($this->agent, $this->account, '50.00', '1.00', '0.50');
        $this->gateways['bachs']->transferResults = [new PayoutResult(PayoutResult::SUCCEEDED, 'prov-1', fee: '1.00')];
        app(WithdrawalPayoutService::class)->approveAndSend($w, $this->admin->id);

        $range = new ReportingRange('today', now()->subDay(), now()->addMinute());
        $this->assertSame(0.5, app(PlatformRevenueMetric::class)->sum($range));
    }
}
