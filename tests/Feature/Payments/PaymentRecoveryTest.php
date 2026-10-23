<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\PaymentWebhook;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFunding;
use App\Modules\Wallet\Payments\PaymentEventProcessor;
use App\Modules\Wallet\Services\DepositCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BuildsPayouts;
use Tests\TestCase;

class PaymentRecoveryTest extends TestCase
{
    use BuildsPayouts;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();

        $this->installPayoutGateways();
        $this->admin = $this->adminUser();
    }

    private function creatorFunding(string $reference, array $overrides = []): WalletFunding
    {
        $creator = User::factory()->create(['email_verified_at' => now()]);
        $wallet = Wallet::factory()->create(['user_id' => $creator->id, 'balance' => 0, 'locked_balance' => 0]);

        return WalletFunding::create(array_merge([
            'user_id' => $creator->id,
            'wallet_id' => $wallet->id,
            'method' => 'bachs_checkout',
            'provider' => 'bachs',
            'amount' => '25.00',
            'currency' => 'USD',
            'status' => 'processing',
            'internal_status' => 'processing',
            'reference' => $reference,
            'provider_payment_reference' => $reference,
        ], $overrides));
    }

    private function collectionWebhook(string $id, string $reference, string $status = 'succeeded'): array
    {
        return ['id' => $id, 'type' => 'collection', 'status' => $status, 'reference' => $reference, 'amount' => '25.00'];
    }

    public function test_failed_webhook_is_reprocessed_when_the_provider_redelivers_it(): void
    {
        $funding = $this->creatorFunding('DEP-RETRY-1');
        $this->gateways['bachs']->verifyAmount = '25.00';
        $payload = $this->collectionWebhook('evt-retry-1', 'DEP-RETRY-1');
        $webhook = PaymentWebhook::create([
            'provider' => 'bachs', 'event' => 'collection', 'payload' => $payload, 'headers' => [],
            'signature_valid' => true, 'idempotency_key' => 'evt:evt-retry-1', 'status' => 'received', 'received_at' => now(),
        ]);

        $this->gateways['bachs']->processFailures = 1;
        try {
            app(PaymentEventProcessor::class)->process($webhook);
        } catch (\RuntimeException) {
        }

        $webhook->refresh();
        $this->assertSame('failed', $webhook->status);
        $this->assertSame(1, (int) $webhook->attempts);
        $this->assertNotNull($webhook->next_retry_at);
        $this->assertSame('0.0000', bcadd((string) $funding->wallet->fresh()->balance, '0', 4));

        $this->postJson('/webhooks/payments/bachs', $payload)->assertOk();

        $this->assertSame('processed', $webhook->fresh()->status);
        $this->assertSame('approved', $funding->fresh()->status);
        $this->assertSame(1, PaymentWebhook::query()->where('idempotency_key', 'evt:evt-retry-1')->count());
    }

    public function test_reconcile_processes_stuck_received_webhooks(): void
    {
        $funding = $this->creatorFunding('DEP-STUCK-RECV');
        $this->gateways['bachs']->verifyAmount = '25.00';
        $webhook = PaymentWebhook::create([
            'provider' => 'bachs',
            'event' => 'collection',
            'payload' => $this->collectionWebhook('evt-stuck-recv', 'DEP-STUCK-RECV'),
            'headers' => [],
            'signature_valid' => true,
            'idempotency_key' => 'evt:evt-stuck-recv',
            'status' => 'received',
            'received_at' => now()->subMinutes(5),
        ]);

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('processed', $webhook->fresh()->status);
        $this->assertSame('approved', $funding->fresh()->status);
    }

    public function test_reconcile_retries_failed_webhooks_on_backoff_then_alerts(): void
    {
        $this->creatorFunding('DEP-RETRY-2');
        $this->gateways['bachs']->verifyAmount = '25.00';
        $webhook = PaymentWebhook::create([
            'provider' => 'bachs', 'event' => 'collection', 'payload' => $this->collectionWebhook('evt-retry-2', 'DEP-RETRY-2'),
            'headers' => [], 'signature_valid' => true, 'idempotency_key' => 'evt:evt-retry-2', 'status' => 'failed',
            'received_at' => now(), 'attempts' => PaymentEventProcessor::MAX_ATTEMPTS - 1, 'next_retry_at' => now()->addMinutes(10),
        ]);

        $this->gateways['bachs']->processFailures = 1;
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame(PaymentEventProcessor::MAX_ATTEMPTS - 1, (int) $webhook->fresh()->attempts);

        $webhook->update(['next_retry_at' => now()->subMinute()]);
        $this->artisan('payments:reconcile')->assertSuccessful();

        $webhook->refresh();
        $this->assertSame('failed', $webhook->status);
        $this->assertSame(PaymentEventProcessor::MAX_ATTEMPTS, (int) $webhook->attempts);
        $this->assertNull($webhook->next_retry_at);
        $this->assertDatabaseHas('admin_notifications', ['type' => 'payment.webhook_failed']);
    }

    public function test_reconcile_checks_the_longest_unchecked_records_first(): void
    {
        $this->gateways['bachs']->verifyStatus = 'pending';
        $recent = $this->creatorFunding('DEP-ORDER-1');
        $never = $this->creatorFunding('DEP-ORDER-2');
        WalletFunding::query()->whereKey([$recent->id, $never->id])->update(['created_at' => now()->subHour()]);
        WalletFunding::query()->whereKey($recent->id)->update(['last_checked_at' => now()->subMinutes(5)]);

        $this->artisan('payments:reconcile', ['--limit' => 1])->assertSuccessful();

        $this->assertNotNull($never->fresh()->last_checked_at);
        $this->assertTrue($recent->fresh()->last_checked_at->lt(now()->subMinutes(4)));
    }

    public function test_deposit_paid_after_being_marked_failed_alerts_and_waits_for_admin_credit(): void
    {
        $funding = $this->creatorFunding('DEP-LATE-1', ['status' => 'failed', 'internal_status' => 'failed']);
        $this->gateways['bachs']->verifyAmount = '25.00';

        app(DepositCheckoutService::class)->completeFromReturn('DEP-LATE-1');

        $funding->refresh();
        $this->assertSame(DepositCheckoutService::PAID_AFTER_FAILURE, $funding->internal_status);
        $this->assertSame('0.0000', bcadd((string) $funding->wallet->fresh()->balance, '0', 4));
        $this->assertDatabaseHas('admin_notifications', ['type' => 'payment.paid_after_failure']);

        $this->actingAs($this->admin)->get(route('admin.reconciliation'))->assertOk()->assertSee('Paid after marked failed');

        $this->gateways['bachs']->verifyStatus = 'pending';
        $this->actingAs($this->admin)->post(route('admin.reconciliation.fix-funding', $funding))->assertSessionHas('error');
        $this->assertSame('0.0000', bcadd((string) $funding->wallet->fresh()->balance, '0', 4));

        $this->gateways['bachs']->verifyStatus = 'succeeded';
        $this->actingAs($this->admin)->post(route('admin.reconciliation.fix-funding', $funding))->assertSessionHas('status');
        $this->assertSame('25.0000', bcadd((string) $funding->wallet->fresh()->balance, '0', 4));
        $this->assertSame('approved', $funding->fresh()->status);
    }

    public function test_payment_on_a_replaced_order_reference_is_matched_and_alerted(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $order = Order::create([
            'source' => 'platform',
            'user_id' => $buyer->id,
            'reference' => 'PLT-REPLACED',
            'amount' => '25.00',
            'total_amount' => '25.00',
            'status' => 'pending',
            'payment_method' => 'gateway',
            'payment_provider' => 'flutterwave',
            'provider_payment_reference' => 'PLT-PAY-NEW',
            'payment_metadata' => ['replaced_references' => [['provider' => 'bachs', 'reference' => 'PLT-PAY-OLD']]],
        ]);

        $this->postJson('/webhooks/payments/bachs', $this->collectionWebhook('evt-old-ref', 'PLT-PAY-OLD'))->assertOk();

        $this->assertDatabaseHas('admin_notifications', ['type' => 'payment.replaced_reference_paid']);
        $this->assertDatabaseMissing('admin_notifications', ['type' => 'payment.gateway_unmatched']);
        $this->assertSame('pending', $order->fresh()->status);
    }
}
