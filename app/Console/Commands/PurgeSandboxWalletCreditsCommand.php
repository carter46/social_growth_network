<?php

namespace App\Console\Commands;

use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Order;
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
 *
 * --cancel-open closes unfinished sandbox deposits/orders.
 * --archive tags sandbox-era deposit/order rows so admin lists hide them by default.
 */
class PurgeSandboxWalletCreditsCommand extends Command
{
    protected $signature = 'wallets:purge-sandbox-credits
        {--before= : Cutover datetime (Y-m-d or ISO). Approved deposits at/before this are treated as sandbox}
        {--user= : Limit to one user id or email}
        {--execute : Apply changes (default is dry-run)}
        {--zero-available : After reversing, zero remaining available balance on wallets that had sandbox deposits}
        {--cancel-open : Cancel pending/processing sandbox deposits and unpaid platform orders}
        {--archive : Tag sandbox-era deposits/orders so admin lists hide them (use ?sandbox=1 to show)}
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
        $cancelOpen = (bool) $this->option('cancel-open');
        $archive = (bool) $this->option('archive');

        try {
            $userFilter = $this->resolveUserId();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(($execute ? 'EXECUTE' : 'DRY RUN').' — sandbox cutover at/before '.$before->toDateTimeString());
        $this->line('Admin attribution: #'.$admin->id.' ('.$admin->email.')');
        if ($userFilter) {
            $this->line('Limited to user #'.$userFilter);
        }

        $failed = $this->purgeApprovedCredits($wallets, $before, $userFilter, $admin, $execute, $zeroAvailable);

        if ($cancelOpen) {
            $this->cancelOpenRecords($before, $userFilter, $execute);
        }

        if ($archive) {
            $this->archiveRecords($before, $userFilter, $execute);
        }

        if (! $execute) {
            $this->comment('Re-run with --execute to apply.');
            if (! $cancelOpen || ! $archive) {
                $this->comment('Add --cancel-open and/or --archive to clean admin deposit/order lists.');
            }
        }

        return $failed > 0 && $execute ? self::FAILURE : self::SUCCESS;
    }

    private function purgeApprovedCredits(
        WalletService $wallets,
        Carbon $before,
        ?int $userFilter,
        User $admin,
        bool $execute,
        bool $zeroAvailable,
    ): int {
        $fundings = $this->candidateFundings($before, $userFilter);

        if ($fundings->isEmpty()) {
            $this->line('No approved sandbox-era deposits left to reverse.');
            if ($zeroAvailable && $userFilter) {
                $this->zeroWalletAvailable($wallets, $userFilter, $admin, $execute);
            }

            return 0;
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
                    $this->tagFundingPurged($funding->fresh(), 'reversed');
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
                    $wallet = Wallet::query()->find($funding->wallet_id);
                    $take = $wallet ? Money::round($wallet->availableBalance(), 4) : '0';
                    if (Money::isPositive($take, 4)) {
                        try {
                            $adj = $wallets->adminAdjust(
                                $wallet,
                                Money::sub('0', $take, 4),
                                'Sandbox purge leftover after spent deposit #'.$funding->id,
                                $admin->id,
                            );
                            $this->markFundingClawed($funding, $adj->id);
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
                        $this->markFundingClawed($funding, null);
                        $action = 'spent (marked reversed)';
                        $note = $e->getMessage();
                        $clawed++;
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
                ? " Reversed {$reversed}, clawed/marked {$clawed}, failed {$failed}."
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

        return $failed;
    }

    private function cancelOpenRecords(Carbon $before, ?int $userId, bool $execute): void
    {
        $fundings = WalletFunding::query()
            ->whereIn('status', ['pending', 'processing'])
            ->where('created_at', '<=', $before)
            ->where(function ($q) {
                $q->whereNull('metadata->environment')
                    ->orWhere('metadata->environment', '!=', 'live');
            })
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')
            ->get();

        $orders = Order::query()
            ->where('source', 'platform')
            ->whereIn('status', ['pending', 'processing'])
            ->where('created_at', '<=', $before)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')
            ->get();

        $this->line('');
        $this->info('Open sandbox deposits/orders: '.$fundings->count().' deposit(s), '.$orders->count().' order(s).');

        foreach ($fundings as $funding) {
            if (! $execute) {
                $this->line("  would cancel deposit #{$funding->id} {$funding->reference} ({$funding->status})");

                continue;
            }
            $funding->update([
                'status' => 'rejected',
                'internal_status' => 'rejected',
                'admin_notes' => trim((string) $funding->admin_notes."\nSandbox purge: cancelled unfinished checkout."),
                'metadata' => $this->withPurgedMeta($funding->metadata),
            ]);
            $this->line("  cancelled deposit #{$funding->id} {$funding->reference}");
        }

        foreach ($orders as $order) {
            if (! $execute) {
                $this->line("  would cancel order #{$order->id} {$order->reference} ({$order->status})");

                continue;
            }
            $meta = $this->withPurgedMeta($order->payment_metadata);
            $meta['cancel_reason'] = 'Sandbox purge: cancelled unfinished checkout.';
            $order->update([
                'status' => 'cancelled',
                'payment_metadata' => $meta,
            ]);
            $this->line("  cancelled order #{$order->id} {$order->reference}");
        }
    }

    private function archiveRecords(Carbon $before, ?int $userId, bool $execute): void
    {
        $fundings = WalletFunding::query()
            ->where('created_at', '<=', $before)
            ->where(function ($q) {
                $q->whereNull('metadata->environment')
                    ->orWhere('metadata->environment', '!=', 'live');
            })
            ->where(function ($q) {
                $q->whereNull('metadata->sandbox_purged_at');
            })
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')
            ->get();

        $orders = Order::query()
            ->where('source', 'platform')
            ->where('created_at', '<=', $before)
            ->where(function ($q) {
                $q->whereNull('payment_metadata->sandbox_purged_at');
            })
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')
            ->get();

        $this->line('');
        $this->info('Archive sandbox history: '.$fundings->count().' deposit(s), '.$orders->count().' order(s).');

        foreach ($fundings as $funding) {
            if (! $execute) {
                $this->line("  would archive deposit #{$funding->id} {$funding->reference} ({$funding->status})");

                continue;
            }
            $updates = [
                'metadata' => $this->withPurgedMeta($funding->metadata),
                'admin_notes' => trim((string) $funding->admin_notes."\nSandbox archive: hidden from admin lists."),
            ];
            // Clawed leftovers that still say "approved" — mark reversed for the UI.
            if ($funding->status === 'approved') {
                $updates['status'] = 'reversed';
                $updates['internal_status'] = 'reversed';
                $updates['reversed_at'] = $funding->reversed_at ?? now();
            }
            $funding->update($updates);
            $this->line("  archived deposit #{$funding->id} {$funding->reference}");
        }

        foreach ($orders as $order) {
            if (! $execute) {
                $this->line("  would archive order #{$order->id} {$order->reference} ({$order->status})");

                continue;
            }
            $meta = $this->withPurgedMeta($order->payment_metadata);
            $meta['sandbox_note'] = 'Sandbox archive: hidden from admin lists. Paid test orders were not unfulfilled.';
            $order->update(['payment_metadata' => $meta]);
            $this->line("  archived order #{$order->id} {$order->reference}");
        }

        if ($execute) {
            $this->comment('Admin Deposits / Orders / Transactions hide these unless you open the page with ?sandbox=1');
        }
    }

    /** @param  array<string, mixed>|null  $meta */
    private function withPurgedMeta(?array $meta): array
    {
        $meta = $meta ?? [];
        $meta['sandbox_purged_at'] = now()->toIso8601String();
        if (! isset($meta['environment']) || $meta['environment'] === '') {
            $meta['environment'] = 'sandbox';
        }

        return $meta;
    }

    private function tagFundingPurged(WalletFunding $funding, string $status): void
    {
        $funding->update([
            'metadata' => $this->withPurgedMeta($funding->metadata),
            'status' => $status,
        ]);
    }

    private function markFundingClawed(WalletFunding $funding, ?int $adjustmentId): void
    {
        $funding->update([
            'status' => 'reversed',
            'internal_status' => 'reversed',
            'reversed_at' => now(),
            'reversal_transaction_id' => $adjustmentId ?? $funding->reversal_transaction_id,
            'admin_notes' => trim((string) $funding->admin_notes."\nSandbox purge: deposit was partly spent; leftover clawed."),
            'metadata' => $this->withPurgedMeta($funding->metadata),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, WalletFunding> */
    private function candidateFundings(Carbon $before, ?int $userId)
    {
        return WalletFunding::query()
            ->where('status', 'approved')
            ->where(function ($q) use ($before) {
                $q->where('metadata->environment', 'sandbox')
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
