<?php

namespace App\Console\Commands;

use App\Models\PayoutAccount;
use App\Models\PayoutConnection;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use App\Services\Notifications\AdminPaymentAlertNotifier;
use App\Support\Money\Money;
use Illuminate\Console\Command;

/**
 * Recipient accounts holding platform funds with no payout in progress (a funding transfer
 * landed late, or a failed payout returned money after the reclaim). Report only by default;
 * --sweep moves the balances back after an admin confirms each one.
 */
class BachsStrandedBalancesCommand extends Command
{
    protected $signature = 'payments:bachs-stranded
        {--sweep : Sweep balances back to the platform after confirming each one}
        {--alert : Notify admins when stranded balances are found}';

    protected $description = 'Report (and optionally sweep) USD left on Bachs recipient accounts';

    public function handle(BachsConnectService $connect, AdminPaymentAlertNotifier $alerts): int
    {
        if (! $connect->available()) {
            $this->warn('Bachs is not configured. Nothing to check.');

            return self::SUCCESS;
        }
        $driver = $connect->driver();

        $stranded = [];
        foreach ($this->accounts() as $accountId => $userId) {
            if ($this->hasLivePayout($userId)) {
                continue;
            }
            try {
                $balance = $driver->accountBalance($accountId, 'USD') ?? '0';
            } catch (GatewayException $e) {
                $this->warn('User #'.$userId.' ('.$accountId.'): balance check failed ('.($e->providerCode ?? $e->httpStatus ?? 'error').')');

                continue;
            }
            if (Money::isPositive($balance, 2)) {
                $stranded[$accountId] = ['user_id' => $userId, 'balance' => Money::round($balance, 2)];
            }
        }

        if ($stranded === []) {
            $this->info('No stranded balances.');

            return self::SUCCESS;
        }

        $this->table(['User', 'Bachs account', 'USD balance'], array_map(
            fn (string $id, array $row) => [$row['user_id'], $id, $row['balance']],
            array_keys($stranded),
            $stranded,
        ));

        if ($this->option('alert')) {
            $alerts->strandedBalances(array_map(
                fn (string $id, array $row) => 'user #'.$row['user_id'].' $'.$row['balance'],
                array_keys($stranded),
                $stranded,
            ));
        }

        if (! $this->option('sweep')) {
            $this->line('Run with --sweep to move these balances back after confirming each one.');

            return self::SUCCESS;
        }

        foreach ($stranded as $accountId => $row) {
            if (! $this->confirm('Sweep $'.$row['balance'].' from user #'.$row['user_id'].' ('.$accountId.') back to the platform?')) {
                continue;
            }
            // Re-check right before moving money: a payout may have started since the report.
            if ($this->hasLivePayout($row['user_id'])) {
                $this->warn('Skipped: a payout for user #'.$row['user_id'].' started in the meantime.');

                continue;
            }
            $ok = $driver->sweepAccount($accountId, $accountId.'-sweep-'.now()->format('Y-m-d'));
            $this->line($ok ? 'Swept '.$accountId.'.' : 'Sweep failed for '.$accountId.'. Check the logs and try again.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, int> account id => user id */
    private function accounts(): array
    {
        $accounts = PayoutConnection::query()
            ->where('provider', BachsConnectService::PROVIDER)
            ->whereNotNull('account_id')
            ->pluck('user_id', 'account_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        PayoutAccount::withTrashed()->whereNotNull('provider_beneficiary_ids')->get(['user_id', 'provider_beneficiary_ids'])
            ->each(function (PayoutAccount $account) use (&$accounts) {
                $id = (string) data_get($account->provider_beneficiary_ids, BachsConnectService::PROVIDER.'.account', '');
                if ($id !== '') {
                    $accounts[$id] ??= (int) $account->user_id;
                }
            });

        return $accounts;
    }

    private function hasLivePayout(int $userId): bool
    {
        return WithdrawalPayoutAttempt::query()
            ->where('gateway_key', BachsConnectService::PROVIDER)
            ->whereNotNull('live_flag')
            ->whereHas('withdrawal', fn ($q) => $q->where('user_id', $userId))
            ->exists();
    }
}
