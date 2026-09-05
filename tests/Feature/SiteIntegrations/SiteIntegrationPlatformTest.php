<?php

namespace Tests\Feature\SiteIntegrations;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Enums\SiteIntegrationStatus;
use App\Enums\UserToolStatus;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\User;
use App\Models\UserTool;
use App\Models\UserToolIntegration;
use App\Models\Wallet;
use App\Services\SiteIntegrations\DemoLaunchService;
use App\Services\SiteIntegrations\ProtocolV1Signer;
use App\Services\SiteIntegrations\SiteIntegrationAdminService;
use App\Services\SiteIntegrations\UserToolProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteIntegrationPlatformTest extends TestCase
{
    use RefreshDatabase;

    private function seedWebsiteProduct(): PlatformProduct
    {
        \Illuminate\Support\Facades\Artisan::call('catalog:backfill-hierarchy');

        $service = \App\Models\ProductType::query()
            ->where('slug', 'like', '%website%')
            ->orWhere('name', 'like', '%Website Package%')
            ->first();

        if (! $service) {
            $category = $this->forceCreateServiceCategory([
                'name' => 'Website Services',
                'slug' => 'website-services-test',
                'is_active' => true,
                'sort_order' => 1,
            ]);
            $service = $this->forceCreateProductType([
                'service_category_id' => $category->id,
                'name' => 'Website Package',
                'slug' => 'website-package-test',
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }

        $product = $this->forceCreatePlatformProduct([
            'title' => 'Online Banking website',
            'slug' => 'online-banking-website-'.Str::lower(Str::random(4)),
            'product_type' => PlatformProductType::SocialService,
            'product_type_id' => $service->id,
            'status' => PlatformProductStatus::Published,
            'base_price' => 10000,
            'sort_order' => 1,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
        ]);

        PlatformProductVariant::query()->create([
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

        return $product->fresh('activeVariants');
    }

    public function test_demo_and_owned_credentials_are_isolated(): void
    {
        $product = $this->seedWebsiteProduct();
        $adminService = app(SiteIntegrationAdminService::class);

        $demo = $adminService->create([
            'platform_product_id' => $product->id,
            'base_url' => 'https://demo.example.com',
            'demo_user_email' => 'demo-user@example.com',
            'demo_admin_email' => 'demo-admin@example.com',
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $tool = UserTool::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $product->activeVariants->first()->id,
            'status' => UserToolStatus::PendingSetup,
            'purchased_at' => now(),
            'duration_months' => 3,
            'instance_sequence' => 1,
            'display_name' => $product->title,
        ]);

        Http::fake([
            'https://customer.example.com/*' => Http::response(['ok' => true, 'capabilities' => []], 200),
        ]);

        $owned = app(UserToolProvisioningService::class)->setup($tool, [
            'site_url' => 'https://customer.example.com',
            'admin_login_url' => 'https://customer.example.com/admin',
            'admin_email' => 'owner-admin@example.com',
            'admin_password' => 'SecretPass123!',
        ]);

        $this->assertNotSame(
            $demo['credentials']['client_secret'],
            $owned['credentials']['client_secret']
        );
        $this->assertNotSame(
            $demo['credentials']['integration_id'],
            $owned['credentials']['integration_id']
        );
        $this->assertDatabaseHas('site_integrations', [
            'id' => $demo['integration']->id,
            'platform_product_id' => $product->id,
        ]);
        $this->assertDatabaseHas('user_tool_integrations', [
            'user_tool_id' => $tool->id,
        ]);
    }

    public function test_launch_binds_identity_and_rejects_client_supplied_email(): void
    {
        $product = $this->seedWebsiteProduct();
        $result = app(SiteIntegrationAdminService::class)->create([
            'platform_product_id' => $product->id,
            'base_url' => 'https://demo.example.com',
            'demo_user_email' => 'demo-user@example.com',
            'demo_admin_email' => 'demo-admin@example.com',
        ]);
        $integration = $result['integration'];
        $integration->status = SiteIntegrationStatus::Active;
        $integration->save();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $launch = app(DemoLaunchService::class)->launchDemo($user, $integration, 'admin');

        $this->assertSame('demo-admin@example.com', $launch['assertion']['identity']['email']);
        $this->assertStringContainsString('token=', $launch['redirect_url']);
        $this->assertStringNotContainsString('email=', $launch['redirect_url']);

        $signer = app(ProtocolV1Signer::class);
        $this->assertTrue($signer->verify($launch['assertion'], $result['credentials']['client_secret']));
    }

    public function test_renew_extends_same_user_tool_row(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $tool = UserTool::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now()->subMonths(2),
            'configured_at' => now()->subMonths(2),
            'expires_at' => now()->addDays(3),
            'duration_months' => 3,
            'site_url' => 'https://customer.example.com',
            'admin_email' => 'a@example.com',
            'instance_sequence' => 1,
        ]);

        UserToolIntegration::query()->create([
            'user_tool_id' => $tool->id,
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_test',
            'client_secret' => 'secret',
            'webhook_secret' => 'whsec',
            'capabilities' => UserToolIntegration::defaultCapabilities(),
        ]);

        Http::fake([
            'https://customer.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $beforeId = $tool->id;
        $beforeCount = UserTool::query()->where('user_id', $user->id)->count();

        app(UserToolProvisioningService::class)->renew($tool->fresh('integration'), 3);

        $this->assertSame($beforeCount, UserTool::query()->where('user_id', $user->id)->count());
        $this->assertSame($beforeId, $tool->fresh()->id);
        $this->assertTrue($tool->fresh()->expires_at->greaterThan(now()->addMonths(2)));
    }

    public function test_my_tools_show_does_not_include_password_in_html(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $tool = UserTool::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now(),
            'configured_at' => now(),
            'expires_at' => now()->addMonths(3),
            'duration_months' => 3,
            'site_url' => 'https://customer.example.com',
            'admin_login_url' => 'https://customer.example.com/admin',
            'admin_email' => 'a@example.com',
            'admin_password' => 'NeverInHtml-Password!',
            'instance_sequence' => 1,
            'display_name' => 'Online Banking',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.my-tools.show', $tool))
            ->assertOk()
            ->assertSee('Copy password')
            ->assertDontSee('NeverInHtml-Password!', false);

        $this->actingAs($user)
            ->postJson(route('dashboard.my-tools.password', $tool))
            ->assertOk()
            ->assertJson(['password' => 'NeverInHtml-Password!']);
    }

    public function test_expiring_soon_is_derived_not_stored(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $tool = UserTool::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now(),
            'expires_at' => now()->addDays(5),
            'duration_months' => 3,
            'instance_sequence' => 1,
            'display_name' => 'Banking',
        ]);

        $this->assertTrue($tool->isExpiringSoon());
        $this->assertSame(UserToolStatus::Active, $tool->status);

        $this->actingAs($user)
            ->get(route('dashboard.my-tools', ['expiring_soon' => 1]))
            ->assertOk()
            ->assertSee('Banking');
    }

    public function test_checkout_creates_pending_setup_user_tool(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 100000,
            'locked_balance' => 0,
        ]);

        $variant = $product->activeVariants->first();

        config(['domains.default_nameservers' => ['ns1.platform.test', 'ns2.platform.test']]);
        $this->app->instance(
            \App\Services\Domains\DomainDnsLookupService::class,
            new \App\Services\Domains\DomainDnsLookupService(fn () => [
                ['target' => 'ns1.oldhost.test'],
                ['target' => 'ns2.oldhost.test'],
            ]),
        );
        $this->app->forgetInstance(\App\Services\Domains\DomainConnectionService::class);
        $this->app->forgetInstance(\App\Services\Domains\DomainCheckoutValidator::class);

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $variant->id,
                'quantity' => 1,
                'domain_mode' => 'connect',
                'domain_fqdn' => 'mysite.com',
                'domain_connect_acknowledged' => '1',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_tools', [
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'status' => UserToolStatus::PendingSetup->value,
        ]);
    }

    public function test_checkout_creates_user_tool_for_non_website_internal_service(): void
    {
        \Illuminate\Support\Facades\Artisan::call('catalog:backfill-hierarchy');

        $service = \App\Models\ProductType::query()
            ->where('slug', 'vpn')
            ->first();

        if (! $service) {
            $category = $this->forceCreateServiceCategory([
                'name' => 'Network Services',
                'slug' => 'network-services-test',
                'is_active' => true,
                'sort_order' => 2,
            ]);
            $service = $this->forceCreateProductType([
                'service_category_id' => $category->id,
                'name' => 'VPN',
                'slug' => 'vpn',
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }

        $product = $this->forceCreatePlatformProduct([
            'title' => 'Business VPN',
            'slug' => 'business-vpn-'.Str::lower(Str::random(4)),
            'product_type' => PlatformProductType::SocialService,
            'product_type_id' => $service->id,
            'status' => PlatformProductStatus::Published,
            'base_price' => 5000,
            'sort_order' => 1,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
        ]);

        PlatformProductVariant::query()->create([
            'platform_product_id' => $product->id,
            'name' => '1 Month',
            'label' => '1 Month',
            'sku' => $product->slug.'-1m',
            'duration_months' => 1,
            'price' => 5000,
            'sort_order' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 100000,
            'locked_balance' => 0,
        ]);

        $variant = $product->fresh('activeVariants')->activeVariants->first();

        config(['domains.default_nameservers' => ['ns1.platform.test', 'ns2.platform.test']]);
        $this->app->instance(
            \App\Services\Domains\DomainDnsLookupService::class,
            new \App\Services\Domains\DomainDnsLookupService(fn () => [
                ['target' => 'ns1.oldhost.test'],
                ['target' => 'ns2.oldhost.test'],
            ]),
        );
        $this->app->forgetInstance(\App\Services\Domains\DomainConnectionService::class);
        $this->app->forgetInstance(\App\Services\Domains\DomainCheckoutValidator::class);

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $variant->id,
                'quantity' => 1,
                'domain_mode' => 'connect',
                'domain_fqdn' => 'mysite.com',
                'domain_connect_acknowledged' => '1',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_tools', [
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'status' => UserToolStatus::PendingSetup->value,
        ]);
    }

    public function test_expire_command_marks_tools_expired(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create();
        $tool = UserTool::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'status' => UserToolStatus::Active,
            'expires_at' => now()->subDay(),
            'site_url' => 'https://customer.example.com',
            'duration_months' => 3,
            'instance_sequence' => 1,
        ]);
        UserToolIntegration::query()->create([
            'user_tool_id' => $tool->id,
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_x',
            'client_secret' => 'sec',
            'webhook_secret' => 'wh',
            'capabilities' => UserToolIntegration::defaultCapabilities(),
        ]);

        Http::fake([
            'https://customer.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('site-integrations:expire-user-tools')->assertSuccessful();

        $this->assertSame(UserToolStatus::Expired, $tool->fresh()->status);
    }

    public function test_setup_defers_health_check_and_leaves_connection_unchecked(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $tool = UserTool::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $product->activeVariants->first()->id,
            'status' => UserToolStatus::PendingSetup,
            'purchased_at' => now(),
            'duration_months' => 3,
            'instance_sequence' => 1,
            'display_name' => $product->title,
        ]);

        Http::fake();

        $result = app(UserToolProvisioningService::class)->setup($tool, [
            'site_url' => 'https://customer.example.com',
            'admin_login_url' => 'https://customer.example.com/admin',
            'admin_email' => 'owner-admin@example.com',
            'admin_password' => 'SecretPass123!',
        ]);

        Http::assertNothingSent();
        $this->assertSame('unchecked', $result['tool']->integration->connection_status);
        $this->assertStringContainsString('Install on the merchant site', $result['credentials']['connection_message']);
    }

    public function test_unknown_integration_maps_to_pending_merchant_status(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $tool = UserTool::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $product->activeVariants->first()->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now()->subDay(),
            'configured_at' => now()->subDay(),
            'expires_at' => now()->addMonths(3),
            'duration_months' => 3,
            'site_url' => 'https://customer.example.com',
            'admin_login_url' => 'https://customer.example.com/admin',
            'admin_email' => 'owner-admin@example.com',
            'admin_password' => 'SecretPass123!',
            'instance_sequence' => 1,
        ]);

        UserToolIntegration::query()->create([
            'user_tool_id' => $tool->id,
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_owned',
            'client_secret' => 'owned-secret',
            'webhook_secret' => 'wh-owned',
            'capabilities' => UserToolIntegration::defaultCapabilities(),
        ]);

        Http::fake([
            'https://customer.example.com/*' => Http::response(['ok' => false, 'error' => 'unknown_integration'], 404),
        ]);

        app(\App\Services\SiteIntegrations\ConnectionCheckService::class)
            ->checkOwned($tool->fresh('integration'));

        $this->assertSame('pending_merchant', $tool->fresh('integration')->integration->connection_status);
    }

    public function test_can_launch_admin_when_pending_merchant_but_not_on_hard_error(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $tool = UserTool::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now()->subDay(),
            'configured_at' => now()->subDay(),
            'expires_at' => now()->addMonths(3),
            'site_url' => 'https://customer.example.com',
            'admin_email' => 'owner-admin@example.com',
            'instance_sequence' => 1,
        ]);

        UserToolIntegration::query()->create([
            'user_tool_id' => $tool->id,
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_owned',
            'client_secret' => 'owned-secret',
            'webhook_secret' => 'wh-owned',
            'capabilities' => UserToolIntegration::defaultCapabilities(),
            'connection_status' => 'pending_merchant',
        ]);

        $this->assertTrue($tool->fresh('integration')->canLaunchAdmin());

        $tool->integration->update(['connection_status' => 'error']);
        $this->assertFalse($tool->fresh('integration')->canLaunchAdmin());
    }

    public function test_admin_can_view_owned_tool_manage_page(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('user');

        $product = $this->seedWebsiteProduct();
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
            'admin_login_url' => 'https://customer.example.com/admin',
            'admin_email' => 'owner-admin@example.com',
            'admin_password' => 'SecretPass123!',
            'instance_sequence' => 1,
        ]);

        UserToolIntegration::query()->create([
            'user_tool_id' => $tool->id,
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_owned',
            'client_secret' => 'owned-secret',
            'webhook_secret' => 'wh-owned',
            'capabilities' => UserToolIntegration::defaultCapabilities(),
            'connection_status' => 'unchecked',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.tools.show', [$member, $tool]))
            ->assertOk()
            ->assertSee('Owned tool credentials')
            ->assertSee('Check connection')
            ->assertSee($tool->public_id);
    }

    public function test_check_tool_redirects_to_manage_page_with_pending_merchant_warning(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('user');

        $product = $this->seedWebsiteProduct();
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
            'admin_login_url' => 'https://customer.example.com/admin',
            'admin_email' => 'owner-admin@example.com',
            'admin_password' => 'SecretPass123!',
            'instance_sequence' => 1,
        ]);

        UserToolIntegration::query()->create([
            'user_tool_id' => $tool->id,
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_owned',
            'client_secret' => 'owned-secret',
            'webhook_secret' => 'wh-owned',
            'capabilities' => UserToolIntegration::defaultCapabilities(),
        ]);

        Http::fake([
            'https://customer.example.com/*' => Http::response(['ok' => false, 'error' => 'unknown_integration'], 404),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.tools.check', [$member, $tool]))
            ->assertRedirect(route('admin.users.tools.show', [$member, $tool]))
            ->assertSessionHas('warning');

        $this->assertSame('pending_merchant', $tool->fresh('integration')->integration->connection_status);
    }
}
