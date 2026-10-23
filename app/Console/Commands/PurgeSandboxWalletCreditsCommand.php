<?php

namespace App\Console\Commands;

use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFunding;
use App\Modules\Wallet\Services\WalletService;
use App\Support\Money\Money;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Claw back site-wallet credits that came from Bachs/Flutterwave sandbox testing.
 *
 * Site balances live in our DB — switching the gateway to live does not clear them.
 * Default is dry-run. Pass --execute to write ledger reversals / adjustments.
 */
class PurgeSandboxWalletCreditsCommand extends Command
{
    protected $signature = 'wallets:purge-sandbox-credits
        {--before= : Cutover datetime (Y-m-d or ISO). Approved deposits at/before this are treated as sandbox}
        {--user= : Limit to one user id or email}
        {--execute : Apply changes (default is dry-run)}
        {--zero-available : After reversing, zero remaining available balance on wallets that had sandbox deposits}
        {--admin= : Admin user id for ledger attribution}';

    protected $description = 'Dry-run (or execute) clawback of sandbox-era wallet deposits before go-live';

    public function handle(WalletService $wallets): int
    {
        $beforeRaw = (string) $this->option('before');
        if ($beforeRaw === '') {
            $this->error('Pass --before=YYYY-MM-DD (the moment you switched Bachs to live).');

            return self::FAILURE;
        }

        try {
            $before = Carbon::parse($beforeRaw)->endOfDay();
        } catch (\Throwable) {
            $this->error('Could not parse --before. Use Y-m-d or an ISO datetime.');

            return self::FAILURE;
        }

        $admin = $this->resolveAdmin();
        if (! $admin) {
            $this->error('No admin user found. Pass --admin=<id>.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $zeroAvailable = (bool) $this->option('zero-available');
        try {
            $userFilter = $this->resolveUserId();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $fundings = $this->candidateFundings($before, $userFilter);
        $this->info(($execute ? 'EXECUTE' : 'DRY RUN').' — sandbox deposits at/before '.$before->toDateTimeString());
        $this->line('Admin attribution: #'.$admin->id.' ('.$admin->email.')');
        if ($userFilter) {
            $this->line('Limited to user #'.$userFilter);
        }

        if ($fundings->isEmpty()) {
            $this->warn('No approved sandbox-era deposits matched.');
            if ($zeroAvailable && $userFilter) {
                return $this->zeroWalletAvailable($wallets, $userFilter, $admin, $execute);
            }

            return self::SUCCESS;
        }

        $rows = [];
        $reversed = 0;
        $clawed = 0;
        $failed = 0;
        $affectedWalletIds = [];

        foreach ($fundings as $funding) {
            $txn = Transaction::query()
                ->where('wallet_funding_id', $funding->id)
                ->where('type', TransactionType::Funding->value)
                ->whereNull('reverses_transaction_id')
                ->first();

            $wallet = Wallet::query()->find($funding->wallet_id);
            $available = $wallet ? Money::round($wallet->availableBalance(), 4) : '0';
            $env = (string) data_get($funding->metadata, 'environment', 'untagged');
            $action = 'would reverse';
            $note = '';

            if (! $txn) {
                $action = 'skip';
                $note = 'no funding ledger row';
                $failed++;
            } elseif ($execute) {
                try {
                    $wallets->reverseTransaction(
                        $txn,
                        'Sandbox deposit purge (before '.$before->toDateString().')',
                        $admin->id,
                    );
                    $action = 'reversed';
                    $reversed++;
                    $affectedWalletIds[$funding->wallet_id] = true;
                    Log::channel('financial')->info('sandbox_purge.reversed', [
                        'funding_id' => $funding->id,
                        'user_id' => $funding->user_id,
                        'amount' => (string) $funding->amount,
                        'admin_id' => $admin->id,
                    ]);
                } catch (InvalidArgumentException $e) {
                    // Already spent some/all of the deposit — claw what is still available.
                    $wallet = Wallet::query()->find($funding->wallet_id);
                    $take = $wallet ? Money::round($wallet->availableBalance(), 4) : '0';
                    if (Money::isPositive($take, 4)) {
                        try {
                            $wallets->adminAdjust(
                                $wallet,
                                Money::sub('0', $take, 4),
                                'Sandbox purge leftover after spent deposit #'.$funding->id,
                                $admin->id,
                            );
                            $action = 'clawed leftover';
                            $note = $e->getMessage();
                            $clawed++;
                            $affectedWalletIds[$funding->wallet_id] = true;
                            Log::channel('financial')->warning('sandbox_purge.clawed_leftover', [
                                'funding_id' => $funding->id,
                                'user_id' => $funding->user_id,
                                'clawed' => $take,
                                'error' => $e->getMessage(),
                                'admin_id' => $admin->id,
                            ]);
                        } catch (InvalidArgumentException $inner) {
                            $action = 'failed';
                            $note = $inner->getMessage();
                            $failed++;
                        }
                    } else {
                        $action = 'spent (nothing left)';
                        $note = $e->getMessage();
                        $failed++;
                    }
                }
            } else {
                $affectedWalletIds[$funding->wallet_id] = true;
                if ($wallet && Money::cmp($available, (string) $funding->amount, 4) < 0) {
                    $action = 'would claw leftover (spent)';
                    $note = 'available '.$available.' < deposit '.$funding->amount;
                }
            }

            $rows[] = [
                $funding->id,
                $funding->user_id,
                (string) $funding->amount,
                $funding->approved_at?->toDateTimeString() ?? $funding->created_at?->toDateTimeString() ?? '-',
                $env,
                $available,
                $action,
                $note,
            ];
        }

        $this->table(
            ['Funding', 'User', 'Amount', 'Approved', 'Env', 'Available now', 'Action', 'Note'],
            $rows,
        );

        $this->line(sprintf(
            'Matched %d deposit(s).%s',
            $fundings->count(),
            $execute
                ? " Reversed {$reversed}, clawed leftover {$clawed}, failed/spent {$failed}."
                : ' Nothing written (dry-run).'
        ));

        if ($zeroAvailable && $affectedWalletIds !== []) {
            foreach (array_keys($affectedWalletIds) as $walletId) {
                $wallet = Wallet::query()->find($walletId);
                if (! $wallet || $wallet->type === WalletType::Platform) {
                    continue;
                }
                $this->zeroOneWallet($wallets, $wallet, $admin, $execute);
            }
        }

        if (! $execute) {
            $this->comment('Re-run with --execute to apply. Add --zero-available to also clear leftovers on those wallets.');
        }

        return $failed > 0 && $execute ? self::FAILURE : self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, WalletFunding> */
    private function candidateFundings(Carbon $before, ?int $userId)
    {
        return WalletFunding::query()
            ->where('status', 'approved')
            ->where(function ($q) use ($before) {
                // Explicitly tagged sandbox deposits (any date).
                $q->where('metadata->environment', 'sandbox')
                    // Untagged / non-live deposits credited at or before go-live.
                    ->orWhere(function ($q2) use ($before) {
                        $q2->where(function ($q3) {
                            $q3->whereNull('metadata->environment')
                                ->orWhere('metadata->environment', '!=', 'live');
                        })->where(function ($q3) use ($before) {
                            $q3->where('approved_at', '<=', $before)
                                ->orWhere(function ($q4) use ($before) {
                                    $q4->whereNull('approved_at')->where('created_at', '<=', $before);
                                });
                        });
                    });
            })
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->whereHas('wallet', fn ($q) => $q->where('type', '!=', WalletType::Platform->value))
            ->orderBy('id')
            ->get();
    }

    private function resolveUserId(): ?int
    {
        $raw = (string) $this->option('user');
        if ($raw === '') {
            return null;
        }
        if (ctype_digit($raw)) {
            return (int) $raw;
        }
        $user = User::query()->where('email', $raw)->first();
        if (! $user) {
            throw new InvalidArgumentException('No user matched --user='.$raw);
        }

        return (int) $user->id;
    }

    private function resolveAdmin(): ?User
    {
        $adminId = $this->option('admin');
        if ($adminId !== null && $adminId !== '') {
            return User::query()->find((int) $adminId);
        }

        return User::query()->role('admin')->orderBy('id')->first();
    }

    private function zeroWalletAvailable(WalletService $wallets, int $userId, User $admin, bool $execute): int
    {
        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->where('type', '!=', WalletType::Platform->value)
            ->first();
        if (! $wallet) {
            $this->warn('User #'.$userId.' has no wallet to zero.');

            return self::SUCCESS;
        }
        $this->zeroOneWallet($wallets, $wallet, $admin, $execute);

        return self::SUCCESS;
    }

    private function zeroOneWallet(WalletService $wallets, Wallet $wallet, User $admin, bool $execute): void
    {
        $available = Money::round($wallet->fresh()->availableBalance(), 4);
        if (! Money::isPositive($available, 4)) {
            $this->line('Wallet #'.$wallet->id.' (user #'.$wallet->user_id.'): available already 0.');

            return;
        }

        if (! $execute) {
            $this->line('Wallet #'.$wallet->id.' (user #'.$wallet->user_id.'): would zero available '.$available);

            return;
        }

        $wallets->adminAdjust(
            $wallet,
            Money::sub('0', $available, 4),
            'Sandbox purge: zero remaining available balance',
            $admin->id,
        );
        $this->info('Wallet #'.$wallet->id.' (user #'.$wallet->user_id.'): zeroed available '.$available);
        Log::channel('financial')->info('sandbox_purge.zero_available', [
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'amount' => $available,
            'admin_id' => $admin->id,
        ]);
    }
}
