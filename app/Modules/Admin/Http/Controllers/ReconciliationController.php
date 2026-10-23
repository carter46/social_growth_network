<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PaymentWebhook;
use App\Models\WalletFunding;
use App\Models\Withdrawal;
use App\Modules\Wallet\Services\DepositCheckoutService;
use App\Modules\Wallet\Services\WithdrawalPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function __construct(
        private DepositCheckoutService $deposits,
        private WithdrawalPayoutService $payouts,
    ) {}

    public function index(): View
    {
        $fundings = WalletFunding::query()
            ->forActiveUsers()
            ->with('user')
            ->where('amount', '>', 0)
            ->where(function ($q) {
                $q->whereIn('status', ['processing', 'pending'])
                    ->orWhereIn('internal_status', ['processing', 'pending', DepositCheckoutService::PAID_AFTER_FAILURE]);
            })
            ->whereNotNull('provider_payment_reference')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $withdrawals = Withdrawal::query()
            ->forActiveUsers()
            ->with(['user', 'latestAttempt'])
            ->where(function ($q) {
                $q->whereIn('status', ['processing', 'approved'])
                    ->orWhereIn('internal_status', [
                        'processing',
                        'approved',
                        Withdrawal::INTERNAL_NEEDS_ADMIN_DECISION,
                        Withdrawal::INTERNAL_AWAITING_AGENT_CONFIRMATION,
                    ]);
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $webhookByRef = $this->webhooksIndexedByReference(
            $fundings->pluck('provider_payment_reference')
                ->merge($withdrawals->map(fn (Withdrawal $w) => $w->latestAttempt?->reference))
        );

        $fundingRows = $fundings->map(function (WalletFunding $f) use ($webhookByRef) {
            $webhook = $webhookByRef[(string) $f->provider_payment_reference] ?? null;

            return [
                'type' => 'funding',
                'model' => $f,
                'reference' => $f->provider_payment_reference ?: $f->reference,
                'user' => \App\Models\User::labelFor($f->user),
                'amount' => $f->amount,
                'gateway' => $f->provider ? ucfirst((string) $f->provider) : '—',
                'provider_status' => $f->provider_status,
                'ledger_status' => $f->internal_status ?: $f->status,
                'webhook' => $webhook?->status,
                'difference' => $this->fundingDifference($f, $webhook),
            ];
        });

        $withdrawalRows = $withdrawals->map(function (Withdrawal $w) use ($webhookByRef) {
            $attempt = $w->latestAttempt;
            $webhook = $attempt ? ($webhookByRef[$attempt->reference] ?? null) : null;

            return [
                'type' => 'withdrawal',
                'model' => $w,
                'reference' => $attempt?->reference ?: $w->reference,
                'user' => \App\Models\User::labelFor($w->user),
                'amount' => $w->amount,
                'gateway' => $attempt ? ucfirst((string) $attempt->gateway_key) : '—',
                'provider_status' => $attempt?->status,
                'ledger_status' => $w->internal_status ?: $w->status,
                'webhook' => $webhook?->status,
                'difference' => $this->withdrawalDifference($w, $webhook),
            ];
        });

        return view('dashboard.admin.reconciliation', [
            'rows' => $fundingRows->concat($withdrawalRows)->values(),
        ]);
    }

    public function fixFunding(WalletFunding $funding): RedirectResponse
    {
        if (! $funding->provider_payment_reference) {
            return back()->with('error', __('Cannot verify this funding.'));
        }

        try {
            $funding = $this->deposits->completeFromReturn($funding->provider_payment_reference, releaseHeld: true, adminCredit: true);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($funding->status !== 'approved' && $funding->internal_status !== 'completed') {
            return back()->with('error', __('The gateway has not confirmed this payment, so nothing was credited.'));
        }

        return back()->with('status', __('Funding verified and credited.'));
    }

    public function syncWithdrawal(Withdrawal $withdrawal): RedirectResponse
    {
        $attempt = $withdrawal->latestAttempt;
        if (! $attempt || ! $attempt->isLive()) {
            return back()->with('error', __('This withdrawal has no payout waiting for a gateway answer.'));
        }

        try {
            $status = $this->payouts->reconcileAttempt($attempt);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Payout checked: :status.', ['status' => $status]));
    }

    /**
     * @param  Collection<int, mixed>  $references
     * @return array<string, PaymentWebhook>
     */
    private function webhooksIndexedByReference(Collection $references): array
    {
        $refs = $references->filter(fn ($r) => filled($r))->map(fn ($r) => (string) $r)->unique()->values();
        if ($refs->isEmpty()) {
            return [];
        }

        $webhooks = PaymentWebhook::query()
            ->where('signature_valid', true)
            ->where(function ($q) use ($refs) {
                foreach ($refs as $ref) {
                    $q->orWhere('payload', 'like', '%'.$ref.'%');
                }
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $map = [];
        foreach ($refs as $ref) {
            $match = $webhooks->first(fn (PaymentWebhook $w) => str_contains((string) json_encode($w->payload), $ref));
            if ($match) {
                $map[$ref] = $match;
            }
        }

        return $map;
    }

    private function fundingDifference(WalletFunding $f, ?PaymentWebhook $webhook): string
    {
        if ($f->internal_status === DepositCheckoutService::PAID_AFTER_FAILURE) {
            return 'Paid after marked failed';
        }
        if ($f->created_at && $f->created_at->lt(now()->subDays(3)) && in_array($f->status, ['pending', 'processing'], true)) {
            return 'Needs review (over 3 days)';
        }

        $paid = strtoupper((string) $f->provider_status) === 'SUCCEEDED';
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
        $attempt = $w->latestAttempt;

        if ($w->needsAdminDecision()) {
            return 'Needs admin decision';
        }
        if ($w->awaitingAgentConfirmation()) {
            return 'Waiting for agent to accept new fee';
        }
        if ($attempt?->isLive() && $attempt->created_at && $attempt->created_at->lt(now()->subDays(3))) {
            return 'Needs review (over 3 days)';
        }
        if ($attempt?->status === 'unknown') {
            return 'Gateway result unknown';
        }
        if ($attempt?->status === 'succeeded' && $w->internal_status !== 'completed') {
            return 'Paid / Not Completed';
        }
        if ($attempt?->isLive() && ! $webhook) {
            return 'Processing / Webhook Missing';
        }

        return 'OK';
    }
}
