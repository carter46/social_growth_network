<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Modules\Admin\Services\AuditLogService;
use App\Modules\Wallet\Payments\AbstractGatewayDriver;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use App\Modules\Wallet\Payments\Bachs\BachsDriver;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Modules\Wallet\Payments\PayoutGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Credentials, enable switch and checkout/payout order for every installed payment driver.
 * Secrets are stored encrypted on integration_providers, never in .env.
 */
class PaymentGatewaySettingsController extends Controller
{
    public function __construct(
        private GatewayRegistry $registry,
        private AuditLogService $audit,
    ) {}

    public function update(Request $request, string $gateway): RedirectResponse
    {
        abort_unless($this->registry->has($gateway), 404);
        $driver = $this->registry->driver($gateway);
        abort_unless($driver instanceof AbstractGatewayDriver, 404);

        $rules = ['enabled' => ['nullable', 'boolean']];
        foreach ($driver->credentialFields() as $field) {
            $name = 'fields.'.$field['name'];
            $rules[$name] = match ($field['type']) {
                'select' => ['nullable', 'string', Rule::in(array_keys($field['options'] ?? []))],
                default => ['nullable', 'string', 'max:2000'],
            };
        }
        $validated = $request->validate($rules);

        $provider = $driver->provider();
        $credentials = [];
        $meta = $provider->meta ?? [];
        $changed = [];

        foreach ($driver->credentialFields() as $field) {
            $value = $validated['fields'][$field['name']] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $store = $field['store'] ?? 'credentials';

            if ($field['type'] === 'secret' && blank($value)) {
                continue;
            }

            if ($store === 'meta') {
                $meta[$field['name']] = $value;
            } else {
                $credentials[$field['name']] = $value;
            }
            $changed[] = $field['name'];
        }

        $previousEnvironment = ($provider->meta['environment'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
        $wasEnabled = (bool) $provider->enabled;
        $provider->enabled = $request->boolean('enabled');
        $provider->mergeCredentials($credentials);
        $provider->meta = $meta;
        $provider->save();
        $driver->refreshProvider();

        $this->audit->log(auth()->id(), 'settings.payment_gateway.updated', null, ['enabled' => $wasEnabled], [
            'gateway' => $gateway,
            'enabled' => (bool) $provider->enabled,
            'fields' => $changed,
        ], $request->ip());

        $message = $driver->displayName().' settings saved.';
        if ($provider->enabled && ! $driver->isConfigured()) {
            $message .= ' Some required fields are still empty, so it will not be offered yet.';
        }

        if ($gateway === BachsDriver::KEY) {
            $newEnvironment = $driver->environment();
            if ($previousEnvironment !== $newEnvironment) {
                $cleared = app(BachsConnectService::class)->invalidateMismatched($newEnvironment);
                $this->audit->log(auth()->id(), 'settings.payment_gateway.connect_reset', null, null, [
                    'gateway' => $gateway,
                    'from' => $previousEnvironment,
                    'to' => $newEnvironment,
                    'cleared' => $cleared,
                ], $request->ip());
                $message .= ' Cleared '.$cleared.' Connect account'.($cleared === 1 ? '' : 's').' from '.$previousEnvironment.' so agents can reconnect on '.$newEnvironment.'.';
            }
        }

        return back()->with('success', $message);
    }

    /** Clear Bachs Connect rows that do not match the currently configured environment. */
    public function resetMismatchedConnect(Request $request): RedirectResponse
    {
        abort_unless($this->registry->has(BachsDriver::KEY), 404);

        $connect = app(BachsConnectService::class);
        $environment = $connect->currentEnvironment();
        $cleared = $connect->invalidateMismatched($environment);

        $this->audit->log(auth()->id(), 'settings.payment_gateway.connect_reset', null, null, [
            'gateway' => BachsDriver::KEY,
            'to' => $environment,
            'cleared' => $cleared,
            'manual' => true,
        ], $request->ip());

        return back()->with(
            'success',
            $cleared === 0
                ? __('No Connect accounts needed clearing for :env.', ['env' => $environment])
                : __('Cleared :count Connect account(s). Those agents can reconnect on :env; profile details still autofill.', [
                    'count' => $cleared,
                    'env' => $environment,
                ])
        );
    }

    public function test(Request $request, string $gateway): JsonResponse|RedirectResponse
    {
        abort_unless($this->registry->has($gateway), 404);
        $driver = $this->registry->driver($gateway);

        try {
            $result = $driver->testConnection();
        } catch (Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        if ($driver instanceof AbstractGatewayDriver) {
            $result['ok']
                ? $driver->provider()->recordSuccess()
                : $driver->provider()->recordFailure((string) $result['message']);
        }

        if ($result['ok']) {
            $this->registry->health()->recordSuccess($gateway);
        }

        if ($request->expectsJson()) {
            return response()->json($result, $result['ok'] ? 200 : 422);
        }

        return $result['ok']
            ? back()->with('success', $result['message'])
            : back()->withErrors(['gateway_test_'.$gateway => $result['message']]);
    }

    /**
     * Cutover verification and the payouts switch. Payouts can only be switched on once the site
     * runs in USD and the cutover has been verified; clearing the verification switches them off.
     */
    public function updatePayoutGate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['verify_cutover', 'clear_cutover', 'enable', 'disable'])],
            'confirm_report' => ['nullable', 'boolean'],
        ]);

        $before = [
            'payouts_enabled' => SystemSetting::enabled(PayoutGate::SETTING_ENABLED, false),
            'cutover_verified_at' => PayoutGate::cutoverVerifiedAt()?->toIso8601String(),
        ];

        switch ($validated['action']) {
            case 'verify_cutover':
                if (site_currency()->code !== PayoutGate::PAYOUT_CURRENCY) {
                    return back()->withErrors(['payout_gate' => 'Set the site currency to '.PayoutGate::PAYOUT_CURRENCY.' and run the conversion before verifying the cutover.']);
                }
                if (! $request->boolean('confirm_report')) {
                    return back()->withErrors(['payout_gate' => 'Confirm that you reviewed the post-conversion report (php artisan currency:cutover-report).']);
                }
                SystemSetting::set(PayoutGate::SETTING_CUTOVER_VERIFIED_AT, now()->toIso8601String());
                SystemSetting::set(PayoutGate::SETTING_CUTOVER_VERIFIED_BY, (string) auth()->id());
                $message = 'Currency cutover marked as verified. Payouts are still off until you switch them on.';
                break;
            case 'clear_cutover':
                SystemSetting::set(PayoutGate::SETTING_CUTOVER_VERIFIED_AT, '');
                SystemSetting::set(PayoutGate::SETTING_CUTOVER_VERIFIED_BY, '');
                SystemSetting::set(PayoutGate::SETTING_ENABLED, false);
                $message = 'Cutover verification cleared and gateway payouts switched off.';
                break;
            case 'enable':
                SystemSetting::set(PayoutGate::SETTING_ENABLED, true);
                $reasons = PayoutGate::reasons();
                if ($reasons !== []) {
                    SystemSetting::set(PayoutGate::SETTING_ENABLED, false);

                    return back()->withErrors(['payout_gate' => implode(' ', $reasons)]);
                }
                $message = 'Gateway payouts switched on.';
                break;
            default:
                SystemSetting::set(PayoutGate::SETTING_ENABLED, false);
                $message = 'Gateway payouts switched off. New approvals wait in review.';
        }

        $this->audit->log(auth()->id(), 'settings.payout_gate.'.$validated['action'], null, $before, [
            'payouts_enabled' => SystemSetting::enabled(PayoutGate::SETTING_ENABLED, false),
            'cutover_verified_at' => PayoutGate::cutoverVerifiedAt()?->toIso8601String(),
        ], $request->ip());

        return back()->with('success', $message);
    }

    public function updateOrder(Request $request): RedirectResponse
    {
        $keys = $this->registry->keys();
        $validated = $request->validate([
            'collection' => ['nullable', 'array'],
            'collection.*' => ['string', Rule::in($keys)],
            'payout' => ['nullable', 'array'],
            'payout.*' => ['string', Rule::in($keys)],
        ]);

        $old = [
            'collection' => $this->registry->collectionOrder(),
            'payout' => $this->registry->payoutOrder(),
        ];

        $this->registry->setCollectionOrder(array_values($validated['collection'] ?? []));
        $this->registry->setPayoutOrder(array_values($validated['payout'] ?? []));

        $this->audit->log(auth()->id(), 'settings.payment_gateway.order_updated', null, $old, [
            'collection' => $this->registry->collectionOrder(),
            'payout' => $this->registry->payoutOrder(),
        ], $request->ip());

        return back()->with('success', 'Gateway order saved.');
    }
}
