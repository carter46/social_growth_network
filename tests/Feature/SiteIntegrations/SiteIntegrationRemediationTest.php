<?php

namespace Tests\Feature\SiteIntegrations;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Enums\SiteIntegrationStatus;
use App\Enums\UserToolStatus;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Models\UserTool;
use App\Models\UserToolIntegration;
use App\Models\Wallet;
use App\Services\SiteIntegrations\DemoLaunchService;
use App\Services\SiteIntegrations\SubscriptionSyncService;
use App\Services\SiteIntegrations\UserToolProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteIntegrationRemediationTest extends TestCase
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
            'title' => 'Website Package '.Str::random(4),
            'slug' => 'website-pkg-'.Str::lower(Str::random(6)),
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

    private function makeOwnedTool(User $user, PlatformProduct $product, array $attrs = []): UserTool
    {
        $tool = UserTool::query()->create(array_merge([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $product->activeVariants->first()->id,
            'status' => UserToolStatus::Active,
            'purchased_at' => now()->subMonth(),
            'configured_at' => now()->subMonth(),
            'expires_at' => now()->addMonths(2),
            'duration_months' => 3,
            'site_url' => 'https://customer.example.com',
            'admin_login_url' => 'https://customer.example.com/admin',
            'admin_email' => 'owner-admin@example.com',
            'admin_password' => 'SecretPass123!',
            'instance_sequence' => 1,
            'display_name' => $product->title,
        ], $attrs));

        UserToolIntegration::query()->create([
            'user_tool_id' => $tool->id,
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_'.Str::lower(Str::random(8)),
            'client_secret' => 'client-secret-'.Str::random(8),
            'webhook_secret' => 'webhook-secret-'.Str::random(8),
            'capabilities' => UserToolIntegration::defaultCapabilities(),
            'connection_status' => 'ok',
        ]);

        return $tool->fresh('integration');
    }

    public function test_webhook_accepts_valid_secret_without_csrf_and_rejects_bad_secret(): void
    {
        $product = $this->seedWebsiteProduct();
        $integration = SiteIntegration::query()->create([
            'platform_product_id' => $product->id,
            'name' => $product->title,
            'base_url' => 'https://demo.example.com',
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_demo',
            'client_secret' => 'sec',
            'webhook_secret' => 'whsec-correct',
            'capabilities' => SiteIntegration::defaultCapabilities(),
            'status' => SiteIntegrationStatus::Active,
        ]);

        $this->postJson('/webhooks/site-integrations/'.$integration->integration_id, [
            'event' => 'ping',
        ], [
            'X-7TH-Webhook-Secret' => 'whsec-correct',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->postJson('/webhooks/site-integrations/'.$integration->integration_id, [
            'event' => 'ping',
        ], [
            'X-7TH-Webhook-Secret' => 'wrong',
        ])->assertUnauthorized();

        $this->postJson('/webhooks/site-integrations/'.(string) Str::uuid(), [
            'event' => 'ping',
        ], [
            'X-7TH-Webhook-Secret' => 'whsec-correct',
        ])->assertNotFound();
    }

    public function test_site_integrations_webhook_is_csrf_excepted_like_monnify(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringContainsString("'webhooks/monnify'", $bootstrap);
        $this->assertStringContainsString("'webhooks/site-integrations/*'", $bootstrap);
    }

    public function test_active_status_with_past_expires_at_cannot_launch(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        $tool = $this->makeOwnedTool($user, $product, [
            'status' => UserToolStatus::Active,
            'expires_at' => now()->subHour(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(DemoLaunchService::class)->launchOwnedAdmin($user, $tool->fresh('integration'));
    }

    public function test_subscription_poll_reports_expired_when_clock_passed(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $tool = $this->makeOwnedTool($user, $product, [
            'status' => UserToolStatus::Active,
            'expires_at' => now()->subMinute(),
        ]);

        $snapshot = app(SubscriptionSyncService::class)->snapshotForClient(
            $tool->integration->integration_id,
            $tool->integration->client_id
        );

        $this->assertSame('expired', $snapshot['status']);
    }

    public function test_reconfigure_does_not_extend_expires_at(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $tool = $this->makeOwnedTool($user, $product);
        $originalExpiry = $tool->expires_at->copy();

        Http::fake([
            'https://customer.example.com/*' => Http::response(['ok' => true, 'capabilities' => []], 200),
        ]);

        app(UserToolProvisioningService::class)->reconfigure($tool, [
            'site_url' => 'https://customer.example.com',
            'admin_login_url' => 'https://customer.example.com/admin',
            'admin_email' => 'new-admin@example.com',
            'admin_password' => 'NewSecret123!',
        ]);

        $this->assertTrue($tool->fresh()->expires_at->equalTo($originalExpiry));
        $this->assertSame('new-admin@example.com', $tool->fresh()->admin_email);
    }

    public function test_setup_rejected_when_already_configured(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $tool = $this->makeOwnedTool($user, $product);

        $this->expectException(\InvalidArgumentException::class);
        app(UserToolProvisioningService::class)->setup($tool, [
            'site_url' => 'https://customer.example.com',
            'admin_login_url' => 'https://customer.example.com/admin',
            'admin_email' => 'a@example.com',
            'admin_password' => 'SecretPass123!',
        ]);
    }

    public function test_website_package_quantity_greater_than_one_rejected(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 1000000,
            'locked_balance' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $product->activeVariants->first()->id,
                'quantity' => 2,
                'domain_mode' => 'connect',
                'domain_fqdn' => 'mysite.com',
                'domain_connect_acknowledged' => '1',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('user_tools', [
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
        ]);
    }

    public function test_password_copy_refuses_expired_tool(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        $tool = $this->makeOwnedTool($user, $product, [
            'status' => UserToolStatus::Active,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.my-tools.password', $tool));

        $response->assertStatus(422);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_my_tools_idor_returns_404(): void
    {
        $product = $this->seedWebsiteProduct();
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $owner->assignRole('user');
        $intruder = User::factory()->create(['email_verified_at' => now()]);
        $intruder->assignRole('user');
        $tool = $this->makeOwnedTool($owner, $product);

        $this->actingAs($intruder)
            ->get(route('dashboard.my-tools.show', $tool))
            ->assertNotFound();
    }

    public function test_token_replay_rejected(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        $tool = $this->makeOwnedTool($user, $product);

        $launch = app(DemoLaunchService::class)->launchOwnedAdmin($user, $tool->fresh('integration'));
        parse_str(parse_url($launch['redirect_url'], PHP_URL_QUERY), $query);
        $token = $query['token'];
        $clientId = $tool->integration->client_id;
        $secret = $tool->integration->client_secret;

        $this->postJson('/api/site-integrations/v1/demo/tokens/validate', [
            'token' => $token,
        ], [
            'X-7TH-Client-Id' => $clientId,
            'X-7TH-Client-Secret' => $secret,
        ])->assertOk()->assertJson(['valid' => true]);

        $this->postJson('/api/site-integrations/v1/demo/tokens/validate', [
            'token' => $token,
        ], [
            'X-7TH-Client-Id' => $clientId,
            'X-7TH-Client-Secret' => $secret,
        ])->assertStatus(422);
    }

    public function test_expire_command_does_not_expire_renewed_tool(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create();
        $tool = $this->makeOwnedTool($user, $product, [
            'status' => UserToolStatus::Active,
            'expires_at' => now()->subMinute(),
        ]);

        Http::fake([
            'https://customer.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        // Simulate renew winning before expire flip by extending clock first.
        $tool->expires_at = now()->addMonths(3);
        $tool->save();

        $this->artisan('site-integrations:expire-user-tools')->assertSuccessful();

        $this->assertSame(UserToolStatus::Active, $tool->fresh()->status);
    }
}
