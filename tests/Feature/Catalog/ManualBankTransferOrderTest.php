<?php

namespace Tests\Feature\Catalog;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Models\Order;
use App\Models\PlatformProductVariant;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Communications\Email\EmailService;
use App\Services\Communications\Email\SendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ManualBankTransferOrderTest extends TestCase
{
    use RefreshDatabase;

    private function financeAdmin(): User
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('finance.manage');

        return $admin;
    }

    private function seedSimpleProduct(): \App\Models\PlatformProduct
    {
        $product = $this->forceCreatePlatformProduct([
            'title' => 'Test Hosting',
            'slug' => 'test-hosting-'.Str::lower(Str::random(4)),
            'product_type' => PlatformProductType::SocialService,
            'status' => PlatformProductStatus::Published,
            'base_price' => 2500,
            'sort_order' => 1,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
        ]);

        PlatformProductVariant::query()->create([
            'platform_product_id' => $product->id,
            'name' => 'Monthly',
            'label' => 'Monthly',
            'sku' => $product->slug.'-m',
            'duration_months' => 1,
            'price' => 2500,
            'sort_order' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);

        return $product->fresh('activeVariants');
    }

    public function test_manual_bank_checkout_creates_pending_order_without_crediting_buyer_wallet(): void
    {
        SystemSetting::set('manual_bank_transfer_enabled', true);
        SystemSetting::set('manual_bank_transfer_bank_name', 'Test Bank');
        SystemSetting::set('manual_bank_transfer_account_number', '0123456789');
        SystemSetting::set('manual_bank_transfer_account_name', '7th Trade Hub');

        $product = $this->seedSimpleProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 100,
            'locked_balance' => 0,
        ]);

        $balanceBefore = (float) $user->wallet->fresh()->balance;

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $product->activeVariants->first()->id,
                'quantity' => 1,
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
            ])
            ->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(Order::PAYMENT_MANUAL_BANK_TRANSFER, $order->payment_method);
        $this->assertSame('pending', $order->status);
        $this->assertSame($balanceBefore, (float) $user->wallet->fresh()->balance);
    }

    public function test_admin_confirm_credits_platform_wallet_not_buyer(): void
    {
        SystemSetting::set('manual_bank_transfer_enabled', true);
        SystemSetting::set('manual_bank_transfer_bank_name', 'Test Bank');
        SystemSetting::set('manual_bank_transfer_account_number', '0123456789');
        SystemSetting::set('manual_bank_transfer_account_name', '7th Trade Hub');

        $admin = $this->financeAdmin();
        $product = $this->seedSimpleProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $platformBefore = (float) app(WalletService::class)->getPlatformWallet()->balance;

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $product->activeVariants->first()->id,
                'quantity' => 1,
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
            ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $buyerBalanceBefore = (float) ($user->wallet?->balance ?? 0);

        $this->actingAs($admin)
            ->post(route('admin.orders.confirm', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame($buyerBalanceBefore, (float) ($user->wallet?->fresh()->balance ?? 0));
        $this->assertGreaterThan($platformBefore, (float) app(WalletService::class)->getPlatformWallet()->fresh()->balance);
    }

    public function test_manual_bank_rejected_when_toggle_off_and_not_admin(): void
    {
        SystemSetting::set('manual_bank_transfer_enabled', false);

        $product = $this->seedSimpleProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $product->activeVariants->first()->id,
                'quantity' => 1,
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_manual_bank_unavailable_when_bank_details_missing(): void
    {
        SystemSetting::set('manual_bank_transfer_enabled', true);
        SystemSetting::set('manual_bank_transfer_bank_name', '');
        SystemSetting::set('manual_bank_transfer_account_number', '');
        SystemSetting::set('manual_bank_transfer_account_name', '');

        $product = $this->seedSimpleProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $product->activeVariants->first()->id,
                'quantity' => 1,
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_admin_can_create_mark_paid_order_when_user_toggle_off(): void
    {
        SystemSetting::set('manual_bank_transfer_enabled', false);

        $admin = $this->financeAdmin();
        $product = $this->seedSimpleProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($admin)
            ->post(route('admin.orders.store'), [
                'user_id' => $user->id,
                'product_slug' => $product->slug,
                'variant_id' => $product->activeVariants->first()->id,
                'quantity' => 1,
                'mark_paid' => '1',
            ])
            ->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('paid', $order->status);
    }

    public function test_wallet_bank_deposit_route_is_gone(): void
    {
        $user = User::factory()->kycApproved()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        app(\App\Modules\Wallet\Services\WalletProvisioningService::class)->createWallet($user);

        $this->actingAs($user)
            ->post('/dashboard/deposit/bank', [
                'amount' => 5000,
                'bank_name' => 'GTBank',
                'transfer_reference' => 'TXN-12345',
            ])
            ->assertNotFound();
    }

    public function test_manual_payment_page_shows_bank_details_without_javascript(): void
    {
        SystemSetting::set('manual_bank_transfer_enabled', true);
        SystemSetting::set('manual_bank_transfer_bank_name', 'Test Bank');
        SystemSetting::set('manual_bank_transfer_account_number', '0123456789');
        SystemSetting::set('manual_bank_transfer_account_name', '7th Trade Hub');
        SystemSetting::set('manual_bank_transfer_instructions', 'Use your order reference as narration.');

        $product = $this->seedSimpleProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $product->activeVariants->first()->id,
                'quantity' => 1,
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
            ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->get(route('dashboard.orders.manual-payment', $order))
            ->assertOk()
            ->assertSee('Transfer to this account', false)
            ->assertSee('Test Bank', false)
            ->assertSee('0123456789', false)
            ->assertSee('7th Trade Hub', false)
            ->assertSee('Time remaining to complete payment', false)
            ->assertSee('I Have Made This Payment', false)
            ->assertSee('Use your order reference as narration.', false);
    }

    public function test_manual_payment_page_requires_proof_only(): void
    {
        SystemSetting::set('manual_bank_transfer_enabled', true);
        SystemSetting::set('manual_bank_transfer_bank_name', 'Test Bank');
        SystemSetting::set('manual_bank_transfer_account_number', '0123456789');
        SystemSetting::set('manual_bank_transfer_account_name', '7th Trade Hub');

        $product = $this->seedSimpleProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $product->activeVariants->first()->id,
                'quantity' => 1,
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
            ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('dashboard.orders.manual-payment.submit', $order), [], [
                'Accept' => 'application/json',
            ])
            ->assertStatus(422);

        $file = \Illuminate\Http\UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($user)
            ->post(route('dashboard.orders.manual-payment.submit', $order), [
                'proof' => $file,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $order->refresh();
        $this->assertNotNull($order->payment_submitted_at);
        $this->assertArrayHasKey('proof_path', $order->payment_metadata ?? []);
    }

    public function test_third_manual_payment_expiry_cancels_order(): void
    {
        SystemSetting::set('manual_bank_transfer_enabled', true);
        SystemSetting::set('manual_bank_transfer_bank_name', 'Test Bank');
        SystemSetting::set('manual_bank_transfer_account_number', '0123456789');
        SystemSetting::set('manual_bank_transfer_account_name', '7th Trade Hub');

        $product = $this->seedSimpleProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $product->activeVariants->first()->id,
                'quantity' => 1,
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
            ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $order->update([
            'payment_metadata' => [
                'manual_payment_session' => 3,
                'manual_payment_expires_at' => now()->subMinute()->toIso8601String(),
                'manual_payment_expired' => true,
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('dashboard.orders.manual-payment.expire', $order))
            ->assertOk()
            ->assertJson(['status' => 'cancelled']);

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_checkout_does_not_notify_admin_until_proof_submitted(): void
    {
        $this->financeAdmin();
        $this->mockSuccessfulMail();
        $this->seedManualBankSettings();

        $product = $this->seedSimpleProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $product->activeVariants->first()->id,
                'quantity' => 1,
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
            ]);

        $this->assertDatabaseMissing('admin_notifications', ['type' => 'order.manual_bank_transfer_proof']);
        $this->assertDatabaseMissing('admin_notifications', ['type' => 'order.manual_bank_transfer_failed']);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $file = \Illuminate\Http\UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($user)
            ->post(route('dashboard.orders.manual-payment.submit', $order), [
                'proof' => $file,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk();

        $this->assertDatabaseHas('admin_notifications', ['type' => 'order.manual_bank_transfer_proof']);
    }

    public function test_final_manual_payment_expiry_notifies_admin_of_failure(): void
    {
        $this->financeAdmin();
        $this->mockSuccessfulMail();
        $this->seedManualBankSettings();

        $product = $this->seedSimpleProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $product->activeVariants->first()->id,
                'quantity' => 1,
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
            ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $order->update([
            'payment_metadata' => [
                'manual_payment_session' => 3,
                'manual_payment_expires_at' => now()->subMinute()->toIso8601String(),
                'manual_payment_expired' => true,
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('dashboard.orders.manual-payment.expire', $order))
            ->assertOk()
            ->assertJson(['status' => 'cancelled']);

        $this->assertDatabaseHas('admin_notifications', ['type' => 'order.manual_bank_transfer_failed']);
    }

    private function seedManualBankSettings(): void
    {
        SystemSetting::set('manual_bank_transfer_enabled', true);
        SystemSetting::set('manual_bank_transfer_bank_name', 'Test Bank');
        SystemSetting::set('manual_bank_transfer_account_number', '0123456789');
        SystemSetting::set('manual_bank_transfer_account_name', '7th Trade Hub');
    }

    private function mockSuccessfulMail(): void
    {
        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendMailableHtml')->andReturn(SendResult::ok('test', 'msg-1'));
        $this->app->instance(EmailService::class, $mock);
    }
}
