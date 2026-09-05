<?php

namespace Database\Seeders\Demo;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletFunding;
use App\Models\Withdrawal;
use Database\Seeders\Demo\Support\DemoContext;
use Database\Seeders\Demo\Support\DemoTimeline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoWalletSeeder extends Seeder
{
    public function run(DemoContext $ctx, DemoTimeline $timeline): void
    {
        $finance = $ctx->admin('finance');
        $txCount = 0;

        foreach ($ctx->members() as $key => $user) {
            if ($key === 'emily') {
                continue;
            }

            $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
            $months = max(1, (int) ($user->created_at?->diffInMonths(now()) ?: 3));

            // Large base deposit so later escrow locks stay solvent.
            $baseAt = $timeline->monthsAgo(max(0, $months - 1), 8, 11);
            $baseAmount = in_array($key, ['alice', 'sarah', 'filler1', 'filler2', 'filler3'], true) ? 400000 : 250000;
            $baseFunding = WalletFunding::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'method' => 'bank',
                'amount' => $baseAmount,
                'currency' => 'NGN',
                'status' => 'approved',
                'reference' => $ctx->ref('DEP'),
                'approved_by' => $finance->id,
                'approved_at' => $baseAt->copy()->addHours(2),
                'approved_ip' => '203.0.113.'.(($user->id % 200) + 1),
                'metadata' => ['demo' => true, 'persona' => $key, 'base' => true],
            ]);
            $ctx->stamp($baseFunding, $baseAt, ['approved_at' => $baseAt->copy()->addHours(2)]);
            $baseTxn = Transaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'wallet_funding_id' => $baseFunding->id,
                'reference' => $ctx->ref('TXN'),
                'type' => TransactionType::Funding->value,
                'label' => 'Deposit (bank)',
                'description' => 'Wallet funding approved',
                'amount' => $baseAmount,
                'currency' => 'NGN',
                'status' => 'completed',
            ]);
            $ctx->stamp($baseTxn, $baseAt->copy()->addHours(2));
            $txCount++;

            // Smaller follow-up fundings for timeline charts
            $fundings = min(3, $months);
            for ($i = 0; $i < $fundings; $i++) {
                $at = $timeline->monthsAgo(max(0, $months - $i - 2), 10 + $i, 13);
                $amount = [25000, 50000, 75000, 15000][$i % 4];
                $ref = $ctx->ref('DEP');

                $funding = WalletFunding::query()->create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'method' => 'bank',
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'status' => 'approved',
                    'reference' => $ref,
                    'approved_by' => $finance->id,
                    'approved_at' => $at->copy()->addHours(2),
                    'approved_ip' => '203.0.113.'.(($user->id % 200) + 1),
                    'metadata' => ['demo' => true, 'persona' => $key],
                ]);
                $ctx->stamp($funding, $at, ['approved_at' => $at->copy()->addHours(2)]);

                $txn = Transaction::query()->create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'wallet_funding_id' => $funding->id,
                    'reference' => $ctx->ref('TXN'),
                    'type' => TransactionType::Funding->value,
                    'label' => 'Deposit (bank)',
                    'description' => 'Wallet funding approved',
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'status' => 'completed',
                ]);
                $ctx->stamp($txn, $at->copy()->addHours(2));
                $txCount++;
            }

            if (in_array($key, ['alice', 'filler1', 'filler5'], true)) {
                $at = $timeline->daysAgo(2);
                $pending = WalletFunding::query()->create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'method' => 'bank',
                    'amount' => 20000,
                    'currency' => 'NGN',
                    'status' => 'pending',
                    'reference' => $ctx->ref('DEP'),
                    'metadata' => ['demo' => true, 'pending' => true],
                ]);
                $ctx->stamp($pending, $at);
            }

            if (in_array($key, ['sarah', 'michael', 'filler3'], true)) {
                $at = $timeline->monthsAgo(1, 18, 15);
                $wd = Withdrawal::query()->create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'amount' => 12000,
                    'currency' => 'NGN',
                    'status' => 'completed',
                    'reference' => $ctx->ref('WDR'),
                    'bank_name' => 'Demo Bank',
                    'account_name' => $user->name,
                    'account_number' => '0123456789',
                    'approved_by' => $finance->id,
                    'approved_at' => $at->copy()->addDay(),
                ]);
                $ctx->stamp($wd, $at, ['approved_at' => $at->copy()->addDay()]);

                $txn = Transaction::query()->create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'withdrawal_id' => $wd->id,
                    'reference' => $ctx->ref('TXN'),
                    'type' => TransactionType::Withdrawal->value,
                    'label' => 'Withdrawal',
                    'description' => 'Payout completed',
                    'amount' => -12000,
                    'currency' => 'NGN',
                    'status' => 'completed',
                ]);
                $ctx->stamp($txn, $at->copy()->addDay());
                $txCount++;
            }

            if ($key === 'sarah') {
                $at = $timeline->daysAgo(1);
                $pendingWd = Withdrawal::query()->create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'amount' => 5000,
                    'currency' => 'NGN',
                    'status' => 'pending',
                    'reference' => $ctx->ref('WDR'),
                    'bank_name' => 'Demo Bank',
                    'account_name' => $user->name,
                    'account_number' => '0123456789',
                ]);
                $ctx->stamp($pendingWd, $at);
            }
        }

        // Reversed funding with matching ledger (funding + reversal).
        $alice = $ctx->member('alice');
        $aliceWallet = Wallet::query()->where('user_id', $alice->id)->firstOrFail();
        $revAt = $timeline->monthsAgo(3, 5, 10);
        $reversed = WalletFunding::query()->create([
            'user_id' => $alice->id,
            'wallet_id' => $aliceWallet->id,
            'method' => 'bank',
            'amount' => 10000,
            'currency' => 'NGN',
            'status' => 'reversed',
            'reference' => $ctx->ref('DEP'),
            'approved_by' => $finance->id,
            'approved_at' => $revAt,
            'reversed_at' => $revAt->copy()->addDays(1),
            'metadata' => ['demo' => true, 'reason' => 'Duplicate credit'],
        ]);
        $ctx->stamp($reversed, $revAt, [
            'approved_at' => $revAt,
            'reversed_at' => $revAt->copy()->addDays(1),
        ]);

        $orig = Transaction::query()->create([
            'user_id' => $alice->id,
            'wallet_id' => $aliceWallet->id,
            'wallet_funding_id' => $reversed->id,
            'reference' => $ctx->ref('TXN'),
            'type' => TransactionType::Funding->value,
            'label' => 'Deposit (bank)',
            'description' => 'Wallet funding approved',
            'amount' => 10000,
            'currency' => 'NGN',
            'status' => 'completed',
        ]);
        $ctx->stamp($orig, $revAt);
        $txCount++;

        $reversal = Transaction::query()->create([
            'user_id' => $alice->id,
            'wallet_id' => $aliceWallet->id,
            'wallet_funding_id' => $reversed->id,
            'reverses_transaction_id' => $orig->id,
            'reference' => $ctx->ref('TXN'),
            'type' => TransactionType::Reversal->value,
            'label' => 'Reversal',
            'description' => 'Duplicate credit',
            'amount' => -10000,
            'currency' => 'NGN',
            'status' => 'completed',
        ]);
        $ctx->stamp($reversal, $revAt->copy()->addDays(1));
        $reversed->forceFill(['reversal_transaction_id' => $reversal->id])->save();
        $txCount++;

        // Pad toward ~300 completed txs with small admin adjustments.
        $padMembers = $ctx->members()->filter(fn ($u, $k) => $k !== 'emily')->values();
        for ($i = 0; $i < 40; $i++) {
            $user = $padMembers[$i % $padMembers->count()];
            $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
            $at = $timeline->daysAgo(10 + ($i % 50), 9);
            $adj = Transaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'reference' => $ctx->ref('TXN'),
                'type' => TransactionType::AdminAdjustment->value,
                'label' => 'Admin adjustment',
                'description' => 'Demo goodwill credit',
                'amount' => 500,
                'currency' => 'NGN',
                'status' => 'completed',
            ]);
            $ctx->stamp($adj, $at);
            $txCount++;
        }

        $ctx->transactionCount += $txCount;
        $ctx->note('✓ Wallet fundings / withdrawals seeded');
    }
}
