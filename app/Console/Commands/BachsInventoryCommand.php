<?php

namespace App\Console\Commands;

use App\Models\PayoutAccount;
use App\Models\PayoutConnection;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use Illuminate\Console\Command;

/**
 * Read-only list of every Bachs account id the app knows about: the per-agent connected
 * account and any account ids stored on payout accounts by the earlier per-account design.
 * With --live it also reads each account's capability status and USD balance from Bachs.
 * Nothing is created, moved or changed.
 */
class BachsInventoryCommand extends Command
{
    protected $signature = 'payments:bachs-inventory {--live : Also read capability status and balance from Bachs}';

    protected $description = 'List known Bachs account ids per agent (read-only)';

    public function handle(BachsConnectService $connect): int
    {
        $rows = [];

        PayoutConnection::query()->where('provider', BachsConnectService::PROVIDER)->whereNotNull('account_id')
            ->orderBy('user_id')->each(function (PayoutConnection $c) use (&$rows) {
                $rows[(string) $c->account_id] = [
                    'user_id' => (int) $c->user_id,
                    'account' => (string) $c->account_id,
                    'source' => 'connected account',
                    'local_status' => (string) $c->status,
                    'payout_accounts' => 0,
                ];
            });

        PayoutAccount::withTrashed()->whereNotNull('provider_beneficiary_ids')->orderBy('user_id')
            ->each(function (PayoutAccount $account) use (&$rows) {
                $id = (string) data_get($account->provider_beneficiary_ids, BachsConnectService::PROVIDER.'.account', '');
                if ($id === '') {
                    return;
                }
                $rows[$id] ??= [
                    'user_id' => (int) $account->user_id,
                    'account' => $id,
                    'source' => 'payout account (legacy)',
                    'local_status' => '-',
                    'payout_accounts' => 0,
                ];
                $rows[$id]['payout_accounts']++;
            });

        if ($rows === []) {
            $this->info('No Bachs account ids are stored.');

            return self::SUCCESS;
        }

        $live = (bool) $this->option('live');
        if ($live && ! $connect->available()) {
            $this->warn('Bachs is not configured, so --live was skipped.');
            $live = false;
        }

        $perUser = [];
        foreach ($rows as $id => $row) {
            $perUser[$row['user_id']][] = $id;
            if ($live) {
                try {
                    $caps = $connect->driver()->accountCapabilities($id);
                    $rows[$id]['transfers'] = $caps['transfers'] ?? '-';
                    $rows[$id]['payouts'] = $caps['payouts'] ?? '-';
                    $rows[$id]['usd_balance'] = $connect->driver()->accountBalance($id, 'USD') ?? '-';
                } catch (GatewayException $e) {
                    $rows[$id]['transfers'] = $rows[$id]['payouts'] = $rows[$id]['usd_balance'] = 'error '.($e->providerCode ?? $e->httpStatus ?? '');
                }
            }
        }

        $headers = ['User', 'Bachs account', 'Source', 'Local status', 'Payout accounts'];
        if ($live) {
            array_push($headers, 'Transfers', 'Payouts', 'USD balance');
        }
        $this->table($headers, array_map(fn (array $r) => array_values($r), array_values($rows)));

        $multi = array_filter($perUser, fn (array $ids) => count($ids) > 1);
        $this->line(count($rows).' Bachs account id(s) across '.count($perUser).' agent(s).');
        if ($multi !== []) {
            $this->warn(count($multi).' agent(s) have more than one Bachs account: user '.implode(', ', array_keys($multi)).'. Review these before choosing which account to keep.');
        }

        return self::SUCCESS;
    }
}
