<?php

namespace Tests\Feature\SiteIntegrations;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Enums\SiteIntegrationStatus;
use App\Enums\UserToolStatus;
use App\Models\AuditLog;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\SiteIntegration;
use App\Models\SiteIntegrationCheckLog;
use App\Models\User;
use App\Models\UserTool;
use App\Models\UserToolIntegration;
use App\Services\SiteIntegrations\DemoLaunchService;
use App\Services\SiteIntegrations\OwnedAdminCredentialSyncService;
use App\Services\SiteIntegrations\ProtocolV1Signer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnedAdminCredentialSyncTest extends TestCase
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
                'slug' => 'website-services-cred-sync',
                'is_active' => true,
                'sort_order' => 1,
            ]);
            $service = $this->forceCreateProductType([
                'service_category_id' => $category->id,
                'name' => 'Website Package',
                'slug' => 'website-package-cred-sync',
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }

        $product = $this->forceCreatePlatformProduct([
            'title' => 'Banking Site',
            'slug' => 'banking-cred-sync-'.Str::lower(Str::random(4)),
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

    /**
     * @return array{tool: UserTool, integration: UserToolIntegration, client_secret: string, webhook_secret: string}
     */
    private function makeOwned(User $user, PlatformProduct $product): array
    {
        $clientSecret = 'client-secret-'.Str::random(12);
        $webhookSecret = 'webhook-secret-'.Str::random(12);

        $tool = UserTool::query()->create([
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
            'admin_password' => 'OldPass123!',
            'instance_sequence' => 1,
            'display_name' => $product->title,
        ]);

        $integration = UserToolIntegration::query()->create([
            'user_tool_id' => $tool->id,
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_'.Str::lower(Str::random(8)),
            'client_secret' => $clientSecret,
            'webhook_secret' => $webhookSecret,
            'capabilities' => UserToolIntegration::defaultCapabilities(),
            'connection_status' => 'ok',
        ]);

        return [
            'tool' => $tool->fresh(),
            'integration' => $integration->fresh(),
            'client_secret' => $clientSecret,
            'webhook_secret' => $webhookSecret,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function signedPayload(UserToolIntegration $integration, string $clientSecret, array $extra): array
    {
        $base = [
            'integration_id' => $integration->integration_id,
            'context' => 'owned_tool',
            'role' => 'credential_sync',
            'event' => OwnedAdminCredentialSyncService::EVENT,
            'event_id' => (string) Str::uuid(),
            'request_id' => (string) Str::uuid(),
            'nonce' => Str::random(24),
            'issued_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(3)->toIso8601String(),
        ];

        return app(ProtocolV1Signer::class)->sign(array_merge($base, $extra), $clientSecret);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postCredential(UserToolIntegration $integration, string $webhookSecret, array $payload, array $headers = [])
    {
        return $this->postJson(
            '/webhooks/site-integrations/'.$integration->integration_id,
            $payload,
            array_merge([
                'X-7TH-Webhook-Secret' => $webhookSecret,
                'X-7TH-Client-Id' => $integration->client_id,
            ], $headers)
        );
    }

    public function test_ping_still_works_with_webhook_secret_only(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $owned = $this->makeOwned($user, $product);

        $this->postJson('/webhooks/site-integrations/'.$owned['integration']->integration_id, [
            'event' => 'ping',
        ], [
            'X-7TH-Webhook-Secret' => $owned['webhook_secret'],
        ])->assertOk()->assertJson(['ok' => true]);

        $owned['integration']->refresh();
        $this->assertSame('ok', $owned['integration']->connection_status);
        $this->assertSame($owned['client_secret'], $owned['integration']->client_secret);
        $this->assertSame($owned['webhook_secret'], $owned['integration']->webhook_secret);
    }

    public function test_syncs_email_only_without_touching_connection_or_keys(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $owned = $this->makeOwned($user, $product);
        $integration = $owned['integration'];

        $payload = $this->signedPayload($integration, $owned['client_secret'], [
            'identity' => ['email' => 'Admin.New@Example.com'],
        ]);

        $this->postCredential($integration, $owned['webhook_secret'], $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $tool = $owned['tool']->fresh();
        $integration->refresh();

        $this->assertSame('admin.new@example.com', $tool->admin_email);
        $this->assertSame('OldPass123!', $tool->admin_password);
        $this->assertSame('ok', $integration->connection_status);
        $this->assertSame($owned['client_secret'], $integration->client_secret);
        $this->assertSame($owned['webhook_secret'], $integration->webhook_secret);
        $this->assertSame($owned['integration']->integration_id, $integration->integration_id);
        $this->assertSame(UserToolIntegration::defaultCapabilities(), $integration->capabilities);
        $this->assertSame(UserToolStatus::Active, $tool->status);
        $this->assertSame($owned['tool']->expires_at?->toIso8601String(), $tool->expires_at?->toIso8601String());

        $log = SiteIntegrationCheckLog::query()->where('owner_type', 'owned')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('Admin credentials updated', $log->message);
        $this->assertArrayNotHasKey('password', $log->payload_summary ?? []);
        $this->assertStringNotContainsString('OldPass', json_encode($log->payload_summary));
    }

    public function test_syncs_password_only_and_copy_endpoint_returns_it(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        $owned = $this->makeOwned($user, $product);
        $integration = $owned['integration'];

        $payload = $this->signedPayload($integration, $owned['client_secret'], [
            'credential' => ['password' => 'NewPass456!'],
        ]);

        $this->postCredential($integration, $owned['webhook_secret'], $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('owner-admin@example.com', $owned['tool']->fresh()->admin_email);
        $this->assertSame('NewPass456!', $owned['tool']->fresh()->admin_password);

        $this->actingAs($user)
            ->postJson(route('dashboard.my-tools.password', $owned['tool']))
            ->assertOk()
            ->assertJson(['password' => 'NewPass456!']);

        $audit = AuditLog::query()->where('action', 'user_tool.admin_credentials_synced')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('NewPass456!', json_encode($audit->new_values));
        $this->assertTrue((bool) ($audit->new_values['password_updated'] ?? false));
    }

    public function test_syncs_email_and_password_together(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $owned = $this->makeOwned($user, $product);
        $integration = $owned['integration'];

        $payload = $this->signedPayload($integration, $owned['client_secret'], [
            'identity' => ['email' => 'both@example.com'],
            'credential' => ['password' => 'BothPass99!'],
        ]);

        $this->postCredential($integration, $owned['webhook_secret'], $payload)->assertOk();

        $tool = $owned['tool']->fresh();
        $this->assertSame('both@example.com', $tool->admin_email);
        $this->assertSame('BothPass99!', $tool->admin_password);
    }

    public function test_email_sync_updates_auto_login_bound_identity(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        $owned = $this->makeOwned($user, $product);
        $integration = $owned['integration'];

        $payload = $this->signedPayload($integration, $owned['client_secret'], [
            'identity' => ['email' => 'sso-new@example.com'],
        ]);
        $this->postCredential($integration, $owned['webhook_secret'], $payload)->assertOk();

        $launch = app(DemoLaunchService::class)->launchOwnedAdmin($user, $owned['tool']->fresh('integration'));
        $this->assertSame('sso-new@example.com', $launch['assertion']['identity']['email'] ?? null);
    }

    public function test_duplicate_event_id_is_idempotent(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $owned = $this->makeOwned($user, $product);
        $integration = $owned['integration'];

        $payload = $this->signedPayload($integration, $owned['client_secret'], [
            'identity' => ['email' => 'first@example.com'],
        ]);

        $this->postCredential($integration, $owned['webhook_secret'], $payload)->assertOk()->assertJsonMissing(['deduped' => true]);
        $this->postCredential($integration, $owned['webhook_secret'], $payload)->assertOk()->assertJson(['ok' => true, 'deduped' => true]);

        $this->assertSame('first@example.com', $owned['tool']->fresh()->admin_email);
        $this->assertSame(1, SiteIntegrationCheckLog::query()->where('message', 'Admin credentials updated')->count());
    }

    public function test_demo_integration_is_forbidden_for_credential_event(): void
    {
        $product = $this->seedWebsiteProduct();
        $integration = SiteIntegration::query()->create([
            'platform_product_id' => $product->id,
            'name' => $product->title,
            'base_url' => 'https://demo.example.com',
            'integration_id' => (string) Str::uuid(),
            'client_id' => 'th_demo',
            'client_secret' => 'sec',
            'webhook_secret' => 'whsec-demo',
            'capabilities' => SiteIntegration::defaultCapabilities(),
            'status' => SiteIntegrationStatus::Active,
        ]);

        $this->postJson('/webhooks/site-integrations/'.$integration->integration_id, [
            'event' => OwnedAdminCredentialSyncService::EVENT,
        ], [
            'X-7TH-Webhook-Secret' => 'whsec-demo',
            'X-7TH-Client-Id' => 'th_demo',
        ])->assertForbidden();
    }

    public function test_bad_webhook_secret_is_unauthorized(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $owned = $this->makeOwned($user, $product);
        $payload = $this->signedPayload($owned['integration'], $owned['client_secret'], [
            'identity' => ['email' => 'x@example.com'],
        ]);

        $this->postCredential($owned['integration'], 'wrong-secret', $payload)->assertUnauthorized();
        $this->assertSame('owner-admin@example.com', $owned['tool']->fresh()->admin_email);
    }

    public function test_bad_client_id_is_unauthorized(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $owned = $this->makeOwned($user, $product);
        $payload = $this->signedPayload($owned['integration'], $owned['client_secret'], [
            'identity' => ['email' => 'x@example.com'],
        ]);

        $this->postCredential($owned['integration'], $owned['webhook_secret'], $payload, [
            'X-7TH-Client-Id' => 'wrong-client',
        ])->assertUnauthorized();
    }

    public function test_invalid_signature_is_unauthorized(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $owned = $this->makeOwned($user, $product);
        $payload = $this->signedPayload($owned['integration'], $owned['client_secret'], [
            'identity' => ['email' => 'x@example.com'],
        ]);
        $payload['signature'] = str_repeat('a', 64);

        $this->postCredential($owned['integration'], $owned['webhook_secret'], $payload)
            ->assertUnauthorized();
    }

    public function test_expired_assertion_is_unprocessable(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $owned = $this->makeOwned($user, $product);
        $payload = $this->signedPayload($owned['integration'], $owned['client_secret'], [
            'identity' => ['email' => 'late@example.com'],
            'expires_at' => now()->subMinute()->toIso8601String(),
        ]);

        $this->postCredential($owned['integration'], $owned['webhook_secret'], $payload)
            ->assertStatus(422);
        $this->assertSame('owner-admin@example.com', $owned['tool']->fresh()->admin_email);
    }

    public function test_missing_email_and_password_is_unprocessable(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $owned = $this->makeOwned($user, $product);
        $payload = $this->signedPayload($owned['integration'], $owned['client_secret'], []);

        $this->postCredential($owned['integration'], $owned['webhook_secret'], $payload)
            ->assertStatus(422);
        $this->assertSame('owner-admin@example.com', $owned['tool']->fresh()->admin_email);
    }

    public function test_unknown_webhook_event_is_accepted_like_ping(): void
    {
        $product = $this->seedWebsiteProduct();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $owned = $this->makeOwned($user, $product);

        $this->postJson('/webhooks/site-integrations/'.$owned['integration']->integration_id, [
            'event' => 'site.heartbeat',
        ], [
            'X-7TH-Webhook-Secret' => $owned['webhook_secret'],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('owner-admin@example.com', $owned['tool']->fresh()->admin_email);
        $this->assertSame('ok', $owned['integration']->fresh()->connection_status);
    }
}
