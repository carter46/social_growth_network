<?php

namespace App\Modules\Wallet\Services;

use App\Enums\TransactionType;
use App\Enums\WalletHoldReason;
use App\Enums\WalletHoldStatus;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Read-only comparison of each wallet's stored balance with its completed ledger entries.
 * Withdrawal holds and unlocks only move locked_balance, so they are left out of the sum.
 */
class LedgerReconciliationService
{
    private const SCALE = 4;

    /** @return list<string> */
    public static function nonBalanceTypes(): array
    {
        return [TransactionType::WithdrawalHold->value, TransactionType::WithdrawalUnlock->value];
    }

    /**
     * @return list<array{wallet_id: int, user_id: int|null, balance: string, ledger: string, residual: string, locked_balance: string, holds: string, lock_residual: string}>
     */
    public function mismatches(string $tolerance = '0.0000'): array
    {
        $ledger = DB::table('transactions')
            ->selectRaw('wallet_id, SUM(amount) as total')
            ->where('status', 'completed')
            ->whereNotNull('wallet_id')
            ->whereNotIn('type', self::nonBalanceTypes())
            ->groupBy('wallet_id')
            ->pluck('total', 'wallet_id');

        $holds = DB::table('wallet_holds')
            ->selectRaw('wallet_id, SUM(amount) as total')
            ->where('status', WalletHoldStatus::Active->value)
            ->where('reason_type', '!=', WalletHoldReason::CampaignBudget->value)
            ->groupBy('wallet_id')
            ->pluck('total', 'wallet_id')
            ->map(fn ($total) => $this->num($total))
            ->all();

        // Campaign rewards unlock funds without shrinking the hold row, so the budget's
        // remaining amount is what should still be locked.
        $campaignHolds = DB::table('wallet_holds')
            ->leftJoin('campaign_budgets', 'campaign_budgets.campaign_id', '=', 'wallet_holds.reason_id')
            ->where('wallet_holds.status', WalletHoldStatus::Active->value)
            ->where('wallet_holds.reason_type', WalletHoldReason::CampaignBudget->value)
            ->get([
                'wallet_holds.wallet_id', 'wallet_holds.amount', 'campaign_budgets.id as budget_id',
                'campaign_budgets.funded_amount', 'campaign_budgets.paid_amount',
                'campaign_budgets.refunded_amount', 'campaign_budgets.released_amount',
            ]);
        foreach ($campaignHolds as $hold) {
            $remaining = $hold->budget_id === null
                ? $this->num($hold->amount)
                : bcsub($this->num($hold->funded_amount), bcadd(
                    bcadd($this->num($hold->paid_amount), $this->num($hold->refunded_amount), self::SCALE),
                    $this->num($hold->released_amount),
                    self::SCALE,
                ), self::SCALE);
            if (bccomp($remaining, '0', self::SCALE) < 0) {
                $remaining = '0.0000';
            }
            $holds[$hold->wallet_id] = bcadd($holds[$hold->wallet_id] ?? '0', $remaining, self::SCALE);
        }

        $rows = [];
        DB::table('wallets')->select(['id', 'user_id', 'balance', 'locked_balance'])->orderBy('id')
            ->chunkById(500, function ($wallets) use ($ledger, $holds, $tolerance, &$rows) {
                foreach ($wallets as $wallet) {
                    $balance = $this->num($wallet->balance);
                    $sum = $this->num($ledger[$wallet->id] ?? '0');
                    $residual = bcsub($balance, $sum, self::SCALE);
                    $locked = $this->num($wallet->locked_balance);
                    $held = $this->num($holds[$wallet->id] ?? '0');
                    $lockResidual = bcsub($locked, $held, self::SCALE);

                    if ($this->within($residual, $tolerance) && $this->within($lockResidual, $tolerance)) {
                        continue;
                    }

                    $rows[] = [
                        'wallet_id' => (int) $wallet->id,
                        'user_id' => $wallet->user_id !== null ? (int) $wallet->user_id : null,
                        'balance' => $balance,
                        'ledger' => $sum,
                        'residual' => $residual,
                        'locked_balance' => $locked,
                        'holds' => $held,
                        'lock_residual' => $lockResidual,
                    ];
                }
            });

        return $rows;
    }

    private function within(string $value, string $tolerance): bool
    {
        return bccomp(ltrim($value, '-'), $tolerance, self::SCALE) <= 0;
    }

    private function num(mixed $value): string
    {
        return Money::round(is_numeric($value) ? (string) $value : '0', self::SCALE);
    }
}
