<?php

namespace App\Services\SiteIntegrations;

use App\Enums\PlatformProductType;
use App\Enums\SiteIntegrationStatus;
use App\Models\PlatformProduct;
use App\Models\SiteIntegration;
use App\Modules\Admin\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SiteIntegrationAdminService
{
    public function __construct(
        private IntegrationCredentialGenerator $credentials,
        private ConnectionCheckService $connectionCheck,
        private AuditLogService $audit,
        private IntegrationOutboundUrlGuard $urlGuard,
    ) {}

    /**
     * @param  array{platform_product_id: int, name?: string, base_url: string, demo_user_email?: string|null, demo_admin_email?: string|null, capabilities?: list<string>|null}  $data
     * @return array{integration: SiteIntegration, credentials: array{integration_id: string, client_id: string, client_secret: string, webhook_secret: string, webhook_url: string}}
     */
    public function create(array $data, ?int $adminId = null, ?string $ip = null): array
    {
        $product = PlatformProduct::query()->findOrFail($data['platform_product_id']);
        if ($product->product_type !== PlatformProductType::SocialService) {
            throw new InvalidArgumentException('Only social service products can be demo-integrated.');
        }

        if (SiteIntegration::query()->where('platform_product_id', $product->id)->exists()) {
            throw new InvalidArgumentException('This product already has a demo integration. Edit it instead.');
        }

        $this->urlGuard->assertSafe($data['base_url'], httpsOnly: true);

        $creds = $this->credentials->generate();
        $capabilities = $data['capabilities'] ?? SiteIntegration::defaultCapabilities();

        $integration = SiteIntegration::create([
            'platform_product_id' => $product->id,
            'name' => $data['name'] ?? $product->title,
            'base_url' => rtrim($data['base_url'], '/'),
            'demo_user_email' => $data['demo_user_email'] ?? null,
            'demo_admin_email' => $data['demo_admin_email'] ?? null,
            'integration_id' => $creds['integration_id'],
            'client_id' => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'webhook_secret' => $creds['webhook_secret'],
            'capabilities' => $capabilities,
            'status' => SiteIntegrationStatus::Draft,
            'connection_status' => 'unchecked',
        ]);

        $this->audit->log($adminId, 'site_integration.created', $integration, null, [
            'platform_product_id' => $product->id,
            'base_url' => $integration->base_url,
        ], $ip, ['module' => 'site_integrations']);

        return [
            'integration' => $integration,
            'credentials' => [
                ...$creds,
                'webhook_url' => url('/webhooks/site-integrations/'.$creds['integration_id']),
            ],
        ];
    }

    /**
     * @param  array{name?: string, base_url?: string, demo_user_email?: string|null, demo_admin_email?: string|null, capabilities?: list<string>|null, status?: string}  $data
     */
    public function update(SiteIntegration $integration, array $data, ?int $adminId = null, ?string $ip = null): SiteIntegration
    {
        $old = $integration->only(['name', 'base_url', 'demo_user_email', 'demo_admin_email', 'status', 'capabilities']);

        if (isset($data['base_url'])) {
            $this->urlGuard->assertSafe($data['base_url'], httpsOnly: true);
            $data['base_url'] = rtrim($data['base_url'], '/');
        }

        if (isset($data['status'])) {
            $data['status'] = SiteIntegrationStatus::from($data['status']);
        }

        $integration->fill($data);
        $integration->save();

        $this->audit->log($adminId, 'site_integration.updated', $integration, $old, $integration->only([
            'name', 'base_url', 'demo_user_email', 'demo_admin_email', 'status', 'capabilities',
        ]), $ip, ['module' => 'site_integrations']);

        return $integration;
    }

    /**
     * @return array{integration: SiteIntegration, credentials: array{integration_id: string, client_id: string, client_secret: string, webhook_secret: string, webhook_url: string}}
     */
    public function rotateCredentials(SiteIntegration $integration, ?int $adminId = null, ?string $ip = null): array
    {
        $creds = $this->credentials->generate();

        $integration->fill([
            'integration_id' => $creds['integration_id'],
            'client_id' => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'webhook_secret' => $creds['webhook_secret'],
            'connection_status' => 'unchecked',
            'last_error' => null,
        ]);
        $integration->save();

        $this->audit->log($adminId, 'site_integration.credentials_rotated', $integration, null, [
            'integration_id' => $creds['integration_id'],
        ], $ip, ['module' => 'site_integrations']);

        return [
            'integration' => $integration,
            'credentials' => [
                ...$creds,
                'webhook_url' => url('/webhooks/site-integrations/'.$creds['integration_id']),
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function checkConnection(SiteIntegration $integration): array
    {
        $result = $this->connectionCheck->checkDemo($integration);

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
        ];
    }
}
