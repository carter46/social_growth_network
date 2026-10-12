<?php

namespace Tests\Feature\Payments;

use App\Models\PayoutCoverage;
use App\Models\PayoutFeeRule;
use App\Models\SystemSetting;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Wallet\Payments\Data\CoverageRoute;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Payments\Data\PayoutQuote;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Modules\Wallet\Services\PayoutCoverageService;
use App\Modules\Wallet\Services\PayoutQuoteService;
use App\Modules\Wallet\Services\WithdrawalFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\Support\BuildsPayouts;
use Tests\TestCase;

class PayoutFeeAndCoverageTest extends TestCase
{
    use BuildsPayouts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->installPayoutGateways();
        $this->coverRoute();
        SystemSetting::set('withdrawal_min_amount', '10');
        SystemSetting::set('withdrawal_max_amount', '5000');
    }

    private function fees(): WithdrawalFeeService
    {
        return app(WithdrawalFeeService::class);
    }

    private function verifiedRule(array $overrides = []): PayoutFeeRule
    {
        return PayoutFeeRule::create(array_merge([
            'country' => 'NG',
            'method' => 'bank_account',
            'gateway_key' => 'bachs',
            'flat_fee' => 0,
            'percent_fee' => 0,
            'verified_provider_fee_flat' => '0.50',
            'verified_provider_fee_percent' => '1.000',
            'verified_source' => PayoutFeeRule::SOURCE_PROVIDER_INVOICE,
            'verified_at' => now(),
            'active' => true,
        ], $overrides));
    }

    private function destination(): PayoutAccountDetails
    {
        return PayoutAccountDetails::fromPayoutAccount($this->payoutAccount($this->agentWithBalance()));
    }

    public function test_live_quote_is_preferred_over_a_verified_entry(): void
    {
        $this->verifiedRule();
        $quote = app(PayoutQuoteService::class)->quote('50.00', $this->destination(), 'bachs');

        $this->assertSame(PayoutQuote::SOURCE_LIVE, $quote->source);
        $this->assertSame('1.00', $quote->providerFee);
        $this->assertSame('bachs', $quote->gatewayKey);
    }

    public function test_verified_entry_is_used_without_a_live_quote_and_rounds_up_to_the_cent(): void
    {
        $this->gateways['bachs']->quote = null;
        $this->verifiedRule();

        $quote = app(PayoutQuoteService::class)->quote('33.33', $this->destination(), 'bachs');

        $this->assertSame(PayoutQuote::SOURCE_VERIFIED_ENTRY, $quote->source);
        $this->assertSame('0.84', $quote->providerFee);
    }

    public function test_stale_verified_entry_means_the_route_is_unavailable(): void
    {
        $this->gateways['bachs']->quote = null;
        $this->verifiedRule(['verified_at' => now()->subDays(120)]);
        $account = $this->payoutAccount($this->agentWithBalance());

        $this->assertNull(app(PayoutQuoteService::class)->quote('50.00', PayoutAccountDetails::fromPayoutAccount($account), 'bachs'));

        try {
            $this->fees()->quote('50.00', $account);
            $this->fail('A withdrawal without a known fee should be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('temporarily unavailable', $e->errors()['amount'][0]);
        }
    }

    public function test_effective_minimum_is_the_highest_floor(): void
    {
        config(['payments.payout.max_fee_share_percent' => 20]);
        $this->setQuote('bachs', '3.00');
        $quote = $this->liveQuote('3.00');

        $this->assertSame('15.00', $this->fees()->limits('NG', 'bank_account', 'bachs', $quote)['min']);

        PayoutFeeRule::create(['country' => 'NG', 'method' => 'bank_account', 'flat_fee' => 0, 'percent_fee' => 0, 'min_withdrawal' => '25', 'active' => true]);
        $this->assertSame('25.00', $this->fees()->limits('NG', 'bank_account', 'bachs', $quote)['min']);

        PayoutCoverage::query()->where('gateway_key', 'bachs')->where('country', 'NG')->update(['provider_min' => '45000']);
        $this->assertSame('30.00', $this->fees()->limits('NG', 'bank_account', 'bachs', $quote)['min']);

        PayoutCoverage::query()->where('gateway_key', 'bachs')->where('country', 'NG')->update(['provider_max' => '1500000']);
        $this->assertSame('1000.00', $this->fees()->limits('NG', 'bank_account', 'bachs', $quote)['max']);
    }

    public function test_quote_breakdown_and_refusals(): void
    {
        config(['payments.payout.max_fee_share_percent' => 0]);
        $account = $this->payoutAccount($this->agentWithBalance());
        PayoutFeeRule::create(['flat_fee' => '0.25', 'percent_fee' => '1', 'active' => true]);

        $breakdown = $this->fees()->quote('40.00', $account);
        $this->assertSame('1.00', $breakdown['provider_fee']);
        $this->assertSame('0.65', $breakdown['platform_fee']);
        $this->assertSame('38.35', $breakdown['net']);
        $this->assertSame('bachs', $breakdown['gateway_key']);

        $this->expectException(ValidationException::class);
        $this->fees()->quote('5.00', $account);
    }

    public function test_fee_larger_than_the_amount_is_refused(): void
    {
        config(['payments.payout.max_fee_share_percent' => 0]);
        $this->setQuote('bachs', '12.00');
        $this->setQuote('flutterwave', '12.00');
        $account = $this->payoutAccount($this->agentWithBalance());

        try {
            $this->fees()->quote('11.00', $account);
            $this->fail('Net at or below zero should be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('fee is larger', $e->errors()['amount'][0]);
        }
    }

    public function test_tolerance_is_the_larger_of_one_dollar_and_five_percent(): void
    {
        $this->assertSame('1.00', $this->fees()->tolerance('4.00'));
        $this->assertSame('2.50', $this->fees()->tolerance('50.00'));
    }

    public function test_fees_page_refuses_a_minimum_at_or_below_the_verified_fee(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.fees-limits.rules.store'), [
            'country' => 'NG',
            'method' => 'bank_account',
            'gateway_key' => 'bachs',
            'verified_provider_fee_flat' => '5.00',
            'verified_source' => PayoutFeeRule::SOURCE_PROVIDER_INVOICE,
            'min_withdrawal' => '5.00',
            'active' => '1',
        ])->assertSessionHasErrors('min_withdrawal');

        $this->actingAs($admin)->post(route('admin.fees-limits.rules.store'), [
            'country' => 'NG',
            'verified_provider_fee_flat' => '5.00',
            'verified_source' => PayoutFeeRule::SOURCE_TEST_PAYOUT,
            'min_withdrawal' => '20.00',
        ])->assertSessionHasErrors('verified_reference');

        $this->actingAs($admin)->post(route('admin.fees-limits.rules.store'), [
            'country' => 'NG',
            'verified_provider_fee_flat' => '5.00',
            'verified_source' => PayoutFeeRule::SOURCE_PROVIDER_INVOICE,
            'min_withdrawal' => '20.00',
            'active' => '1',
        ])->assertSessionHasNoErrors();

        $rule = PayoutFeeRule::query()->firstOrFail();
        $this->assertNotNull($rule->verified_at);
        $this->assertSame($admin->id, (int) $rule->verified_by);
    }

    // ---------------------------------------------------------------- coverage

    public function test_sync_verifies_reported_routes_and_never_reenables_disabled_ones(): void
    {
        $this->coverRoute('GH', 'bank_account', ['bachs'], PayoutCoverage::DISABLED);
        $this->coverRoute('US', 'bank_account', ['bachs']);
        $this->gateways['bachs']->coverageRoutes = [
            new CoverageRoute('NG', 'bank_account', 'NGN', true),
            new CoverageRoute('GH', 'bank_account', 'GHS'),
            new CoverageRoute('ZZ', 'bank_account', 'USD'),
        ];

        $summary = app(PayoutCoverageService::class)->sync('bachs');

        $this->assertTrue($summary['bachs']['ok']);
        $row = fn (string $country) => PayoutCoverage::query()->where('gateway_key', 'bachs')->where('country', $country)->first();
        $this->assertSame(PayoutCoverage::VERIFIED_API, $row('NG')->state);
        $this->assertSame(PayoutCoverage::DISABLED, $row('GH')->state);
        $this->assertSame(PayoutCoverage::UNAVAILABLE, $row('US')->state);
        $this->assertNull($row('ZZ'));
        $this->assertSame([], app(GatewayRegistry::class)->payoutRoute('US', 'bank_account'));
    }

    public function test_failed_sync_keeps_the_last_verified_state(): void
    {
        $this->gateways['bachs']->coverageThrows = true;

        $summary = app(PayoutCoverageService::class)->sync('bachs');

        $this->assertFalse($summary['bachs']['ok']);
        $row = PayoutCoverage::query()->where('gateway_key', 'bachs')->where('country', 'NG')->first();
        $this->assertSame(PayoutCoverage::VERIFIED_API, $row->state);
        $this->assertNotNull($row->last_error);
    }

    public function test_manual_enable_needs_a_confirmed_test_payout_and_a_note_and_expires(): void
    {
        $admin = $this->adminUser();
        $coverage = app(PayoutCoverageService::class);

        try {
            $coverage->enableManually('bachs', 'GB', 'bank_account', 'WPO-1-1', '', $admin->id);
            $this->fail('A note is required.');
        } catch (InvalidArgumentException) {
        }

        try {
            $coverage->enableManually('bachs', 'GB', 'bank_account', 'missing-ref', 'Tested by ops', $admin->id);
            $this->fail('An unconfirmed reference is refused.');
        } catch (InvalidArgumentException) {
        }

        $this->gateways['bachs']->succeed('prov-test-1');
        $row = $coverage->enableManually('bachs', 'GB', 'bank_account', 'prov-test-1', 'Tested by ops', $admin->id);

        $this->assertSame(PayoutCoverage::VERIFIED_MANUAL, $row->state);
        $this->assertSame($admin->id, (int) $row->verified_by);
        $this->assertTrue(PayoutCoverage::isUsable('bachs', 'GB', 'bank_account'));

        $this->travel((int) config('payments.payout.manual_coverage_days', 90) + 1)->days();
        $this->assertFalse(PayoutCoverage::isUsable('bachs', 'GB', 'bank_account'));
    }

    public function test_manual_enable_accepts_our_own_succeeded_attempt_for_that_route(): void
    {
        $admin = $this->adminUser();
        $agent = $this->agentWithBalance();
        $this->coverRoute('GB', 'bank_account', ['flutterwave']);
        $account = $this->payoutAccount($agent, [
            'country' => 'GB', 'currency' => 'GBP', 'bank_code' => null,
            'details' => ['bank_name' => 'Example Bank', 'sort_code' => '123456', 'account_number' => '12345678'],
        ]);
        $withdrawal = $this->pendingWithdrawal($agent, $account, '20.00');
        WithdrawalPayoutAttempt::create([
            'withdrawal_id' => $withdrawal->id,
            'attempt_no' => 1,
            'gateway_key' => 'bachs',
            'reference' => 'WPO-'.$withdrawal->id.'-1',
            'status' => WithdrawalPayoutAttempt::SUCCEEDED,
            'trigger' => WithdrawalPayoutAttempt::TRIGGER_INITIAL,
            'amount' => '19.00',
        ]);

        $row = app(PayoutCoverageService::class)->enableManually('bachs', 'GB', 'bank_account', 'WPO-'.$withdrawal->id.'-1', 'Paid in test', $admin->id);

        $this->assertSame(PayoutCoverage::VERIFIED_MANUAL, $row->state);
        $this->assertSame(['bachs', 'flutterwave'], array_keys(app(GatewayRegistry::class)->payoutRoute('GB', 'bank_account')));
    }
}
