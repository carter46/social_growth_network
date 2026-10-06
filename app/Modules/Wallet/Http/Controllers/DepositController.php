<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\WalletFunding;
use App\Modules\Wallet\Services\DepositCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepositController extends Controller
{
    public function __construct(
        private DepositCheckoutService $checkout,
    ) {}

    private function rejectAgents(): void
    {
        abort_if(auth()->user()?->hasRole('agent'), 403, 'Agents cannot deposit funds.');
    }

    public function index(): View
    {
        $this->rejectAgents();

        $user = auth()->user();
        $fundings = WalletFunding::where('user_id', $user->id)
            ->where('amount', '>', 0)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('dashboard.user.deposit.index', [
            'fundings' => $fundings,
            'wallet' => $user->wallet,
            'gatewayEnabled' => $this->checkout->gatewayEnabled(),
        ]);
    }

    public function createCheckout(): View|RedirectResponse
    {
        $this->rejectAgents();

        try {
            $this->checkout->assertDepositKyc(auth()->user());
        } catch (\Throwable $e) {
            return redirect()->route('dashboard.deposit.index')->with('error', $e->getMessage());
        }

        return view('dashboard.user.deposit.checkout', [
            'wallet' => auth()->user()->wallet,
            'depositMin' => (float) SystemSetting::get('deposit_min_amount', 100),
            'reservedAllowed' => $this->checkout->reservedAccountsAllowed(auth()->user()),
            'gatewayEnabled' => $this->checkout->gatewayEnabled(),
        ]);
    }

    public function storeCheckout(Request $request): RedirectResponse
    {
        $this->rejectAgents();

        $depositMin = (float) SystemSetting::get('deposit_min_amount', 100);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$depositMin],
        ]);

        try {
            $funding = $this->checkout->startCheckout(
                $request->user(),
                (float) $validated['amount'],
                route('dashboard.deposit.callback'),
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->away($funding->checkout_url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->rejectAgents();

        $paymentReference = (string) $request->query('paymentReference', $request->query('paymentReference', ''));
        if ($paymentReference === '') {
            $paymentReference = (string) $request->query('payment_reference', '');
        }

        if ($paymentReference === '') {
            return redirect()->route('dashboard.deposit.index')
                ->with('error', __('Missing payment reference.'));
        }

        try {
            $funding = $this->checkout->completeFromReturn($paymentReference);
        } catch (\Throwable $e) {
            return redirect()->route('dashboard.deposit.index')
                ->with('error', $e->getMessage());
        }

        if ($funding->internal_status === 'completed' || $funding->status === 'approved') {
            return redirect()->route('dashboard.deposit.show', $funding)
                ->with('status', __('Payment confirmed. Wallet credited.'));
        }

        $providerStatus = strtoupper((string) $funding->provider_status);

        if (in_array($providerStatus, ['FAILED', 'CANCELLED', 'ABANDONED', 'EXPIRED', 'REVERSED'], true)) {
            return redirect()->route('dashboard.deposit.show', $funding)
                ->with('error', __('Payment was not completed. Your wallet has not been credited.'));
        }

        return redirect()->route('dashboard.deposit.show', $funding)
            ->with('info', __('Payment not confirmed yet. If you did not finish paying, tap Continue payment. If you already paid, your wallet will be credited once the payment is confirmed.'));
    }

    public function show(WalletFunding $funding): View|RedirectResponse
    {
        $this->rejectAgents();

        if ((int) $funding->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $funding->load('timelineEvents');

        return view('dashboard.user.deposit.show', [
            'funding' => $funding,
            'wallet' => auth()->user()->wallet,
        ]);
    }

    public function reservedAccount(Request $request): RedirectResponse|View
    {
        $this->rejectAgents();

        try {
            $account = $this->checkout->ensureReservedAccount($request->user());
        } catch (\Throwable $e) {
            return redirect()->route('dashboard.deposit.create-checkout')
                ->with('error', $e->getMessage());
        }

        return view('dashboard.user.deposit.reserved', [
            'account' => $account,
            'wallet' => $request->user()->wallet,
        ]);
    }
}
