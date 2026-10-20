<?php

namespace App\Console\Commands;

use App\Events\WalletDepositAbandoned;
use App\Models\User;
use App\Models\WalletFunding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class RemindAbandonedDepositsCommand extends Command
{
    protected $signature = 'payments:remind-abandoned-deposits
                            {--hours=2 : Age in hours before a pending deposit is treated as abandoned}';

    protected $description = 'Email users who started a wallet deposit but never finished payment';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);
        $sent = 0;

        WalletFunding::query()
            ->whereIn('status', ['pending', 'processing'])
            ->where('created_at', '<=', $cutoff)
            ->whereHas('user', fn ($q) => $q->notAnonymized())
            ->orderBy('id')
            ->each(function (WalletFunding $funding) use (&$sent) {
                $user = User::query()->find($funding->user_id);
                if (! $user || $user->trashed() || $user->isAnonymized()) {
                    return;
                }

                $resume = null;
                if (filled($funding->checkout_url) && ! $funding->isCheckoutExpired()) {
                    $resume = (string) $funding->checkout_url;
                } elseif (Route::has('dashboard.deposit.show')) {
                    $resume = route('dashboard.deposit.show', $funding);
                } elseif (Route::has('dashboard.deposit.create-checkout')) {
                    $resume = route('dashboard.deposit.create-checkout');
                }

                WalletDepositAbandoned::dispatch(
                    (int) $funding->user_id,
                    (int) $funding->id,
                    (float) $funding->amount,
                    $resume,
                    (string) $funding->currency,
                );
                $sent++;
            });

        $this->info("Queued {$sent} abandoned deposit reminder(s).");

        return self::SUCCESS;
    }
}
