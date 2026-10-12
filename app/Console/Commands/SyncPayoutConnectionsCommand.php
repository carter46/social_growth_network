<?php

namespace App\Console\Commands;

use App\Models\PayoutConnection;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use Illuminate\Console\Command;

/**
 * Re-reads agent Bachs accounts that are not ready yet (or not checked recently), so a
 * missed Connect webhook cannot leave an agent stuck.
 */
class SyncPayoutConnectionsCommand extends Command
{
    protected $signature = 'payments:sync-connect
        {--user= : Only this user id}
        {--limit=100 : Maximum accounts per run}';

    protected $description = 'Refresh Bachs payout account readiness from the Bachs API';

    public function handle(BachsConnectService $connect): int
    {
        if (! $connect->available()) {
            $this->warn('Bachs is not configured. Nothing to sync.');

            return self::SUCCESS;
        }

        $query = PayoutConnection::query()
            ->where('provider', BachsConnectService::PROVIDER)
            ->whereNotNull('account_id');

        if ($this->option('user')) {
            $query->where('user_id', (int) $this->option('user'));
        } else {
            $query->where(function ($q) {
                $q->where('status', '!=', PayoutConnection::ACTIVE)
                    ->orWhereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subDay());
            })->where('status', '!=', PayoutConnection::REJECTED);
        }

        $synced = 0;
        $changed = 0;
        $failed = 0;

        foreach ($query->orderByRaw('last_synced_at IS NOT NULL')->orderBy('last_synced_at')->limit(max(1, (int) $this->option('limit')))->get() as $connection) {
            $before = $connection->status;
            try {
                $after = $connect->sync($connection)->status;
                $synced++;
                if ($after !== $before) {
                    $changed++;
                    $this->line('User #'.$connection->user_id.': '.$before.' to '.$after);
                }
            } catch (GatewayException $e) {
                $failed++;
                $this->warn('User #'.$connection->user_id.': sync failed ('.($e->providerCode ?? 'error').')');
            }
        }

        $this->info($synced.' synced, '.$changed.' changed, '.$failed.' failed.');

        return self::SUCCESS;
    }
}
