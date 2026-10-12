<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PayoutCoverage;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Modules\Wallet\Services\PayoutCoverageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Grid of country + method against payout gateway. Disable is always allowed; a manual enable
 * needs a successful test payout reference and a note, and expires.
 */
class PayoutCoverageController extends Controller
{
    public function __construct(
        private PayoutCoverageService $coverage,
        private GatewayRegistry $registry,
    ) {}

    public function index(): View
    {
        $gateways = [];
        foreach ($this->registry->payoutOrder() as $key) {
            $driver = $this->registry->payoutDriver($key);
            if ($driver) {
                $gateways[$key] = ['name' => $driver->displayName(), 'configured' => $driver->isConfigured()];
            }
        }

        $rows = PayoutCoverage::query()->get()->keyBy(fn (PayoutCoverage $r) => $r->gateway_key.'|'.$r->country.'|'.$r->method);

        $routes = [];
        foreach ((array) config('payout_methods.countries', []) as $code => $country) {
            foreach (array_keys((array) ($country['methods'] ?? [])) as $method) {
                $routes[] = [
                    'country' => $code,
                    'country_name' => (string) $country['name'],
                    'method' => $method,
                    'method_label' => (string) config('payout_methods.methods.'.$method, $method),
                    'offered' => $this->coverage->isRouteOffered($code, $method),
                ];
            }
        }
        usort($routes, fn ($a, $b) => [$a['country_name'], $a['method']] <=> [$b['country_name'], $b['method']]);

        return view('dashboard.admin.payout-coverage', [
            'gateways' => $gateways,
            'rows' => $rows,
            'routes' => $routes,
            'lastErrors' => PayoutCoverage::query()->whereNotNull('last_error')->get()->groupBy('gateway_key')->map(fn ($g) => $g->first()->last_error),
            'lastSynced' => PayoutCoverage::query()->max('last_synced_at'),
        ]);
    }

    public function sync(): RedirectResponse
    {
        $summary = $this->coverage->sync();
        $parts = [];
        foreach ($summary as $key => $s) {
            $parts[] = $key.': '.($s['ok'] ? $s['verified'].' verified, '.$s['removed'].' no longer reported' : 'failed ('.mb_substr((string) $s['error'], 0, 120).')');
        }

        return back()->with('status', $parts === [] ? __('No configured payout gateways to sync.') : __('Sync finished. ').implode('; ', $parts));
    }

    public function disable(Request $request): RedirectResponse
    {
        $data = $this->validated($request, ['note' => ['nullable', 'string', 'max:500']]);

        try {
            $this->coverage->disable($data['gateway'], $data['country'], $data['method'], (int) auth()->id(), $data['note'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Route disabled.'));
    }

    public function enable(Request $request): RedirectResponse
    {
        $data = $this->validated($request, [
            'test_reference' => ['required', 'string', 'max:100'],
            'note' => ['required', 'string', 'max:500'],
            'confirm' => ['required', 'accepted'],
        ]);

        try {
            $this->coverage->enableManually($data['gateway'], $data['country'], $data['method'], $data['test_reference'], $data['note'], (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Route enabled manually. It expires in :days days unless re-confirmed.', ['days' => (int) config('payments.payout.manual_coverage_days', 90)]));
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $this->coverage->reset($data['gateway'], $data['country'], $data['method'], (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Route reset. Run a sync to check it with the provider.'));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $extra = []): array
    {
        return $request->validate([
            'gateway' => ['required', 'string', 'in:'.implode(',', $this->registry->payoutOrder())],
            'country' => ['required', 'string', 'size:2'],
            'method' => ['required', 'string', 'max:30'],
        ] + $extra);
    }
}
