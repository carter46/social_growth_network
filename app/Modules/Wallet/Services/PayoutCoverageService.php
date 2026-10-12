<?php

namespace App\Modules\Wallet\Services;

use App\Models\PayoutCoverage;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Admin\Services\AuditLogService;
use App\Modules\Wallet\Payments\Data\PayoutResult;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Services\Notifications\AdminPaymentAlertNotifier;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Which country + method pairs agents may use, per gateway. A route is offered only when it is
 * in config/payout_methods.php and at least one configured gateway on its route has it verified.
 */
class PayoutCoverageService
{
    public function __construct(
        private GatewayRegistry $registry,
        private AdminPaymentAlertNotifier $alerts,
        private AuditLogService $audit,
    ) {}

    public function inConfig(string $country, string $method): bool
    {
        return config('payout_methods.countries.'.strtoupper($country).'.methods.'.$method) !== null;
    }

    public function isRouteOffered(string $country, string $method): bool
    {
        return $this->inConfig($country, $method) && $this->registry->payoutRoute($country, $method) !== [];
    }

    /**
     * Countries an agent can pick, each with its offered methods.
     *
     * @return array<string, array{name: string, currency: string, methods: array<string, string>}>
     */
    public function offeredCountries(): array
    {
        $offered = [];
        foreach ((array) config('payout_methods.countries', []) as $code => $country) {
            $methods = [];
            foreach (array_keys((array) ($country['methods'] ?? [])) as $method) {
                if ($this->isRouteOffered($code, $method)) {
                    $methods[$method] = (string) config('payout_methods.methods.'.$method, $method);
                }
            }
            if ($methods !== []) {
                $offered[$code] = ['name' => (string) $country['name'], 'currency' => (string) $country['currency'], 'methods' => $methods];
            }
        }
        uasort($offered, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $offered;
    }

    /**
     * Pull coverage() from every configured payout driver and upsert rows.
     *
     * @return array<string, array{ok: bool, verified: int, removed: int, error: ?string}>
     */
    public function sync(?string $onlyGateway = null): array
    {
        $summary = [];

        foreach ($this->registry->payoutOrder() as $key) {
            if ($onlyGateway !== null && $key !== $onlyGateway) {
                continue;
            }
            $driver = $this->registry->payoutDriver($key);
            if (! $driver || ! $driver->isConfigured()) {
                continue;
            }

            try {
                $routes = $driver->coverage();
            } catch (\Throwable $e) {
                // Keep the last verified state; just record the error.
                PayoutCoverage::query()->where('gateway_key', $key)->update([
                    'last_error' => mb_substr($e->getMessage(), 0, 1000),
                    'last_synced_at' => now(),
                ]);
                Log::warning('payments.coverage.sync_failed', ['gateway' => $key, 'error' => $e->getMessage()]);
                $this->alerts->coverageAlert(
                    'Payout coverage sync failed for '.$key,
                    'The last verified routes are kept. Error: '.mb_substr($e->getMessage(), 0, 300),
                    'coverage.sync_failed.'.$key.'.'.now()->toDateString(),
                    ['gateway' => $key],
                );
                $summary[$key] = ['ok' => false, 'verified' => 0, 'removed' => 0, 'error' => $e->getMessage()];

                continue;
            }

            $seen = [];
            $verified = 0;
            foreach ($routes as $route) {
                $country = strtoupper($route->country);
                if (! $this->inConfig($country, $route->method)) {
                    continue;
                }
                $seen[$country.'|'.$route->method] = true;

                $row = PayoutCoverage::query()->firstOrNew([
                    'gateway_key' => $key,
                    'country' => $country,
                    'method' => $route->method,
                ]);
                $row->fill([
                    'currency' => strtoupper($route->currency),
                    'resolve_supported' => $route->resolveSupported,
                    'provider_min' => $route->providerMin,
                    'provider_max' => $route->providerMax,
                    'last_error' => null,
                    'last_synced_at' => now(),
                ]);
                if ($row->state !== PayoutCoverage::DISABLED) {
                    if ($row->state !== PayoutCoverage::VERIFIED_API) {
                        $row->verified_at = now();
                        $row->verified_by = null;
                    }
                    $row->state = PayoutCoverage::VERIFIED_API;
                    $verified++;
                }
                $row->save();
            }

            $removed = 0;
            PayoutCoverage::query()
                ->where('gateway_key', $key)
                ->where('state', PayoutCoverage::VERIFIED_API)
                ->get()
                ->each(function (PayoutCoverage $row) use ($seen, $key, &$removed) {
                    if (isset($seen[$row->country.'|'.$row->method])) {
                        return;
                    }
                    $row->update(['state' => PayoutCoverage::UNAVAILABLE, 'last_synced_at' => now(), 'last_error' => 'No longer reported by the provider.']);
                    $removed++;
                    $this->alerts->coverageAlert(
                        'Payout route no longer available: '.$row->country.' '.$row->method.' ('.$key.')',
                        'The provider stopped reporting this route. New withdrawals to it are blocked; open withdrawals are unchanged.',
                        'coverage.removed.'.$key.'.'.$row->country.'.'.$row->method.'.'.now()->toDateString(),
                        ['gateway' => $key, 'country' => $row->country, 'method' => $row->method],
                    );
                });

            $summary[$key] = ['ok' => true, 'verified' => $verified, 'removed' => $removed, 'error' => null];
        }

        return $summary;
    }

    public function disable(string $gatewayKey, string $country, string $method, int $adminId, ?string $note = null): PayoutCoverage
    {
        $row = $this->row($gatewayKey, $country, $method);
        $old = $row->only(['state', 'note']);
        $row->fill(['state' => PayoutCoverage::DISABLED, 'note' => $note ?: $row->note, 'verified_by' => $adminId])->save();

        $this->audit->log($adminId, 'payout_coverage.disabled', $row, $old, $row->only(['gateway_key', 'country', 'method', 'state', 'note']));

        return $row;
    }

    /**
     * Manual enable for a route the API doesn't confirm. Needs a successful test payout on that
     * gateway (our attempt reference, or a provider reference the gateway confirms as paid) and a note.
     */
    public function enableManually(string $gatewayKey, string $country, string $method, string $testReference, string $note, int $adminId): PayoutCoverage
    {
        $country = strtoupper($country);
        if (! $this->inConfig($country, $method)) {
            throw new InvalidArgumentException('This country and method are not in the payout configuration.');
        }
        $driver = $this->registry->payoutDriver($gatewayKey);
        if (! $driver) {
            throw new InvalidArgumentException('Unknown payout gateway.');
        }
        if (trim($note) === '') {
            throw new InvalidArgumentException('Add a note explaining the manual enable.');
        }
        if (! $this->testPayoutSucceeded($gatewayKey, $country, $method, trim($testReference))) {
            throw new InvalidArgumentException('The test payout reference was not confirmed as a successful payout on '.$driver->displayName().' for this route.');
        }

        $row = $this->row($gatewayKey, $country, $method);
        $old = $row->only(['state', 'note', 'test_payout_reference']);
        $row->fill([
            'currency' => $row->currency ?: (string) config('payout_methods.countries.'.$country.'.currency', 'USD'),
            'state' => PayoutCoverage::VERIFIED_MANUAL,
            'verified_at' => now(),
            'verified_by' => $adminId,
            'test_payout_reference' => trim($testReference),
            'manual_expires_at' => now()->addDays((int) config('payments.payout.manual_coverage_days', 90)),
            'note' => trim($note),
        ])->save();

        $this->audit->log($adminId, 'payout_coverage.manual_enabled', $row, $old, $row->only([
            'gateway_key', 'country', 'method', 'state', 'note', 'test_payout_reference', 'manual_expires_at',
        ]));

        return $row;
    }

    /** Clear an admin disable; the next sync decides the state again. */
    public function reset(string $gatewayKey, string $country, string $method, int $adminId): PayoutCoverage
    {
        $row = $this->row($gatewayKey, $country, $method);
        $old = $row->only(['state', 'note']);
        $row->fill(['state' => PayoutCoverage::UNAVAILABLE, 'manual_expires_at' => null])->save();
        $this->audit->log($adminId, 'payout_coverage.reset', $row, $old, $row->only(['gateway_key', 'country', 'method', 'state']));

        return $row;
    }

    private function testPayoutSucceeded(string $gatewayKey, string $country, string $method, string $reference): bool
    {
        if ($reference === '') {
            return false;
        }

        $attempt = WithdrawalPayoutAttempt::query()
            ->where('gateway_key', $gatewayKey)
            ->where(fn ($q) => $q->where('reference', $reference)->orWhere('provider_reference', $reference))
            ->with('withdrawal')
            ->first();
        if ($attempt) {
            $w = $attempt->withdrawal;

            return $attempt->status === WithdrawalPayoutAttempt::SUCCEEDED
                && $w
                && strtoupper((string) $w->destination_country) === $country
                && (string) $w->method === $method;
        }

        $driver = $this->registry->payoutDriver($gatewayKey);
        if (! $driver || ! $driver->isConfigured()) {
            return false;
        }
        try {
            return $driver->transferStatus($reference, $reference)->status === PayoutResult::SUCCEEDED;
        } catch (\Throwable) {
            return false;
        }
    }

    private function row(string $gatewayKey, string $country, string $method): PayoutCoverage
    {
        $country = strtoupper($country);
        if (! $this->inConfig($country, $method)) {
            throw new InvalidArgumentException('This country and method are not in the payout configuration.');
        }

        return PayoutCoverage::query()->firstOrNew(
            ['gateway_key' => $gatewayKey, 'country' => $country, 'method' => $method],
            ['currency' => (string) config('payout_methods.countries.'.$country.'.currency', 'USD'), 'state' => PayoutCoverage::UNAVAILABLE],
        );
    }
}
