<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PayoutFeeRule;
use App\Models\SystemSetting;
use App\Modules\Admin\Services\AuditLogService;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Modules\Wallet\Services\PayoutQuoteService;
use App\Modules\Wallet\Services\WithdrawalFeeService;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeesLimitsController extends Controller
{
    public function __construct(
        private AuditLogService $audit,
        private WithdrawalFeeService $fees,
        private GatewayRegistry $registry,
    ) {}

    public function index(): View
    {
        $rules = PayoutFeeRule::query()->with('verifier')->orderBy('country')->orderBy('method')->orderBy('gateway_key')->get();

        $routes = collect($rules)->map(function (PayoutFeeRule $rule) {
            if (! $rule->country || ! $rule->method) {
                return null;
            }
            $limits = $this->fees->limits($rule->country, $rule->method, $rule->gateway_key);
            $feeAtMin = $rule->hasVerifiedProviderFee() ? PayoutQuoteService::verifiedFee($rule, $limits['min']) : null;

            return [
                'rule_id' => $rule->id,
                'min' => $limits['min'],
                'max' => $limits['max'],
                'fee_share_min' => $limits['fee_share_min'],
                'fee_at_min' => $feeAtMin,
                'share_at_min' => $feeAtMin && Money::isPositive($limits['min'], 2)
                    ? Money::div(Money::mul($feeAtMin, '100', 4), $limits['min'], 1)
                    : null,
            ];
        })->filter()->keyBy('rule_id');

        return view('dashboard.admin.fees-limits', [
            'withdrawalMinAmount' => SystemSetting::get('withdrawal_min_amount', '10'),
            'withdrawalMaxAmount' => SystemSetting::get('withdrawal_max_amount', '5000'),
            'depositMinAmount' => SystemSetting::get('deposit_min_amount', '1'),
            'depositMaxAmount' => SystemSetting::get('deposit_max_amount', '5000'),
            'bounceFee' => SystemSetting::get('withdrawal_bounce_fee', '0'),
            'rules' => $rules,
            'routeInfo' => $routes,
            'countries' => collect((array) config('payout_methods.countries', []))->map(fn ($c) => $c['name'])->sort()->all(),
            'methods' => (array) config('payout_methods.methods', []),
            'gateways' => collect($this->registry->payoutOrder())->mapWithKeys(fn ($k) => [$k => $this->registry->driver($k)->displayName()])->all(),
            'staleDays' => (int) config('payments.payout.verified_fee_max_age_days', 90),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'withdrawal_min_amount' => ['required', 'numeric', 'min:0.01'],
            'withdrawal_max_amount' => ['required', 'numeric', 'min:0.01', 'gte:withdrawal_min_amount'],
            'deposit_min_amount' => ['required', 'numeric', 'min:0.01'],
            'deposit_max_amount' => ['required', 'numeric', 'min:0.01', 'gte:deposit_min_amount'],
            'withdrawal_bounce_fee' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000'],
        ], [
            'withdrawal_max_amount.gte' => 'Maximum withdrawal must be at least the minimum withdrawal.',
            'deposit_max_amount.gte' => 'Maximum deposit must be at least the minimum deposit.',
        ]);
        if (array_key_exists('withdrawal_bounce_fee', $validated)) {
            $validated['withdrawal_bounce_fee'] ??= '0';
        }

        $keys = array_keys($validated);
        $old = [];
        foreach ($keys as $key) {
            $old[$key] = SystemSetting::get($key);
        }
        foreach ($keys as $key) {
            SystemSetting::set($key, (string) $validated[$key]);
        }

        $this->audit->log(auth()->id(), 'fees.updated', null, $old, $validated, $request->ip());

        return back()->with('status', __('Fees & limits saved.'));
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $data = $this->validateRule($request);
        $rule = PayoutFeeRule::create($data);
        $this->audit->log(auth()->id(), 'payout_fee_rule.created', $rule, null, $rule->toArray(), $request->ip());

        return back()->with('status', __('Payout fee rule added.'));
    }

    public function updateRule(Request $request, PayoutFeeRule $rule): RedirectResponse
    {
        $data = $this->validateRule($request, $rule);
        $old = $rule->toArray();
        $rule->update($data);
        $this->audit->log(auth()->id(), 'payout_fee_rule.updated', $rule, $old, $rule->fresh()?->toArray(), $request->ip());

        return back()->with('status', __('Payout fee rule saved.'));
    }

    public function destroyRule(Request $request, PayoutFeeRule $rule): RedirectResponse
    {
        $old = $rule->toArray();
        $rule->delete();
        $this->audit->log(auth()->id(), 'payout_fee_rule.deleted', null, $old, null, $request->ip());

        return back()->with('status', __('Payout fee rule removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRule(Request $request, ?PayoutFeeRule $existing = null): array
    {
        $data = $request->validate([
            'country' => ['nullable', 'string', Rule::in(array_keys((array) config('payout_methods.countries', [])))],
            'method' => ['nullable', 'string', Rule::in(array_keys((array) config('payout_methods.methods', [])))],
            'gateway_key' => ['nullable', 'string', Rule::in($this->registry->payoutOrder())],
            'flat_fee' => ['nullable', 'numeric', 'min:0'],
            'percent_fee' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'min_fee' => ['nullable', 'numeric', 'min:0'],
            'max_fee' => ['nullable', 'numeric', 'min:0'],
            'verified_provider_fee_flat' => ['nullable', 'numeric', 'min:0'],
            'verified_provider_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'verified_source' => ['nullable', Rule::in([PayoutFeeRule::SOURCE_LIVE_QUOTE, PayoutFeeRule::SOURCE_PROVIDER_INVOICE, PayoutFeeRule::SOURCE_TEST_PAYOUT])],
            'verified_reference' => ['nullable', 'string', 'max:100'],
            'min_withdrawal' => ['nullable', 'numeric', 'min:0'],
            'max_withdrawal' => ['nullable', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['country'] = ($data['country'] ?? null) ?: null;
        $data['method'] = ($data['method'] ?? null) ?: null;
        $data['gateway_key'] = ($data['gateway_key'] ?? null) ?: null;
        $data['flat_fee'] = $data['flat_fee'] ?? 0;
        $data['percent_fee'] = $data['percent_fee'] ?? 0;
        $data['active'] = (bool) ($data['active'] ?? false);

        $hasVerified = filled($data['verified_provider_fee_flat'] ?? null) || filled($data['verified_provider_fee_percent'] ?? null);
        if ($hasVerified) {
            if (blank($data['verified_source'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['verified_source' => __('Say how the provider fee was verified.')]);
            }
            if (($data['verified_source'] ?? null) === PayoutFeeRule::SOURCE_TEST_PAYOUT && blank($data['verified_reference'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['verified_reference' => __('Enter the test payout reference.')]);
            }
            $changed = ! $existing
                || (string) $existing->verified_provider_fee_flat !== (string) ($data['verified_provider_fee_flat'] ?? '')
                || (string) $existing->verified_provider_fee_percent !== (string) ($data['verified_provider_fee_percent'] ?? '')
                || $request->boolean('reverify');
            if ($changed) {
                $data['verified_at'] = now();
                $data['verified_by'] = auth()->id();
                $data['drift_note'] = null;
                $data['drift_flagged_at'] = null;
            }
        } else {
            $data['verified_source'] = null;
            $data['verified_reference'] = null;
            $data['verified_at'] = null;
            $data['verified_by'] = null;
        }

        if (filled($data['min_withdrawal'] ?? null) && $hasVerified) {
            $probe = new PayoutFeeRule([
                'verified_provider_fee_flat' => $data['verified_provider_fee_flat'] ?? null,
                'verified_provider_fee_percent' => $data['verified_provider_fee_percent'] ?? null,
            ]);
            $feeAtMin = PayoutQuoteService::verifiedFee($probe, (string) $data['min_withdrawal']);
            if (Money::cmp((string) $data['min_withdrawal'], $feeAtMin, 2) <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['min_withdrawal' => __('The minimum must be above the verified provider fee (:fee at that amount).', ['fee' => money($feeAtMin, 2)])]);
            }
        }

        return $data;
    }
}
