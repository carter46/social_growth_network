<?php

namespace App\Console\Commands;

use App\Models\Withdrawal;
use App\Models\WalletFunding;
use App\Modules\Wallet\Payments\Monnify\MonnifyDisbursementMapper;
use App\Modules\Wallet\Payments\PayoutGateway;
use App\Modules\Wallet\Services\DepositCheckoutService;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Console\Command;

class ReconcileMonnifyPaymentsCommand extends Command
{
    protected $signature = 'monnify:reconcile {--minutes=30 : Age threshold for stuck records}';

    protected $description = 'Poll Monnify for stuck fundings and withdrawals';

    public function handle(
        PayoutGateway $gateway,
        DepositCheckoutService $deposits,
        WalletService $wallets,
    ): int {
        $rail = $gateway->rail();

        if (! $rail->isConfigured()) {
            $this->warn('Monnify not configured.');

            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes((int) $this->option('minutes'));

        $fundings = WalletFunding::query()
            ->whereNotNull('provider_payment_reference')
            ->whereIn('status', ['pending', 'processing'])
            ->where(fn ($q) => $q->whereNull('internal_status')
                ->orWhere('internal_status', '!=', DepositCheckoutService::HELD_OVER_LIMIT))
            ->where('created_at', '<', $cutoff)
            ->limit(50)
            ->get();

        foreach ($fundings as $funding) {
            try {
                $deposits->completeFromReturn($funding->provider_payment_reference);
                $this->line('Funding '.$funding->reference.' checked');
            } catch (\Throwable $e) {
                $this->error('Funding '.$funding->id.': '.$e->getMessage());
            }
        }

        $withdrawals = Withdrawal::query()
            ->whereNotNull('provider_payout_reference')
            ->where(function ($q) {
                $q->whereIn('status', ['processing', 'approved'])
                    ->orWhere('internal_status', Withdrawal::INTERNAL_AWAITING_PROVIDER_AUTH);
            })
            ->where('updated_at', '<', $cutoff)
            ->limit(50)
            ->get();

        foreach ($withdrawals as $withdrawal) {
            try {
                $status = $rail->getTransferStatus($withdrawal->provider_payout_reference);
                $st = MonnifyDisbursementMapper::status($status);
                $meta = $withdrawal->provider_meta ?? [];
                $meta['last_summary'] = MonnifyDisbursementMapper::snapshot($status);
                $withdrawal->update([
                    'provider_status' => $st ?: $withdrawal->provider_status,
                    'provider_meta' => $meta,
                ]);

                if (MonnifyDisbursementMapper::isSuccess($st)) {
                    $wallets->completeWithdrawalPayout($withdrawal);
                } elseif ($st === 'EXPIRED') {
                    $wallets->failWithdrawalPayout($withdrawal, 'Reconcile: Monnify authorization expired');
                } elseif (MonnifyDisbursementMapper::isTerminalFailure($st)) {
                    $wallets->failWithdrawalPayout($withdrawal, 'Reconcile: '.$st);
                }

                $this->line('Withdrawal '.$withdrawal->reference.' → '.$st);
            } catch (\Throwable $e) {
                $this->error('Withdrawal '.$withdrawal->id.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
