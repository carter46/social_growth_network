<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PaymentWebhook;
use App\Models\WalletFunding;
use App\Models\Withdrawal;
use App\Modules\Wallet\Payments\Contracts\PaymentRailInterface;
use App\Modules\Wallet\Payments\Monnify\MonnifyDisbursementMapper;
use App\Modules\Wallet\Services\DepositCheckoutService;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function __construct(
        private PaymentRailInterface $rail,
        private DepositCheckoutService $deposits,
        private WalletService $wallets,
    ) {}

    public function index(Request $request): View
    {
        $fundings = WalletFunding::query()
            ->with('user')
            ->where('amount', '>', 0)
            ->where(function ($q) {
                $q->whereIn('status', ['processing', 'pending'])
                    ->orWhereIn('internal_status', ['processing', 'pending']);
            })
            ->whereNotNull('provider_payment_reference')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $withdrawals = Withdrawal::query()
            ->with('user')
            ->where(function ($q) {
                $q->whereIn('status', ['processing', 'approved', 'failed'])
                    ->orWhereIn('internal_status', [
                        'processing',
                        'approved',
                        'failed',
                        Withdrawal::INTERNAL_AWAITING_PROVIDER_AUTH,
                    ]);
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $webhookByRef = $this->webhooksIndexedByReference(
            $fundings->pluck('provider_payment_reference')
                ->merge($withdrawals->pluck('provider_payout_reference'))
        );

        $fundingRows = $fundings->map(function (WalletFunding $f) use ($webhookByRef) {
            $ref = (string) ($f->provider_payment_reference ?: '');
            $webhook = $ref !== '' ? ($webhookByRef[$ref] ?? null) : null;

            return [
                'type' => 'funding',
                'model' => $f,
                'reference' => $f->provider_payment_reference ?: $f->reference,
                'user' => \App\Models\User::labelFor($f->user),
                'amount' => $f->amount,
                'monnify_status' => $f->provider_status,
                'ledger_status' => $f->internal_status ?: $f->status,
                'webhook' => $webhook?->status,
                'difference' => $this->fundingDifference($f, $webhook),
            ];
        });

        $withdrawalRows = $withdrawals->map(function (Withdrawal $w) use ($webhookByRef) {
            $ref = (string) ($w->provider_payout_reference ?: '');
            $webhook = $ref !== '' ? ($webhookByRef[$ref] ?? null) : null;

            return [
                'type' => 'withdrawal',
                'model' => $w,
                'reference' => $w->provider_payout_reference ?: $w->reference,
                'user' => \App\Models\User::labelFor($w->user),
                'amount' => $w->amount,
                'monnify_status' => $w->provider_status,
                'ledger_status' => $w->internal_status ?: $w->status,
                'webhook' => $webhook?->status,
                'difference' => $this->withdrawalDifference($w, $webhook),
            ];
        });

        return view('dashboard.admin.reconciliation', [
            'rows' => $fundingRows->concat($withdrawalRows)->values(),
            'monnifyEnabled' => $this->rail->isConfigured(),
        ]);
    }

    public function fixFunding(WalletFunding $funding): RedirectResponse
    {
        if (! $funding->provider_payment_reference || ! $this->rail->isConfigured()) {
            return back()->with('error', __('Cannot verify this funding.'));
        }

        try {
            $this->deposits->completeFromReturn($funding->provider_payment_reference, releaseHeld: true);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Funding reconciled.'));
    }

    public function syncWithdrawal(Withdrawal $withdrawal): RedirectResponse
    {
        if (! $withdrawal->provider_payout_reference || ! $this->rail->isConfigured()) {
            return back()->with('error', __('Cannot sync this withdrawal.'));
        }

        try {
            $status = $this->rail->getTransferStatus($withdrawal->provider_payout_reference);
            $st = MonnifyDisbursementMapper::status($status);
            $meta = $withdrawal->provider_meta ?? [];
            $meta['last_summary'] = MonnifyDisbursementMapper::snapshot($status);
            $withdrawal->update([
                'provider_status' => $st ?: $withdrawal->provider_status,
                'provider_meta' => $meta,
            ]);

            if (MonnifyDisbursementMapper::isSuccess($st)) {
                $this->wallets->completeWithdrawalPayout($withdrawal);
            } elseif (MonnifyDisbursementMapper::isTerminalFailure($st)) {
                $this->wallets->failWithdrawalPayout($withdrawal, 'Reconciled as '.$st);
            }
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Withdrawal synced.'));
    }

    /**
     * @param  Collection<int, mixed>  $references
     * @return array<string, PaymentWebhook>
     */
    private function webhooksIndexedByReference(Collection $references): array
    {
        $refs = $references
            ->filter(fn ($r) => filled($r))
            ->map(fn ($r) => (string) $r)
            ->unique()
            ->values();

        if ($refs->isEmpty()) {
            return [];
        }

        $webhooks = PaymentWebhook::query()
            ->where('provider', 'monnify')
            ->where(function ($q) use ($refs) {
                foreach ($refs as $ref) {
                    $q->orWhere('idempotency_key', 'like', '%'.$ref.'%');
                }
            })
            ->orderByDesc('id')
            ->get();

        $map = [];
        foreach ($refs as $ref) {
            $match = $webhooks->first(
                fn (PaymentWebhook $w) => str_contains((string) $w->idempotency_key, $ref)
            );
            if ($match) {
                $map[$ref] = $match;
            }
        }

        return $map;
    }

    private function fundingDifference(WalletFunding $f, ?PaymentWebhook $webhook): string
    {
        $paid = in_array(strtoupper((string) $f->provider_status), ['PAID', 'SUCCESS', 'COMPLETED'], true);
        $credited = $f->status === 'approved' || $f->internal_status === 'completed';

        if ($paid && ! $credited) {
            return 'Paid / Not Credited';
        }
        if ($credited && ! $webhook) {
            return 'Credited / Webhook Missing';
        }
        if ($f->status === 'processing') {
            return 'Stuck processing';
        }

        return 'OK';
    }

    private function withdrawalDifference(Withdrawal $w, ?PaymentWebhook $webhook): string
    {
        if ($w->internal_status === Withdrawal::INTERNAL_AWAITING_PROVIDER_AUTH) {
            return 'Awaiting Monnify OTP';
        }
        if ($w->internal_status === 'processing' && ! $webhook) {
            return 'Processing / Webhook Missing';
        }
        if (MonnifyDisbursementMapper::isSuccess((string) $w->provider_status) && $w->internal_status !== 'completed') {
            return 'Paid / Not Completed';
        }

        return 'OK';
    }
}
