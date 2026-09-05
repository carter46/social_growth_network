<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Enums\UserToolStatus;
use App\Models\Order;
use App\Models\PlatformProductVariant;
use App\Models\User;
use App\Models\UserTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminManualPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->admin()->create(['email_verified_at' => now()]);
    }

    private function memberUser(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        return $user;
    }

    private function seedVpnProduct(): \App\Models\PlatformProduct
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

    public function test_manual_purchase_catalog_returns_products_and_variants(): void
    {
        $product = $this->seedVpnProduct();

        $this->actingAs($this->adminUser())
            ->getJson(route('admin.users.manual-purchase.catalog'))
            ->assertOk()
            ->assertJsonFragment(['slug' => $product->slug])
            ->assertJsonStructure([
                'categories',
                'services',
                'products' => [
                    ['id', 'title', 'slug', 'variants' => [['id', 'label', 'price']]],
                ],
            ]);
    }

    public function test_admin_can_manual_purchase_for_user_from_users_route(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();
        $product = $this->seedVpnProduct();
        $variant = $product->activeVariants->first();

        $this->actingAs($admin)
            ->post(route('admin.users.manual-purchase', $member), [
                'product_slug' => $product->slug,
                'variant_id' => $variant->id,
                'mark_paid' => '1',
            ])
            ->assertRedirect(route('admin.users.tools', $member))
            ->assertSessionHas('status');

        $order = Order::query()->where('user_id', $member->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('paid', $order->status);
    }

    public function test_website_manual_purchase_requires_existing_domain(): void
    {
        \Illuminate\Support\Facades\Artisan::call('catalog:backfill-hierarchy');

        $service = \App\Models\ProductType::query()
            ->where('slug', 'like', '%website%')
            ->orWhere('name', 'like', '%Website Package%')
            ->first();

        if (! $service) {
            $category = $this->forceCreateServiceCategory([
                'name' => 'Website Services',
                'slug' => 'website-services-admin-test',
                'is_active' => true,
                'sort_order' => 1,
            ]);
            $service = $this->forceCreateProductType([
                'service_category_id' => $category->id,
                'name' => 'Website Package',
                'slug' => 'website-package-admin-test',
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }

        $product = $this->forceCreatePlatformProduct([
            'title' => 'Banking Site',
            'slug' => 'banking-site-'.Str::lower(Str::random(4)),
            'product_type' => PlatformProductType::SocialService,
            'product_type_id' => $service->id,
            'status' => PlatformProductStatus::Published,
            'base_price' => 10000,
            'sort_order' => 1,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
        ]);

        $variant = PlatformProductVariant::query()->create([
            'platform_product_id' => $product->id,
            'name' => '3 Months',
            'label' => '3 Months',
            'sku' => $product->slug.'-3m',
            'duration_months' => 3,
            'price' => 27000,
            'sort_order' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);

        $admin = $this->adminUser();
        $member = $this->memberUser();

        $this->actingAs($admin)
            ->post(route('admin.users.manual-purchase', $member), [
                'product_slug' => $product->slug,
                'variant_id' => $variant->id,
                'mark_paid' => '1',
                'domain_fqdn' => 'shop.customer-example.com',
            ])
            ->assertRedirect(route('admin.users.tools', $member))
            ->assertSessionHas('status');

        $tool = UserTool::query()->where('user_id', $member->id)->first();
        $this->assertNotNull($tool);
        $this->assertSame(UserToolStatus::PendingSetup, $tool->status);
        $this->assertSame('https://shop.customer-example.com', $tool->site_url);
        $this->assertSame('https://shop.customer-example.com', $tool->suggestedSiteUrl());

        $order = Order::query()->where('user_id', $member->id)->with('items')->first();
        $this->assertNotNull($order);
        $this->assertSame(
            'shop.customer-example.com',
            $order->items->first()?->options['domain_fqdn'] ?? null
        );
    }

    public function test_admin_manual_purchase_respects_custom_purchase_date(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();
        $product = $this->seedVpnProduct();
        $variant = $product->activeVariants->first();
        $purchaseDate = now()->subDays(10)->format('Y-m-d');

        $this->actingAs($admin)
            ->post(route('admin.users.manual-purchase', $member), [
                'product_slug' => $product->slug,
                'variant_id' => $variant->id,
                'mark_paid' => '1',
                'purchased_at' => $purchaseDate,
            ])
            ->assertRedirect(route('admin.users.tools', $member))
            ->assertSessionHas('status');

        $tool = UserTool::query()->where('user_id', $member->id)->first();
        $this->assertNotNull($tool);
        $this->assertSame($purchaseDate, $tool->purchased_at?->format('Y-m-d'));
    }

    public function test_admin_can_manually_approve_domain_connection(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();
        $product = $this->seedVpnProduct();

        $order = Order::query()->create([
            'source' => 'platform',
            'user_id' => $member->id,
            'reference' => 'PLT-TEST01',
            'amount' => 2500,
            'total_amount' => 2500,
            'status' => 'paid',
            'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
        ]);

        $orderItem = \App\Models\OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'platform_product',
            'item_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'line_total' => 2500,
            'options' => ['domain_mode' => 'connect', 'domain_fqdn' => 'shop.customer-example.com'],
        ]);

        $tool = UserTool::query()->create([
            'user_id' => $member->id,
            'platform_product_id' => $product->id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'status' => UserToolStatus::PendingSetup,
            'purchased_at' => now()->subDay(),
            'duration_months' => 3,
            'instance_sequence' => 1,
        ]);

        $connection = \App\Models\DomainConnection::query()->create([
            'user_id' => $member->id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'user_tool_id' => $tool->id,
            'fqdn' => 'shop.customer-example.com',
            'claim_key' => 'shop.customer-example.com',
            'required_nameservers' => ['ns1.example.com', 'ns2.example.com'],
            'verification_status' => \App\Models\DomainConnection::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.tools.show', [$member, $tool]))
            ->post(route('admin.users.domain-connections.approve', [$member, $connection]))
            ->assertRedirect(route('admin.users.tools.show', [$member, $tool]))
            ->assertSessionHas('status');

        $connection->refresh();
        $this->assertSame(\App\Models\DomainConnection::STATUS_VERIFIED, $connection->verification_status);
        $this->assertNotNull($connection->verified_at);
    }

    public function test_admin_can_adjust_tool_expiry(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();

        $tool = UserTool::query()->create([
            'user_id' => $member->id,
            'platform_product_id' => $this->seedVpnProduct()->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now()->subMonth(),
            'configured_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
            'duration_months' => 3,
            'instance_sequence' => 1,
        ]);

        $newExpiry = now()->addMonths(6)->format('Y-m-d');

        $this->actingAs($admin)
            ->post(route('admin.users.tools.expiry', [$member, $tool]), [
                'expires_at' => $newExpiry,
            ])
            ->assertRedirect(route('admin.users.tools.show', [$member, $tool]))
            ->assertSessionHas('status');

        $this->assertSame(
            $newExpiry,
            $tool->fresh()->expires_at?->format('Y-m-d')
        );
    }

    public function test_admin_can_save_livechat_logins_and_user_can_copy_password(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();
        $product = $this->seedVpnProduct();

        $tool = UserTool::query()->create([
            'user_id' => $member->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $product->activeVariants->first()->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now()->subDay(),
            'configured_at' => now()->subDay(),
            'expires_at' => now()->addMonths(3),
            'duration_months' => 3,
            'site_url' => 'https://customer.example.com',
            'admin_email' => 'owner@example.com',
            'admin_password' => 'SitePass123!',
            'instance_sequence' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.tools.livechat', [$member, $tool]), [
                'livechat_name' => 'Jivo Support',
                'livechat_url' => 'https://app.jivochat.com/login',
                'livechat_email' => 'chat@example.com',
                'livechat_password' => 'ChatPass123!',
            ])
            ->assertRedirect(route('admin.users.tools.show', [$member, $tool]))
            ->assertSessionHas('status');

        $tool->refresh();
        $this->assertSame('Jivo Support', $tool->livechat_name);
        $this->assertSame('https://app.jivochat.com/login', $tool->livechat_url);
        $this->assertSame('chat@example.com', $tool->livechat_email);
        $this->assertSame('ChatPass123!', $tool->livechat_password);

        $this->actingAs($member)
            ->get(route('dashboard.my-tools.show', $tool))
            ->assertOk()
            ->assertSee('Livechat logins')
            ->assertSee('Jivo Support')
            ->assertSee('chat@example.com');

        $this->actingAs($member)
            ->postJson(route('dashboard.my-tools.livechat-password', $tool))
            ->assertOk()
            ->assertJson(['password' => 'ChatPass123!']);
    }

    public function test_product_tutorial_shows_on_product_page_and_my_tools(): void
    {
        $member = $this->memberUser();
        $product = $this->seedVpnProduct();
        $product->forceFill([
            'tutorial_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'tutorial_description' => 'How to set up your banking site admin panel.',
        ])->save();

        $this->actingAs($member)
            ->get(route('dashboard.services.product', $product->slug))
            ->assertOk()
            ->assertSee('Watch tutorial')
            ->assertSee('How to set up your banking site admin panel.');

        $tool = UserTool::query()->create([
            'user_id' => $member->id,
            'platform_product_id' => $product->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now()->subDay(),
            'configured_at' => now()->subDay(),
            'expires_at' => now()->addMonths(3),
            'site_url' => 'https://customer.example.com',
            'instance_sequence' => 1,
        ]);

        $this->actingAs($member)
            ->get(route('dashboard.my-tools.show', $tool))
            ->assertOk()
            ->assertSee('Tutorials')
            ->assertSee('How to set up your banking site admin panel.')
            ->assertSee('Watch tutorial');
    }

    public function test_admin_can_shutdown_site_and_enable_restores_previous_expiry(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();
        $product = $this->seedVpnProduct();

        $originalExpiry = now()->addMonths(2)->endOfDay();

        $tool = UserTool::query()->create([
            'user_id' => $member->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $product->activeVariants->first()->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now()->subMonth(),
            'configured_at' => now()->subMonth(),
            'expires_at' => $originalExpiry,
            'duration_months' => 3,
            'site_url' => 'https://customer.example.com',
            'admin_login_url' => 'https://customer.example.com/admin',
            'admin_email' => 'owner@example.com',
            'admin_password' => 'SitePass123!',
            'instance_sequence' => 1,
        ]);

        $integration = \App\Models\UserToolIntegration::query()->create([
            'user_tool_id' => $tool->id,
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_test',
            'client_secret' => 'client-secret-test',
            'webhook_secret' => 'webhook-secret-test',
            'capabilities' => \App\Models\UserToolIntegration::defaultCapabilities(),
            'connection_status' => 'ok',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://customer.example.com/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.tools.show', [$member, $tool]))
            ->assertOk()
            ->assertSee('Shutdown Site');

        $this->actingAs($admin)
            ->post(route('admin.users.tools.shutdown', [$member, $tool]))
            ->assertRedirect(route('admin.users.tools.show', [$member, $tool]))
            ->assertSessionHas('status');

        $tool->refresh();
        $this->assertSame(UserToolStatus::Expired, $tool->status);
        $this->assertSame(UserTool::END_REASON_ADMIN_SHUTDOWN, $tool->subscription_end_reason);
        $this->assertTrue($tool->expires_at->lessThanOrEqualTo(now()->addSecond()));
        $this->assertSame(
            $originalExpiry->format('Y-m-d'),
            $tool->shutdown_resume_expires_at?->format('Y-m-d')
        );
        $this->assertSame('client-secret-test', $integration->fresh()->client_secret);
        $this->assertSame('webhook-secret-test', $integration->fresh()->webhook_secret);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $member->id,
            'type' => 'tool.subscription_expired',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.tools.show', [$member, $tool->fresh()]))
            ->assertOk()
            ->assertSee('>Enable</', false)
            ->assertDontSee('Shutdown Site')
            ->assertDontSee('name="enable_expires_at"', false)
            ->assertSee($originalExpiry->format('j M Y'), false);

        // No date required — resumes stored expiry.
        $this->actingAs($admin)
            ->post(route('admin.users.tools.enable', [$member, $tool]))
            ->assertRedirect(route('admin.users.tools.show', [$member, $tool]))
            ->assertSessionHas('status');

        $tool->refresh();
        $this->assertSame(UserToolStatus::Active, $tool->status);
        $this->assertNull($tool->subscription_end_reason);
        $this->assertNull($tool->shutdown_resume_expires_at);
        $this->assertSame($originalExpiry->format('Y-m-d'), $tool->expires_at?->format('Y-m-d'));
        $this->assertTrue($tool->isSubscriptionLive());
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $member->id,
            'type' => 'tool.subscription_extended',
        ]);
    }

    public function test_enable_after_natural_expiry_requires_new_date(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();
        $product = $this->seedVpnProduct();

        $tool = UserTool::query()->create([
            'user_id' => $member->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $product->activeVariants->first()->id,
            'status' => UserToolStatus::Expired,
            'purchased_at' => now()->subMonths(4),
            'configured_at' => now()->subMonths(4),
            'expires_at' => now()->subDay(),
            'subscription_end_reason' => UserTool::END_REASON_NATURAL,
            'duration_months' => 3,
            'instance_sequence' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.tools.show', [$member, $tool]))
            ->assertOk()
            ->assertSee('name="enable_expires_at"', false);

        $this->actingAs($admin)
            ->post(route('admin.users.tools.enable', [$member, $tool]))
            ->assertSessionHasErrors('enable_expires_at');

        $newExpiry = now()->addMonths(2)->format('Y-m-d');
        $this->actingAs($admin)
            ->post(route('admin.users.tools.enable', [$member, $tool]), [
                'enable_expires_at' => $newExpiry,
            ])
            ->assertRedirect(route('admin.users.tools.show', [$member, $tool]));

        $tool->refresh();
        $this->assertSame(UserToolStatus::Active, $tool->status);
        $this->assertSame($newExpiry, $tool->expires_at?->format('Y-m-d'));
    }

    public function test_natural_expiry_notifies_user_and_extend_notifies_again(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();
        $product = $this->seedVpnProduct();

        $tool = UserTool::query()->create([
            'user_id' => $member->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $product->activeVariants->first()->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now()->subMonths(4),
            'configured_at' => now()->subMonths(4),
            'expires_at' => now()->subHour(),
            'duration_months' => 3,
            'instance_sequence' => 1,
        ]);

        $this->artisan('site-integrations:expire-user-tools')->assertSuccessful();

        $tool->refresh();
        $this->assertSame(UserToolStatus::Expired, $tool->status);
        $this->assertSame(UserTool::END_REASON_NATURAL, $tool->subscription_end_reason);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $member->id,
            'type' => 'tool.subscription_expired',
        ]);

        $newExpiry = now()->addMonths(2)->format('Y-m-d');
        $this->actingAs($admin)
            ->post(route('admin.users.tools.expiry', [$member, $tool]), [
                'expires_at' => $newExpiry,
            ])
            ->assertRedirect(route('admin.users.tools.show', [$member, $tool]));

        $tool->refresh();
        $this->assertSame(UserToolStatus::Active, $tool->status);
        $this->assertNull($tool->subscription_end_reason);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $member->id,
            'type' => 'tool.subscription_extended',
        ]);
    }

    public function test_admin_extend_after_shutdown_does_not_notify_user(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();
        $product = $this->seedVpnProduct();

        $tool = UserTool::query()->create([
            'user_id' => $member->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $product->activeVariants->first()->id,
            'status' => UserToolStatus::Expired,
            'purchased_at' => now()->subMonth(),
            'configured_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
            'subscription_end_reason' => UserTool::END_REASON_ADMIN_SHUTDOWN,
            'duration_months' => 3,
            'instance_sequence' => 1,
        ]);

        $newExpiry = now()->addMonths(2)->format('Y-m-d');
        $this->actingAs($admin)
            ->post(route('admin.users.tools.expiry', [$member, $tool]), [
                'expires_at' => $newExpiry,
            ])
            ->assertRedirect(route('admin.users.tools.show', [$member, $tool]));

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $member->id,
            'type' => 'tool.subscription_extended',
        ]);
    }

    public function test_admin_shutdown_keeps_hub_expired_when_merchant_unreachable(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();
        $product = $this->seedVpnProduct();

        $tool = UserTool::query()->create([
            'user_id' => $member->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $product->activeVariants->first()->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now()->subMonth(),
            'configured_at' => now()->subMonth(),
            'expires_at' => now()->addMonths(2),
            'duration_months' => 3,
            'site_url' => 'https://customer.example.com',
            'admin_login_url' => 'https://customer.example.com/admin',
            'admin_email' => 'owner@example.com',
            'admin_password' => 'SitePass123!',
            'instance_sequence' => 1,
        ]);

        \App\Models\UserToolIntegration::query()->create([
            'user_tool_id' => $tool->id,
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_test',
            'client_secret' => 'client-secret-test',
            'webhook_secret' => 'webhook-secret-test',
            'capabilities' => \App\Models\UserToolIntegration::defaultCapabilities(),
            'connection_status' => 'ok',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://customer.example.com/*' => \Illuminate\Support\Facades\Http::response('down', 503),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.tools.shutdown', [$member, $tool]))
            ->assertRedirect(route('admin.users.tools.show', [$member, $tool]))
            ->assertSessionHas('status')
            ->assertSessionHas('warning');

        $tool->refresh();
        $this->assertSame(UserToolStatus::Expired, $tool->status);
        $this->assertFalse($tool->isSubscriptionLive());
    }

    public function test_admin_shutdown_without_integration_does_not_claim_merchant_notified(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();
        $product = $this->seedVpnProduct();

        $tool = UserTool::query()->create([
            'user_id' => $member->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $product->activeVariants->first()->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now()->subMonth(),
            'configured_at' => now()->subMonth(),
            'expires_at' => now()->addMonths(2),
            'duration_months' => 3,
            'site_url' => null,
            'instance_sequence' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.tools.shutdown', [$member, $tool]))
            ->assertRedirect(route('admin.users.tools.show', [$member, $tool]))
            ->assertSessionHas('status', 'Site shut down on Hub. No merchant sync was sent (missing site URL or integration credentials).');

        $this->assertSame(UserToolStatus::Expired, $tool->fresh()->status);
    }
}
