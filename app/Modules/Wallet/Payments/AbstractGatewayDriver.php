<?php

namespace App\Modules\Wallet\Payments;

use App\Models\IntegrationProvider;
use App\Modules\Wallet\Payments\Contracts\GatewayDriver;
use Illuminate\Support\Facades\Route;

/**
 * Shared credential handling for drivers. Credentials are stored encrypted on the
 * integration_providers row keyed by the driver's key().
 */
abstract class AbstractGatewayDriver implements GatewayDriver
{
    private ?IntegrationProvider $row = null;

    public function provider(): IntegrationProvider
    {
        return $this->row ??= IntegrationProvider::forProvider($this->key());
    }

    public function refreshProvider(): void
    {
        $this->row = null;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->provider()->enabled;
    }

    public function isConfigured(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        foreach ($this->credentialFields() as $field) {
            if (! ($field['required'] ?? false)) {
                continue;
            }
            if (blank($this->setting($field['name'], $field['store'] ?? 'credentials'))) {
                return false;
            }
        }

        return true;
    }

    public function environment(): string
    {
        return ($this->provider()->meta['environment'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    public function isLive(): bool
    {
        return $this->environment() === 'live';
    }

    public function webhookUrl(): ?string
    {
        return Route::has('webhooks.payments') ? route('webhooks.payments', ['provider' => $this->key()]) : null;
    }

    protected function credential(string $key): ?string
    {
        $value = $this->provider()->credential($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function meta(string $key, mixed $default = null): mixed
    {
        return $this->provider()->meta[$key] ?? $default;
    }

    protected function setting(string $name, string $store): mixed
    {
        return $store === 'meta' ? $this->meta($name) : $this->credential($name);
    }

    protected function timeout(): int
    {
        return (int) config('payments.http_timeout_seconds', 20);
    }

    protected function environmentField(): array
    {
        return [
            'name' => 'environment',
            'label' => 'Environment',
            'type' => 'select',
            'required' => true,
            'store' => 'meta',
            'options' => ['sandbox' => 'Sandbox', 'live' => 'Live'],
        ];
    }
}
