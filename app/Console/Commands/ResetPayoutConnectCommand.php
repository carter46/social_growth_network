<?php

namespace App\Console\Commands;

use App\Models\PayoutConnection;
use App\Models\User;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use Illuminate\Console\Command;

/**
 * Clear Bachs Connect rows so agents can re-onboard after a sandbox → live cutover (or the reverse).
 */
class ResetPayoutConnectCommand extends Command
{
    protected $signature = 'payments:reset-connect
        {--user= : User id or email to reset}
        {--mismatched : Only connections that do not match the current Bachs environment}
        {--force : Required when resetting without --user}';

    protected $description = 'Reset Bachs Connect accounts so agents can reconnect (profile still autofills)';

    public function handle(BachsConnectService $connect): int
    {
        $userOpt = $this->option('user');
        $mismatchedOnly = (bool) $this->option('mismatched');

        if ($userOpt) {
            $user = is_numeric($userOpt)
                ? User::query()->find((int) $userOpt)
                : User::query()->where('email', $userOpt)->first();

            if (! $user) {
                $this->error('User not found.');

                return self::FAILURE;
            }

            $connection = PayoutConnection::for((int) $user->id, BachsConnectService::PROVIDER);
            if (! $connection || blank($connection->account_id)) {
                $this->info('No Connect account to reset for '.$user->email.'.');

                return self::SUCCESS;
            }

            if ($mismatchedOnly && $connection->matchesEnvironment($connect->currentEnvironment())) {
                $this->info($user->email.' already matches '.$connect->currentEnvironment().'.');

                return self::SUCCESS;
            }

            $connect->invalidate($connection, 'Admin reset via payments:reset-connect.');
            $this->info('Cleared Connect for '.$user->email.'. They can reconnect on '.$connect->currentEnvironment().'.');

            return self::SUCCESS;
        }

        if (! $mismatchedOnly) {
            $this->error('Pass --user=… or --mismatched (with --force).');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Clear all Bachs Connect accounts that do not match '.$connect->currentEnvironment().'?')) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $cleared = $connect->invalidateMismatched();
        $this->info('Cleared '.$cleared.' Connect account(s).');

        return self::SUCCESS;
    }
}
