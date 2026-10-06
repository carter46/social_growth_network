<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\WalletFunding;
use App\Modules\Admin\Services\AuditLogService;
use App\Modules\Admin\Services\FinancialAuditLog;
use App\Modules\Wallet\Payments\Contracts\PaymentRailInterface;
use App\Modules\Wallet\Services\DepositCheckoutService;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WalletFundingController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private AuditLogService $audit,
        private FinancialAuditLog $financialAudit,
        private DepositCheckoutService $deposits,
        private PaymentRailInterface $rail,
    ) {}

    public function index(): View
    {
        $fundings = WalletFunding::with(['user', 'wallet'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('dashboard.admin.fundings', compact('fundings'));
    }

    public function approve(WalletFunding $funding, Request $request): RedirectResponse
    {
        if ($funding->method === 'bank') {
            return back()->with('error', __('Manual bank wallet deposits are no longer accepted. Use gateway funding or confirm an order payment under Orders.'));
        }

        if ($funding->status === 'approved' || $funding->internal_status === 'completed') {
            return back()->with('status', __('Deposit already approved.'));
        }

        // Wallets are only credited for payments the gateway confirms; admins cannot credit unpaid deposits.
        if (! $funding->provider_payment_reference || ! $this->rail->isConfigured()) {
            return back()->with('error', __('This deposit cannot be checked with the payment gateway, so the wallet was not credited.'));
        }

        $walletBefore = $funding->wallet?->replicate();

        try {
            $funding = $this->deposits->completeFromReturn($funding->provider_payment_reference, releaseHeld: true);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($funding->status !== 'approved' && $funding->internal_status !== 'completed') {
            return back()->with('error', __(':gateway has not confirmed this payment (status: :status). The wallet was not credited.', [
                'gateway' => $this->rail->displayName(),
                'status' => $funding->provider_status ?: 'unknown',
            ]));
        }

        $funding->refresh();
        $walletAfter = $funding->wallet;

        $this->financialAudit->logMoneyAction(
            auth()->id(),
            'funding.approved',
            $funding,
            $walletBefore,
            $walletAfter,
            $request->ip(),
            $request->userAgent(),
            $request->header('X-Request-Id'),
        );

        return back()->with('status', __('Payment confirmed by :gateway. Wallet credited.', ['gateway' => $this->rail->displayName()]));
    }

    public function reject(WalletFunding $funding, Request $request): RedirectResponse
    {
        if ($funding->status !== 'pending') {
            return back()->with('error', __('Only pending deposits can be rejected.'));
        }

        $funding->update([
            'status' => 'rejected',
            'admin_notes' => $request->input('notes'),
        ]);

        $this->audit->log(auth()->id(), 'funding.rejected', $funding, null, $funding->toArray(), $request->ip());

        return back()->with('status', __('Deposit rejected.'));
    }

    public function reverse(WalletFunding $funding, Request $request): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        if ($funding->status !== 'approved') {
            return back()->with('error', __('Only approved deposits can be reversed.'));
        }

        if ($funding->status === 'reversed') {
            return back()->with('status', __('Deposit already reversed.'));
        }

        $transaction = Transaction::query()
            ->where('wallet_funding_id', $funding->id)
            ->where('type', TransactionType::Funding->value)
            ->whereNull('reverses_transaction_id')
            ->first();

        if (! $transaction) {
            return back()->with('error', __('No ledger entry found for this deposit.'));
        }

        if (Transaction::query()->where('reverses_transaction_id', $transaction->id)->exists()) {
            return back()->with('status', __('Deposit already reversed.'));
        }

        $walletBefore = $funding->wallet?->replicate();

        try {
            $this->walletService->reverseTransaction(
                $transaction,
                $request->input('reason'),
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $funding->refresh();
        $walletAfter = $funding->wallet;

        $this->financialAudit->logMoneyAction(
            auth()->id(),
            'funding.reversed',
            $funding,
            $walletBefore,
            $walletAfter,
            $request->ip(),
            $request->userAgent(),
            $request->header('X-Request-Id'),
        );

        return back()->with('status', __('Deposit reversed. A corrective ledger entry was created.'));
    }

    public function downloadProof(WalletFunding $funding): StreamedResponse|RedirectResponse
    {
        $path = $funding->metadata['proof_path'] ?? null;
        $disk = $funding->metadata['proof_disk']
            ?? config('media.documents.disk', 'local');

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            return back()->with('error', __('Deposit proof not found.'));
        }

        return Storage::disk($disk)->download($path, 'deposit-proof-'.$funding->reference);
    }
}
