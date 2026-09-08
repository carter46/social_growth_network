<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Modules\Wallet\Services\WithdrawalConfirmationService;
use App\Support\MemberShell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function __construct(
        private WithdrawalConfirmationService $confirmation,
    ) {}

    public function index(): View
    {
        $withdrawals = Withdrawal::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(15);

        $hasOpen = Withdrawal::where('user_id', auth()->id())
            ->where(function ($q) {
                $q->whereIn('status', Withdrawal::OPEN_STATUSES)
                    ->orWhereIn('internal_status', Withdrawal::OPEN_INTERNAL);
            })
            ->exists();

        return view('dashboard.user.withdrawal.index', [
            'withdrawals' => $withdrawals,
            'wallet' => auth()->user()->wallet,
            'hasOpen' => $hasOpen,
            'layout' => MemberShell::layout(),
            'prefix' => MemberShell::prefix(),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        $user = auth()->user();
        $prefix = MemberShell::prefix();

        try {
            $this->confirmation->assertCanRequest($user);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route($prefix.'.withdrawal.index')->withErrors($e->errors());
        }

        return view('dashboard.user.withdrawal.create', [
            'wallet' => $user->wallet,
            'bank' => $user->activeBankAccount,
            'step' => session('withdrawal_step', 'confirm'),
            'layout' => MemberShell::layout(),
            'prefix' => $prefix,
        ]);
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
            'user_bank_account_id' => ['required', 'integer'],
        ]);

        $this->confirmation->sendOtp(
            $request->user(),
            $validated['password'],
            (float) $validated['amount'],
            (int) $validated['user_bank_account_id'],
        );

        $prefix = MemberShell::prefix();

        return redirect()
            ->route($prefix.'.withdrawal.create')
            ->with('withdrawal_step', 'otp')
            ->with('withdrawal_amount', $validated['amount'])
            ->with('status', __('A verification code was sent to your email.'));
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $withdrawal = $this->confirmation->verifyOtpAndCreate($request->user(), $validated['otp']);
        $prefix = MemberShell::prefix();

        return redirect()
            ->route($prefix.'.withdrawal.show', $withdrawal)
            ->with('status', __('Withdrawal request submitted.'));
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()
            ->route(MemberShell::prefix().'.withdrawal.create')
            ->with('error', __('Confirm your password and verify the email code to submit a withdrawal.'));
    }

    public function show(Withdrawal $withdrawal): View
    {
        if ((int) $withdrawal->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $withdrawal->load('timelineEvents');

        return view('dashboard.user.withdrawal.show', [
            'withdrawal' => $withdrawal,
            'wallet' => auth()->user()->wallet,
            'layout' => MemberShell::layout(),
            'prefix' => MemberShell::prefix(),
        ]);
    }
}
