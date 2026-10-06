<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Modules\Admin\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeesLimitsController extends Controller
{
    public function __construct(
        private AuditLogService $audit,
    ) {}

    public function index(): View
    {
        return view('dashboard.admin.fees-limits', [
            'withdrawalMinAmount' => SystemSetting::get('withdrawal_min_amount', '100'),
            'withdrawalMaxAmount' => SystemSetting::get('withdrawal_max_amount', '1000000'),
            'depositMinAmount' => SystemSetting::get('deposit_min_amount', '100'),
            'depositMaxAmount' => SystemSetting::get('deposit_max_amount', '1000000'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'withdrawal_min_amount' => ['required', 'numeric', 'min:1'],
            'withdrawal_max_amount' => ['required', 'numeric', 'min:1', 'gte:withdrawal_min_amount'],
            'deposit_min_amount' => ['required', 'numeric', 'min:1'],
            'deposit_max_amount' => ['required', 'numeric', 'min:1', 'gte:deposit_min_amount'],
        ], [
            'withdrawal_max_amount.gte' => 'Maximum withdrawal must be at least the minimum withdrawal.',
            'deposit_max_amount.gte' => 'Maximum deposit must be at least the minimum deposit.',
        ]);

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
}
