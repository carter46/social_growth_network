<?php

namespace Tests\Feature\Payments;

use App\Models\PayoutAccount;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Wallet\Payments\Data\PayoutResult;
use App\Modules\Wallet\Payments\FailureClass;
use App\Modules\Wallet\Services\WithdrawalPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\Support\BuildsPayouts;
use Tests\TestCase;

class WithdrawalPayoutFallbackTest extends TestCase
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

    private function payouts(): WithdrawalPayoutService
    {
        return app(WithdrawalPayoutService::class);
    }

    private function withdrawal(string $amount = '50.00', string $providerFee = '1.00'): Withdrawal
    {
        return $this->pendingWithdrawal($this->agent, $this->account, $amount, $providerFee);
    }

    private function wallet(): array
    {
        $wallet = $this->agent->wallet()->first();

        return [bcadd((string) $wallet->balance, '0', 2), bcadd((string) $wallet->locked_balance, '0', 2)];
    }

    public function test_initial_send_uses_first_gateway_with_net_amount(): void
    {
        $w = $this->withdrawal();

        $this->payouts()->approveAndSend($w, $this->admin->id);

        $w->refresh();
        $attempt = $w->latestAttempt;
        $this->assertSame('processing', $w->status);
        $this->assertSame('bachs', $attempt->gateway_key);
        $this->assertSame(WithdrawalPayoutAttempt::TRIGGER_INITIAL, $attempt->trigger);
        $this->assertSame('WPO-'.$w->id.'-1', $attempt->reference);
        $this->assertSame('49.00', (string) $attempt->amount);
        $this->assertSame('49.00', $this->gateways['bachs']->transfers[0]->amount);
        $this->assertCount(0, $this->gateways['flutterwave']->transfers);
    }

    public function test_not_created_triggers_exactly_one_automatic_fallback(): void
    {
        $this->gateways['bachs']->transferResults = [PayoutResult::notCreated('NOT_CREATED', 'Rejected before creation')];
        $w = $this->withdrawal();

        $this->payouts()->approveAndSend($w, $this->admin->id);

        $attempts = $w->fresh()->attempts;
        $this->assertCount(2, $attempts);
        $this->assertSame(WithdrawalPayoutAttempt::NOT_CREATED, $attempts[0]->status);
        $this->assertSame('flutterwave', $attempts[1]->gateway_key);
        $this->assertSame(WithdrawalPayoutAttempt::TRIGGER_AUTOMATIC_FALLBACK, $attempts[1]->trigger);
        $this->assertNull($attempts[1]->authorised_by);
        $this->assertNotSame($attempts[0]->reference, $attempts[1]->reference);
        $this->assertSame('processing', $w->fresh()->internal_status);
    }

    public function test_no_fallback_when_the_first_gateway_cannot_confirm_nothing_was_paid(): void
    {
        $this->gateways['bachs']->transferResults = [PayoutResult::notCreated('NOT_CREATED', 'Rejected before creation')];
        $this->gateways['bachs']->lookupUnconfirmed = true;
        $w = $this->withdrawal();

        $this->payouts()->approveAndSend($w, $this->admin->id);

        $this->assertCount(1, $w->fresh()->attempts);
        $this->assertCount(0, $this->gateways['flutterwave']->transfers);
        $this->assertTrue($w->fresh()->needsAdminDecision());

        try {
            $this->payouts()->adminRetryViaGateway($w, 'flutterwave', $this->admin->id);
            $this->fail('Retry should wait until the first gateway confirms.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('could not confirm', $e->getMessage());
        }

        $this->gateways['bachs']->lookupUnconfirmed = false;
        $retry = $this->payouts()->adminRetryViaGateway($w, 'flutterwave', $this->admin->id);
        $this->assertSame('flutterwave', $retry->gateway_key);
    }

    public function test_admin_retry_is_refused_when_the_closed_attempt_turns_out_paid(): void
    {
        $w = $this->withdrawal();
        $this->payouts()->approveAndSend($w, $this->admin->id);
        $first = $w->fresh()->latestAttempt;
        $this->gateways['bachs']->fail($first->reference, 'INSUFFICIENT_BALANCE');
        $this->payouts()->reconcileAttempt($first);

        $this->gateways['bachs']->succeed($first->reference);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reports the last attempt as paid');
        $this->payouts()->adminRetryViaGateway($w, 'flutterwave', $this->admin->id);
    }

    public function test_second_not_created_waits_for_admin_instead_of_a_third_attempt(): void
    {
        $this->gateways['bachs']->transferResults = [PayoutResult::notCreated('NOT_CREATED', 'No')];
        $this->gateways['flutterwave']->transferResults = [PayoutResult::notCreated('NOT_CREATED', 'No')];
        $w = $this->withdrawal();

        $this->payouts()->approveAndSend($w, $this->admin->id);

        $this->assertCount(2, $w->fresh()->attempts);
        $this->assertTrue($w->fresh()->needsAdminDecision());
        $this->assertSame(['100.00', '50.00'], $this->wallet());
    }

    public function test_recoverable_failure_waits_for_admin_and_retry_records_who_authorised_it(): void
    {
        $w = $this->withdrawal();
        $this->payouts()->approveAndSend($w, $this->admin->id);
        $first = $w->fresh()->latestAttempt;

        $this->gateways['bachs']->fail($first->reference, 'INSUFFICIENT_BALANCE');
        $this->payouts()->reconcileAttempt($first);

        $w->refresh();
        $this->assertTrue($w->needsAdminDecision());
        $this->assertCount(1, $w->attempts);
        $this->assertSame(FailureClass::RECOVERABLE, $first->fresh()->failure_class);
        $this->assertTrue($first->fresh()->recoverable);

        $retry = $this->payouts()->adminRetryViaGateway($w, 'flutterwave', $this->admin->id);

        $this->assertSame(WithdrawalPayoutAttempt::TRIGGER_ADMIN_RETRY, $retry->trigger);
        $this->assertSame($this->admin->id, (int) $retry->authorised_by);
        $this->assertNotNull($retry->authorised_at);
        $this->assertSame('flutterwave', $retry->gateway_key);
    }

    public function test_unclassified_failure_code_is_treated_as_recoverable(): void
    {
        $w = $this->withdrawal();
        $this->payouts()->approveAndSend($w, $this->admin->id);
        $first = $w->fresh()->latestAttempt;

        $this->gateways['bachs']->fail($first->reference, 'SOMETHING_NEW');
        $this->payouts()->reconcileAttempt($first);

        $this->assertSame(FailureClass::OTHER, $first->fresh()->failure_class);
        $this->assertTrue($first->fresh()->recoverable);
        $this->assertTrue($w->fresh()->needsAdminDecision());
        $this->assertCount(0, $this->gateways['flutterwave']->transfers);
    }

    public function test_unknown_result_never_switches_or_refunds(): void
    {
        $this->gateways['bachs']->transferResults = [PayoutResult::unknown('Timed out')];
        $w = $this->withdrawal();

        $this->payouts()->approveAndSend($w, $this->admin->id);
        $attempt = $w->fresh()->latestAttempt;
        $this->assertSame(WithdrawalPayoutAttempt::UNKNOWN, $attempt->status);
        $this->assertTrue($attempt->isLive());

        $this->assertSame(WithdrawalPayoutAttempt::UNKNOWN, $this->payouts()->reconcileAttempt($attempt));

        try {
            $this->payouts()->adminRetryViaGateway($w, 'flutterwave', $this->admin->id);
            $this->fail('Retry should be refused while the result is unknown.');
        } catch (InvalidArgumentException) {
        }

        try {
            $this->payouts()->refundToWallet($w, $this->admin->id);
            $this->fail('Refund should be refused while the result is unknown.');
        } catch (InvalidArgumentException) {
        }

        $this->assertCount(0, $this->gateways['flutterwave']->transfers);
        $this->assertCount(1, $w->fresh()->attempts);
    }

    public function test_account_details_failure_refunds_in_full_and_is_never_retried(): void
    {
        $w = $this->withdrawal();
        $this->payouts()->approveAndSend($w, $this->admin->id);
        $first = $w->fresh()->latestAttempt;

        $this->gateways['bachs']->fail($first->reference, 'INVALID_ACCOUNT', '2.00');
        $this->payouts()->reconcileAttempt($first);

        $w->refresh();
        $this->assertTrue($w->isTerminal());
        $this->assertSame(['100.00', '0.00'], $this->wallet());
        $this->assertSame(PayoutAccount::REJECTED, $this->account->fresh()->verification);
        $this->assertSame('0.00', bcadd((string) ($w->bounce_fee_amount ?? 0), '0', 2));
        $this->assertCount(0, $this->gateways['flutterwave']->transfers);

        $this->expectException(InvalidArgumentException::class);
        $this->payouts()->adminRetryViaGateway($w, 'flutterwave', $this->admin->id);
    }

    public function test_bounce_fee_is_charged_only_when_every_condition_holds_and_can_be_waived(): void
    {
        SystemSetting::set('withdrawal_bounce_fee', '3.00');
        $this->account->update(['bounce_fee_disclosed' => '3.00', 'bounce_terms_accepted_at' => now()]);
        $w = $this->withdrawal();
        $this->payouts()->approveAndSend($w, $this->admin->id);
        $first = $w->fresh()->latestAttempt;

        $this->gateways['bachs']->fail($first->reference, 'INVALID_ACCOUNT', '2.50');
        $this->payouts()->reconcileAttempt($first);

        $w->refresh();
        $this->assertSame('2.50', bcadd((string) $w->bounce_fee_amount, '0', 2));
        $this->assertSame(['97.50', '0.00'], $this->wallet());

        app(\App\Modules\Wallet\Services\WalletService::class)->waiveBounceFee($w, $this->admin->id);
        $this->assertSame(['100.00', '0.00'], $this->wallet());
        $this->assertNotNull($w->fresh()->bounce_waived_at);
    }

    public function test_no_bounce_fee_when_disclosure_is_below_setting_or_no_return_charge(): void
    {
        SystemSetting::set('withdrawal_bounce_fee', '3.00');

        $this->account->update(['bounce_fee_disclosed' => '2.00', 'bounce_terms_accepted_at' => now()]);
        $w = $this->withdrawal();
        $this->payouts()->approveAndSend($w, $this->admin->id);
        $this->gateways['bachs']->fail($w->fresh()->latestAttempt->reference, 'INVALID_ACCOUNT', '2.50');
        $this->payouts()->reconcileAttempt($w->fresh()->latestAttempt);
        $this->assertSame(['100.00', '0.00'], $this->wallet());

        $other = $this->payoutAccount($this->agent, ['is_primary' => false, 'bounce_fee_disclosed' => '3.00']);
        $w2 = $this->pendingWithdrawal($this->agent, $other, '20.00');
        $this->payouts()->approveAndSend($w2, $this->admin->id);
        $this->gateways['bachs']->fail($w2->fresh()->latestAttempt->reference, 'INVALID_ACCOUNT');
        $this->payouts()->reconcileAttempt($w2->fresh()->latestAttempt);
        $this->assertSame(['100.00', '0.00'], $this->wallet());
    }

    public function test_success_completes_withdrawal_and_resolves_pending_account(): void
    {
        $this->account->update(['verification' => PayoutAccount::PENDING_REVIEW]);
        $w = $this->withdrawal();
        $this->payouts()->approveAndSend($w, $this->admin->id);
        $attempt = $w->fresh()->latestAttempt;

        $this->gateways['bachs']->succeed($attempt->reference);
        $this->payouts()->reconcileAttempt($attempt);

        $this->assertSame('completed', $w->fresh()->status);
        $this->assertSame(['50.00', '0.00'], $this->wallet());
        $this->assertSame(PayoutAccount::RESOLVED, $this->account->fresh()->verification);
        $this->assertSame(WithdrawalPayoutAttempt::SUCCEEDED, $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->live_flag);
    }

    public function test_only_one_live_attempt_per_withdrawal(): void
    {
        $w = $this->withdrawal();
        $this->payouts()->approveAndSend($w, $this->admin->id);

        try {
            $this->payouts()->approveAndSend($w->fresh(), $this->admin->id);
            $this->fail('A second approval should be refused.');
        } catch (InvalidArgumentException) {
        }

        $w->fresh()->update(['internal_status' => Withdrawal::INTERNAL_NEEDS_ADMIN_DECISION]);
        try {
            $this->payouts()->adminRetryViaGateway($w, 'flutterwave', $this->admin->id);
            $this->fail('A retry should be refused while an attempt is live.');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame(1, WithdrawalPayoutAttempt::query()->where('withdrawal_id', $w->id)->whereNotNull('live_flag')->count());
        $this->assertCount(1, $this->gateways['bachs']->transfers);
    }

    public function test_requote_keeps_lower_fee_and_absorbs_within_tolerance(): void
    {
        $this->setQuote('bachs', '0.50');
        $w = $this->withdrawal();
        $this->payouts()->approveAndSend($w, $this->admin->id);
        $this->assertSame('0.00', bcadd((string) $w->fresh()->absorbed_fee, '0', 2));
        $this->assertSame('49.00', $this->gateways['bachs']->transfers[0]->amount);

        $this->setQuote('bachs', '1.90');
        $w2 = $this->pendingWithdrawal($this->agent, $this->account, '20.00');
        $this->payouts()->approveAndSend($w2, $this->admin->id);
        $this->assertSame('0.90', bcadd((string) $w2->fresh()->absorbed_fee, '0', 2));
        $this->assertSame('19.00', $this->gateways['bachs']->transfers[1]->amount);
    }

    public function test_requote_above_tolerance_asks_agent_and_accepting_resends(): void
    {
        $this->setQuote('bachs', '3.00');
        $w = $this->withdrawal();

        $this->payouts()->approveAndSend($w, $this->admin->id);

        $w->refresh();
        $this->assertTrue($w->awaitingAgentConfirmation());
        $this->assertTrue($w->confirmation_expires_at->between(now()->addDays(6), now()->addDays(8)));
        $this->assertCount(0, $this->gateways['bachs']->transfers);

        $this->payouts()->agentAcceptFee($w);
        $w->refresh();
        $this->assertSame('pending_review', $w->internal_status);
        $this->assertSame('47.00', bcadd((string) $w->net_amount, '0', 2));

        $this->payouts()->approveAndSend($w, $this->admin->id);
        $this->assertSame('47.00', $this->gateways['bachs']->transfers[0]->amount);
    }

    public function test_cancel_or_expiry_refunds_the_full_amount(): void
    {
        $this->setQuote('bachs', '5.00');
        $w = $this->pendingWithdrawal($this->agent, $this->account, '30.00');
        $this->payouts()->approveAndSend($w, $this->admin->id);
        $this->payouts()->agentCancel($w);
        $this->assertTrue($w->fresh()->isTerminal());
        $this->assertSame(['100.00', '0.00'], $this->wallet());

        $w2 = $this->pendingWithdrawal($this->agent, $this->account, '30.00');
        $this->payouts()->approveAndSend($w2, $this->admin->id);
        $this->travel(8)->days();
        $this->payouts()->expireAgentConfirmation($w2);
        $this->assertTrue($w2->fresh()->isTerminal());
        $this->assertSame(['100.00', '0.00'], $this->wallet());
    }

    public function test_accepting_a_fee_larger_than_the_withdrawal_is_refused(): void
    {
        $this->setQuote('bachs', '12.00');
        $w = $this->pendingWithdrawal($this->agent, $this->account, '10.00');
        $this->payouts()->approveAndSend($w, $this->admin->id);

        $this->expectException(InvalidArgumentException::class);
        $this->payouts()->agentAcceptFee($w);
    }
}
