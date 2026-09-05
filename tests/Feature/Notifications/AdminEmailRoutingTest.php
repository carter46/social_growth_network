<?php

namespace Tests\Feature\Notifications;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Events\OrderCompleted;
use App\Events\WalletFunded;
use App\Events\WalletFundingSubmitted;
use App\Events\UserVerified;
use App\Models\AdminNotification;
use App\Models\EmailIdentity;
use App\Models\NotificationDeliveryLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformProduct;
use App\Models\User;
use App\Models\WalletFunding;
use App\Modules\Wallet\Services\WalletProvisioningService;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Communications\Email\EmailProfile;
use App\Services\Communications\Email\EmailService;
use App\Services\Communications\Email\SendResult;
use App\Services\Notifications\Channels\MailChannel;
use App\Services\Notifications\EmailIdentityResolver;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use App\Services\Notifications\OrderNotificationTypeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AdminEmailRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function financeAdmin(): User
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('finance.manage');

        return $admin;
    }

    public function test_deposit_submitted_and_credited_map_to_billing_profile(): void
    {
        $resolver = app(EmailIdentityResolver::class);

        $this->assertSame(EmailProfile::Billing, $resolver->resolveProfileForType('wallet.deposit_submitted'));
        $this->assertSame(EmailProfile::Billing, $resolver->resolveProfileForType('wallet.deposit_credited'));
        $this->assertNotSame(
            $resolver->resolveProfileForType('wallet.deposit_submitted'),
            $resolver->resolveProfileForType('order.completed')
        );
    }

    public function test_order_completed_derives_website_and_domain_types(): void
    {
        $resolver = app(OrderNotificationTypeResolver::class);

        $websiteProduct = $this->forceCreatePlatformProduct([
            'title' => 'Online Banking',
            'slug' => 'online-banking-'.Str::lower(Str::random(4)),
            'product_type' => PlatformProductType::SocialService,
            'product_type_id' => 1,
            'status' => PlatformProductStatus::Published,
            'base_price' => 10000,
            'sort_order' => 1,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
        ]);

        $websiteOrder = Order::factory()->platform()->create(['status' => 'paid']);
        OrderItem::query()->create([
            'order_id' => $websiteOrder->id,
            'item_type' => 'platform_product',
            'item_id' => $websiteProduct->id,
            'quantity' => 1,
            'unit_price' => 27000,
            'line_total' => 27000,
            'options' => ['product_title' => 'Online Banking'],
        ]);

        $this->assertSame('order.website_purchased', $resolver->resolve($websiteOrder->fresh('items')));

        $domainOrder = Order::factory()->platform()->create(['status' => 'paid']);
        OrderItem::query()->create([
            'order_id' => $domainOrder->id,
            'item_type' => 'platform_product',
            'item_id' => $websiteProduct->id,
            'quantity' => 1,
            'unit_price' => 5000,
            'line_total' => 5000,
            'options' => ['domain_mode' => 'buy', 'domain_fqdn' => 'example.test'],
        ]);

        $this->assertSame('order.domain_purchased', $resolver->resolve($domainOrder->fresh('items')));
    }

    public function test_notify_admins_creates_distinct_types_for_deposit_lifecycle(): void
    {
        $this->financeAdmin();
        $this->mockSuccessfulMail();

        $user = User::factory()->create();
        $user->assignRole('user');
        app(WalletProvisioningService::class)->createWallet($user);
        $user->refresh();

        $funding = WalletFunding::create([
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'method' => 'bank',
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => 'pending',
            'reference' => 'DEP-ROUTE-001',
        ]);

        WalletFundingSubmitted::dispatch($funding->id, $user->id, 5000, 'NGN', 'bank');

        $this->assertDatabaseHas('admin_notifications', ['type' => 'wallet.deposit_submitted']);

        WalletFunded::dispatch($user->id, 99, 5000, 'NGN');

        $this->assertDatabaseHas('admin_notifications', ['type' => 'wallet.deposit_credited']);
    }

    public function test_mail_channel_dedupes_repeated_admin_alerts(): void
    {
        $this->financeAdmin();
        $this->mockSuccessfulMail();

        $message = new NotificationMessage(
            type: 'wallet.deposit_submitted',
            title: 'Deposit submitted',
            body: 'Test body',
            dedupeKey: 'wallet.deposit_submitted.42',
        );

        app(NotificationDispatcher::class)->notifyAdmins($message);
        app(NotificationDispatcher::class)->notifyAdmins($message);

        $this->assertSame(1, AdminNotification::query()->where('type', 'wallet.deposit_submitted')->count());
        $this->assertSame(
            1,
            NotificationDeliveryLog::query()
                ->where('notification_type', 'wallet.deposit_submitted')
                ->where('channel', 'mail')
                ->where('status', 'sent')
                ->count()
        );
        $this->assertGreaterThanOrEqual(
            1,
            NotificationDeliveryLog::query()
                ->where('notification_type', 'wallet.deposit_submitted')
                ->where('status', 'deduped')
                ->count()
        );
    }

    public function test_wallet_credit_succeeds_when_admin_mail_fails(): void
    {
        $this->financeAdmin();

        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendMailableHtml')->andReturn(SendResult::fail('brevo', 'Provider down'));
        $this->app->instance(EmailService::class, $mock);

        $user = User::factory()->kycApproved()->create();
        $user->assignRole('user');
        app(WalletProvisioningService::class)->createWallet($user);
        $user->refresh();

        $funding = WalletFunding::create([
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'method' => 'bank',
            'amount' => 2500,
            'currency' => 'NGN',
            'status' => 'pending',
            'reference' => 'DEP-FAIL-MAIL',
        ]);

        app(WalletService::class)->creditFromFunding($funding);

        $user->wallet->refresh();
        $this->assertEquals(2500.0, (float) $user->wallet->balance);
        $this->assertDatabaseHas('admin_notifications', ['type' => 'wallet.deposit_credited']);
    }

    public function test_order_completed_notification_uses_sales_profile_in_delivery_log(): void
    {
        $this->financeAdmin();
        $this->mockSuccessfulMail();

        $product = $this->forceCreatePlatformProduct([
            'title' => 'Online Banking',
            'slug' => 'online-banking-'.Str::lower(Str::random(4)),
            'product_type' => PlatformProductType::SocialService,
            'product_type_id' => 1,
            'status' => PlatformProductStatus::Published,
            'base_price' => 10000,
            'sort_order' => 1,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
        ]);

        $order = Order::factory()->platform()->create(['status' => 'paid', 'total_amount' => 15000]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'platform_product',
            'item_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 15000,
            'line_total' => 15000,
            'options' => ['product_title' => 'Online Banking'],
        ]);

        OrderCompleted::dispatch($order->id);

        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'order.website_purchased',
        ]);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_type' => 'order.website_purchased',
            'profile' => EmailProfile::Sales->value,
            'channel' => 'mail',
            'status' => 'sent',
        ]);
    }

    public function test_wallet_funding_submitted_not_notified_when_transaction_rolls_back(): void
    {
        $this->financeAdmin();
        $this->mockSuccessfulMail();

        $user = User::factory()->create();
        $user->assignRole('user');
        app(WalletProvisioningService::class)->createWallet($user);
        $user->refresh();

        $funding = WalletFunding::create([
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'method' => 'bank',
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => 'pending',
            'reference' => 'DEP-ROLLBACK',
        ]);

        try {
            DB::transaction(function () use ($funding, $user) {
                DB::afterCommit(function () use ($funding, $user) {
                    WalletFundingSubmitted::dispatch($funding->id, $user->id, 5000, 'NGN', 'bank');
                });

                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertDatabaseMissing('admin_notifications', ['type' => 'wallet.deposit_submitted']);

        DB::transaction(function () use ($funding, $user) {
            DB::afterCommit(function () use ($funding, $user) {
                WalletFundingSubmitted::dispatch($funding->id, $user->id, 5000, 'NGN', 'bank');
            });
        });

        $this->assertDatabaseHas('admin_notifications', ['type' => 'wallet.deposit_submitted']);
    }

    public function test_notify_inbox_receives_mail_when_no_staff_recipients(): void
    {
        EmailIdentity::query()->where('profile', EmailProfile::Billing->value)->update([
            'notify_to_email' => 'billing-inbox@example.com',
        ]);

        $this->mockSuccessfulMail();

        app(MailChannel::class)->send(
            new NotificationMessage(
                type: 'payment.gateway_unmatched',
                title: 'Unmatched payment',
                body: 'Reference ABC',
                dedupeKey: 'payment.gateway_unmatched.ABC',
            ),
            'admin',
            collect(),
        );

        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_type' => 'payment.gateway_unmatched',
            'recipient' => 'billing-inbox@example.com',
            'channel' => 'mail',
            'status' => 'sent',
        ]);
    }

    public function test_user_verified_notifies_admins(): void
    {
        $admin = $this->financeAdmin();
        $admin->givePermissionTo('users.manage');
        $this->mockSuccessfulMail();

        $user = User::factory()->create(['email_verified_at' => now()]);
        UserVerified::dispatch($user->id);

        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'user.verified',
        ]);
    }

    public function test_atomic_dedupe_claim_prevents_duplicate_mail(): void
    {
        $this->financeAdmin();
        $this->mockSuccessfulMail();

        $message = new NotificationMessage(
            type: 'wallet.deposit_submitted',
            title: 'Deposit submitted',
            body: 'Test body',
            dedupeKey: 'wallet.deposit_submitted.atomic',
        );

        $dedupe = app(\App\Services\Notifications\NotificationDedupeService::class);
        $this->assertTrue($dedupe->tryClaim('wallet.deposit_submitted', 'wallet.deposit_submitted.atomic', 'mail'));
        $this->assertFalse($dedupe->tryClaim('wallet.deposit_submitted', 'wallet.deposit_submitted.atomic', 'mail'));
    }

    private function mockSuccessfulMail(): void
    {
        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendMailableHtml')->andReturn(SendResult::ok('test', 'msg-1'));
        $this->app->instance(EmailService::class, $mock);
    }
}
