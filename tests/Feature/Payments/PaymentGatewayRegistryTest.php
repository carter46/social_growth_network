<?php

namespace Tests\Feature\Payments;

use App\Models\PaymentWebhook;
use App\Models\PayoutConnection;
use App\Models\PayoutCoverage;
use App\Modules\Wallet\Payments\Exceptions\GatewayUnavailable;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Modules\Wallet\Services\WithdrawalPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BuildsPayouts;
use Tests\TestCase;

class PaymentGatewayRegistryTest extends TestCase
{
    use BuildsPayouts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->installPayoutGateways();
    }

    private function registry(): GatewayRegistry
    {
        return app(GatewayRegistry::class);
    }

    public function test_only_gateways_that_charge_usd_are_offered_at_checkout(): void
    {
        $this->assertSame(['bachs', 'flutterwave'], array_keys($this->registry()->availableCollectionGateways()));

        $this->gateways['bachs']->currencyList = ['NGN'];
        $this->assertSame(['flutterwave'], array_keys($this->registry()->availableCollectionGateways()));
        $this->assertSame('flutterwave', $this->registry()->defaultCollectionKey());

        $this->gateways['flutterwave']->configured = false;
        $this->assertFalse($this->registry()->hasCollectionGateway());

        $this->expectException(GatewayUnavailable::class);
        $this->registry()->collection();
    }

    public function test_payout_route_follows_order_and_skips_unverified_gateways(): void
    {
        $this->coverRoute('NG');
        $this->assertSame(['bachs', 'flutterwave'], array_keys($this->registry()->payoutRoute('NG', 'bank_account')));

        PayoutCoverage::query()->where('gateway_key', 'bachs')->update(['state' => PayoutCoverage::DISABLED]);
        $this->assertSame(['flutterwave'], array_keys($this->registry()->payoutRoute('NG', 'bank_account')));

        $this->gateways['flutterwave']->configured = false;
        $this->assertSame([], $this->registry()->payoutRoute('NG', 'bank_account'));
    }

    public function test_active_bachs_connect_allows_admin_approve_without_coverage_row(): void
    {
        // No coverRoute() — same gap that blocked live approve after the agent could still request.
        $agent = $this->agentWithBalance('100.00');
        PayoutConnection::query()->create([
            'user_id' => $agent->id,
            'provider' => 'bachs',
            'account_id' => 'acct_connect_1',
            'status' => PayoutConnection::ACTIVE,
            'capabilities' => ['transfers' => 'active', 'payouts' => 'active'],
        ]);
        $account = $this->payoutAccount($agent);
        $withdrawal = $this->pendingWithdrawal($agent, $account);

        $this->assertSame([], array_keys($this->registry()->payoutRoute('NG', 'bank_account')));
        $this->assertSame(['bachs'], array_keys($this->registry()->payoutRoute('NG', 'bank_account', $agent->id)));
        $this->assertSame(['bachs'], array_keys(app(WithdrawalPayoutService::class)->routeOptions($withdrawal)));

        app(WithdrawalPayoutService::class)->approveAndSend($withdrawal, $this->adminUser()->id);

        $this->assertNotNull($withdrawal->fresh()->latestAttempt);
        $this->assertSame('bachs', $withdrawal->fresh()->latestAttempt->gateway_key);
    }

    public function test_payout_webhook_is_stored_once_and_reverified_with_the_provider(): void
    {
        $this->coverRoute('NG');
        $agent = $this->agentWithBalance('100.00');
        $withdrawal = $this->pendingWithdrawal($agent, $this->payoutAccount($agent));
        app(WithdrawalPayoutService::class)->approveAndSend($withdrawal, $this->adminUser()->id);
        $attempt = $withdrawal->fresh()->latestAttempt;

        $payload = ['id' => 'evt-1', 'type' => 'payout', 'status' => 'succeeded', 'reference' => $attempt->reference];

        $this->postJson('/webhooks/payments/bachs', $payload)->assertOk();
        $this->assertSame('processing', $withdrawal->fresh()->status);

        $this->gateways['bachs']->succeed($attempt->reference);
        $this->postJson('/webhooks/payments/bachs', ['id' => 'evt-2'] + $payload)->assertOk();
        $this->postJson('/webhooks/payments/bachs', ['id' => 'evt-2'] + $payload)->assertOk();

        $this->assertSame('completed', $withdrawal->fresh()->status);
        $this->assertSame(2, PaymentWebhook::query()->where('provider', 'bachs')->count());
    }

    public function test_webhook_from_another_provider_does_not_touch_the_attempt(): void
    {
        $this->coverRoute('NG');
        $agent = $this->agentWithBalance('100.00');
        $withdrawal = $this->pendingWithdrawal($agent, $this->payoutAccount($agent));
        app(WithdrawalPayoutService::class)->approveAndSend($withdrawal, $this->adminUser()->id);
        $attempt = $withdrawal->fresh()->latestAttempt;
        $this->gateways['flutterwave']->succeed($attempt->reference);

        $this->postJson('/webhooks/payments/flutterwave', [
            'id' => 'evt-x', 'type' => 'payout', 'status' => 'succeeded', 'reference' => $attempt->reference,
        ])->assertOk();

        $this->assertSame('processing', $withdrawal->fresh()->status);
        $this->assertSame('ignored', PaymentWebhook::query()->where('provider', 'flutterwave')->value('status'));
    }

    public function test_unknown_provider_is_not_found(): void
    {
        $this->postJson('/webhooks/payments/monnify-old', ['id' => 'x'])->assertNotFound();
    }
}
