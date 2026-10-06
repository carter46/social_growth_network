<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Modules\Wallet\Services\WithdrawalConfirmationService;
use App\Support\MemberShell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
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
        $withdrawalMin = (float) SystemSetting::get('withdrawal_min_amount', 100);

        return view('dashboard.user.withdrawal.create', [
            'wallet' => $user->wallet,
            'bank' => $user->activeBankAccount,
            'step' => session('withdrawal_step', 'confirm'),
            'blocker' => $this->withdrawalBlocker($user, $prefix, $withdrawalMin),
            'withdrawalMin' => $withdrawalMin,
            'withdrawalMax' => (float) SystemSetting::get('withdrawal_max_amount', 1000000),
            'layout' => MemberShell::layout(),
            'prefix' => $prefix,
        ]);
    }

    /**
     * Why this member cannot request a withdrawal right now, with the next step to fix it.
     *
     * @return array{title: string, message: string, action: ?array{label: string, href: string}}|null
     */
    private function withdrawalBlocker(User $user, string $prefix, float $withdrawalMin): ?array
    {
        $link = fn (string $route, string $label) => Route::has($route)
            ? ['label' => $label, 'href' => route($route)]
            : null;

        try {
            $this->confirmation->assertCanRequest($user);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $message = (string) collect($errors)->flatten()->first();

            return match (array_key_first($errors)) {
                'password' => ['title' => __('Set a password first'), 'message' => $message, 'action' => $link($prefix.'.account.security', __('Go to security'))],
                'bank' => ['title' => __('Add a bank account'), 'message' => $message, 'action' => $link($prefix.'.banks.index', __('Add bank account'))],
                default => str_contains($message, 'KYC')
                    ? ['title' => __('Verify your identity'), 'message' => $message, 'action' => $link($prefix.'.account.kyc', __('Complete KYC'))]
                    : ['title' => __('Withdrawal in progress'), 'message' => $message, 'action' => $link($prefix.'.withdrawal.index', __('View withdrawals'))],
            };
        }

        $available = (float) ($user->wallet?->availableBalance() ?? 0);
        if ($available < $withdrawalMin) {
            return [
                'title' => __('Insufficient balance'),
                'message' => __('Your available balance is ₦:available. The minimum withdrawal is ₦:min. Complete more tasks to earn rewards, then come back to withdraw.', [
                    'available' => number_format($available, 2),
                    'min' => number_format($withdrawalMin, 2),
                ]),
                'action' => $link($prefix.'.marketplace', __('Find tasks')),
            ];
        }

        return null;
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
