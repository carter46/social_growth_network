<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Modules\Admin\Services\FinancialAuditLog;
use App\Modules\Wallet\Services\PayoutAccountService;
use App\Modules\Wallet\Services\WalletService;
use App\Modules\Wallet\Services\WithdrawalPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WithdrawalAdminController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private WithdrawalPayoutService $payouts,
        private PayoutAccountService $payoutAccounts,
        private FinancialAuditLog $financialAudit,
    ) {}

    public function index(): View
    {
        $withdrawals = Withdrawal::query()
            ->forActiveUsers()
            ->with(['user', 'approver', 'payoutAccount', 'latestAttempt'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('dashboard.admin.withdrawals', [
            'withdrawals' => $withdrawals,
            'balances' => $this->payouts->gatewayBalances(),
            'gatewaysConfigured' => $this->payouts->gatewaysConfigured(),
        ]);
    }

    public function show(Withdrawal $withdrawal): View
    {
        $withdrawal->load(['user', 'approver', 'timelineEvents', 'payoutAccount', 'attempts.authoriser', 'feeRule']);

        return view('dashboard.admin.withdrawals.show', [
            'withdrawal' => $withdrawal,
            'balances' => $this->payouts->gatewayBalances(),
            'gatewaysConfigured' => $this->payouts->gatewaysConfigured(),
            'routeOptions' => $this->payouts->routeOptions($withdrawal),
        ]);
    }

    public function approve(Withdrawal $withdrawal, Request $request): RedirectResponse
    {
        Log::info('admin.withdrawal.approve.request', [
            'withdrawal_id' => $withdrawal->id,
            'reference' => $withdrawal->reference,
            'admin_id' => auth()->id(),
            'status' => $withdrawal->status,
            'internal_status' => $withdrawal->internal_status,
        ]);

        try {
            $validated = $request->validate([
                'approval_note' => ['nullable', 'string', 'max:500'],
                'confirm_send' => ['required', 'accepted'],
                'gateway' => ['nullable', 'string', 'max:40'],
            ]);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first() ?: __('Confirmation is required.'));
        }

        $walletBefore = $withdrawal->wallet?->replicate();

        try {
            $this->payouts->approveAndSend(
                $withdrawal,
                (int) auth()->id(),
                $request->ip(),
                $validated['approval_note'] ?? null,
                $validated['gateway'] ?? null,
            );
        } catch (\Throwable $e) {
            report($e);
            Log::error('admin.withdrawal.approve.failed', [
                'withdrawal_id' => $withdrawal->id,
                'reference' => $withdrawal->reference,
                'admin_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);
            $withdrawal->refresh();
            if ($withdrawal->status === 'completed' || $withdrawal->internal_status === 'completed') {
                return back()->with('status', __('Withdrawal already completed.'));
            }

            return back()->with('error', $e->getMessage());
        }

        $withdrawal->refresh();

        $this->financialAudit->logMoneyAction(
            auth()->id(),
            'withdrawal.approved_and_sent',
            $withdrawal,
            $walletBefore,
            $withdrawal->wallet,
            $request->ip(),
            $request->userAgent(),
            $request->header('X-Request-Id'),
            FinancialAuditLog::sanitizeWithdrawal($withdrawal->only([
                'id', 'reference', 'amount', 'fee_amount', 'net_amount', 'status', 'internal_status', 'user_id', 'provider', 'provider_payout_reference',
            ])),
        );

        $msg = match (true) {
            $withdrawal->internal_status === 'completed' => __('Withdrawal approved and completed.'),
            $withdrawal->awaitingAgentConfirmation() => __('The provider fee went up beyond the tolerance. The agent has been asked to accept the new fee or cancel.'),
            $withdrawal->needsAdminDecision() => __('The payout was not made. Review the attempt below and retry or refund.'),
            default => __('Withdrawal approved and sent. Waiting for payout confirmation.'),
        };

        return back()->with('status', $msg);
    }

    public function reject(Withdrawal $withdrawal, Request $request): RedirectResponse
    {
        if ($withdrawal->latestAttempt?->isLive()) {
            return back()->with('error', __('A payout attempt is still in progress. Wait for the provider to confirm the result.'));
        }

        try {
            $validated = $request->validate([
                'notes' => ['required', 'string', 'min:3', 'max:1000'],
            ]);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first() ?: __('Enter a reason for rejecting this withdrawal.'));
        }

        $walletBefore = $withdrawal->wallet?->replicate();

        try {
            $this->walletService->unlockRejectedWithdrawal($withdrawal, $validated['notes']);
        } catch (\InvalidArgumentException $e) {
            if ($withdrawal->fresh()->status === 'rejected') {
                return back()->with('status', __('Withdrawal already rejected.'));
            }

            return back()->with('error', $e->getMessage());
        }

        $withdrawal->refresh();

        $this->financialAudit->logMoneyAction(
            auth()->id(),
            'withdrawal.rejected',
            $withdrawal,
            $walletBefore,
            $withdrawal->wallet,
            $request->ip(),
            $request->userAgent(),
            $request->header('X-Request-Id'),
            FinancialAuditLog::sanitizeWithdrawal($withdrawal->only(['id', 'reference', 'amount', 'status', 'user_id'])),
        );

        return back()->with('status', __('Withdrawal rejected. The full amount is back in the member\'s available balance.'));
    }

    /** Admin-authorised retry on another gateway after a confirmed recoverable or unclassified failure. */
    public function retry(Withdrawal $withdrawal, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gateway' => ['required', 'string', 'max:40'],
            'confirm_retry' => ['required', 'accepted'],
        ]);

        try {
            $attempt = $this->payouts->adminRetryViaGateway($withdrawal, $validated['gateway'], (int) auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->financialAudit->logMoneyAction(
            auth()->id(),
            'withdrawal.retry_authorised',
            $withdrawal->fresh(),
            null,
            null,
            $request->ip(),
            $request->userAgent(),
            $request->header('X-Request-Id'),
            ['attempt' => $attempt->reference, 'gateway' => $attempt->gateway_key, 'status' => $attempt->status],
        );

        return back()->with('status', __('Retry sent as :ref.', ['ref' => $attempt->reference]));
    }

    public function refund(Withdrawal $withdrawal, Request $request): RedirectResponse
    {
        $request->validate(['confirm_refund' => ['required', 'accepted'], 'notes' => ['nullable', 'string', 'max:500']]);
        $walletBefore = $withdrawal->wallet?->replicate();

        try {
            $this->payouts->refundToWallet($withdrawal, (int) auth()->id(), $request->input('notes'));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $withdrawal->refresh();
        $this->financialAudit->logMoneyAction(
            auth()->id(),
            'withdrawal.refunded',
            $withdrawal,
            $walletBefore,
            $withdrawal->wallet,
            $request->ip(),
            $request->userAgent(),
            $request->header('X-Request-Id'),
            FinancialAuditLog::sanitizeWithdrawal($withdrawal->only(['id', 'reference', 'amount', 'status', 'user_id'])),
        );

        return back()->with('status', __('Refunded in full to the member\'s wallet.'));
    }

    public function waiveBounceFee(Withdrawal $withdrawal, Request $request): RedirectResponse
    {
        $txn = $this->walletService->waiveBounceFee($withdrawal, (int) auth()->id());
        if (! $txn) {
            return back()->with('error', __('There is no bounce fee to waive.'));
        }

        $this->financialAudit->logMoneyAction(
            auth()->id(),
            'withdrawal.bounce_fee_waived',
            $withdrawal->fresh(),
            null,
            null,
            $request->ip(),
            $request->userAgent(),
            $request->header('X-Request-Id'),
            ['amount' => (string) $withdrawal->bounce_fee_amount],
        );

        return back()->with('status', __('Bounce fee waived and credited back.'));
    }

    public function rejectAccount(Withdrawal $withdrawal, Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $account = $withdrawal->payoutAccount;
        if (! $account) {
            return back()->with('error', __('This withdrawal has no payout account.'));
        }

        $this->payoutAccounts->reject($account, $validated['reason'], (int) auth()->id());

        return back()->with('status', __('Payout account rejected. Reject or refund this withdrawal next.'));
    }
}
