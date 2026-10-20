<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdrawal;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use App\Modules\Wallet\Payments\PayoutGate;
use App\Modules\Wallet\Services\PayoutAccountService;
use App\Modules\Wallet\Services\WithdrawalConfirmationService;
use App\Modules\Wallet\Services\WithdrawalFeeService;
use App\Modules\Wallet\Services\WithdrawalPayoutService;
use App\Support\MemberShell;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function __construct(
        private WithdrawalConfirmationService $confirmation,
        private WithdrawalFeeService $fees,
        private WithdrawalPayoutService $payouts,
        private BachsConnectService $connect,
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
        $account = $user->primaryPayoutAccount;
        $limits = $account
            ? $this->fees->limits($account->country, $account->method, null)
            : ['min' => WithdrawalFeeService::globalMin(), 'max' => WithdrawalFeeService::globalMax()];

        return view('dashboard.user.withdrawal.create', [
            'wallet' => $user->wallet,
            'account' => $account,
            'step' => session('withdrawal_step', 'confirm'),
            'blocker' => $this->withdrawalBlocker($user, $prefix, (string) $limits['min']),
            'withdrawalMin' => (float) $limits['min'],
            'withdrawalMax' => (float) $limits['max'],
            'bounceFee' => PayoutAccountService::bounceFee(),
            'breakdown' => session('withdrawal_breakdown'),
            'layout' => MemberShell::layout(),
            'prefix' => $prefix,
        ]);
    }

    /**
     * Why this member cannot request a withdrawal right now, with the next step to fix it.
     *
     * @return array{title: string, message: string, action: ?array{label: string, href: string}}|null
     */
    private function withdrawalBlocker(User $user, string $prefix, string $withdrawalMin): ?array
    {
        $link = fn (string $route, string $label) => Route::has($route)
            ? ['label' => $label, 'href' => route($route)]
            : null;

        try {
            $this->confirmation->assertCanRequest($user);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $message = (string) collect($errors)->flatten()->first();

            $bankAction = $prefix === 'agent' && $this->connect->available() && Route::has('agent.wallet')
                ? $link('agent.wallet', __('Connect bank account'))
                : $link($prefix.'.banks.index', __('Payout accounts'));

            return match (array_key_first($errors)) {
                'password' => ['title' => __('Set a password first'), 'message' => $message, 'action' => $link($prefix.'.account.security', __('Go to security'))],
                'bank', 'terms' => ['title' => __('Check your payout account'), 'message' => $message, 'action' => $bankAction],
                default => str_contains($message, 'KYC')
                    ? ['title' => __('Verify your identity'), 'message' => $message, 'action' => $link($prefix.'.account.kyc', __('Complete KYC'))]
                    : ['title' => __('Withdrawal in progress'), 'message' => $message, 'action' => $link($prefix.'.withdrawal.index', __('View withdrawals'))],
            };
        }

        $available = $user->wallet?->availableBalance() ?? '0';
        $withdrawable = $user->wallet?->withdrawableBalance() ?? '0.00';
        if (bccomp($withdrawable, Money::normalize($withdrawalMin), 4) < 0) {
            return [
                'title' => __('Insufficient balance'),
                'message' => __('Your available balance is :available. The minimum withdrawal to your primary account is :min. Complete more tasks to earn rewards, then come back to withdraw.', [
                    'available' => money($available),
                    'min' => money($withdrawalMin, 2, 2),
                ]),
                'action' => $link($prefix.'.marketplace', __('Find tasks')),
            ];
        }

        return null;
    }

    /** Live fee breakdown as the agent types (JSON). */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
        ]);

        try {
            $breakdown = $this->confirmation->quoteFor($request->user(), (string) $validated['amount']);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        $quote = (array) $breakdown['quote'];

        return response()->json([
            'amount' => money($breakdown['amount'], 2),
            'provider_fee' => money($breakdown['provider_fee'], 2),
            'platform_fee' => bccomp($breakdown['platform_fee'], '0', 2) > 0 ? money($breakdown['platform_fee'], 2) : null,
            'net' => money($breakdown['net'], 2),
            'fx_rate' => $quote['fx_rate'] ?? null,
            'destination_amount' => isset($quote['destination_amount']) ? number_format((float) $quote['destination_amount'], 2) : null,
            'destination_currency' => $quote['destination_currency'] ?? null,
            'quoted_at' => isset($quote['quoted_at']) ? \Carbon\Carbon::parse($quote['quoted_at'])->format('H:i') : null,
            'high_fee' => $breakdown['high_fee'],
        ]);
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'payout_account_id' => ['required', 'integer'],
        ]);

        $this->confirmation->sendOtp(
            $request->user(),
            $validated['password'],
            (string) $validated['amount'],
            (int) $validated['payout_account_id'],
        );

        $breakdown = $this->confirmation->quoteFor($request->user(), (string) $validated['amount']);

        return redirect()
            ->route(MemberShell::prefix().'.withdrawal.create')
            ->with('withdrawal_step', 'otp')
            ->with('withdrawal_amount', $validated['amount'])
            ->with('withdrawal_breakdown', $breakdown)
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
            ->with('status', PayoutGate::isOpen()
                ? __('Withdrawal request submitted.')
                : __('Withdrawal request submitted. Payouts are paused for maintenance, so it will be sent once they resume. Your funds stay reserved.'));
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

    public function acceptFee(Withdrawal $withdrawal): RedirectResponse
    {
        abort_unless((int) $withdrawal->user_id === (int) auth()->id(), 403);

        try {
            $this->payouts->agentAcceptFee($withdrawal);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('New fee accepted. Your withdrawal is back in the queue.'));
    }

    public function cancel(Withdrawal $withdrawal): RedirectResponse
    {
        abort_unless((int) $withdrawal->user_id === (int) auth()->id(), 403);

        try {
            $this->payouts->agentCancel($withdrawal);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Withdrawal cancelled. The full amount is back in your balance.'));
    }
}
