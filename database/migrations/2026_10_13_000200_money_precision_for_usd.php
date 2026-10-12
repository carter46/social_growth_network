<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Small USD rewards (fractions of a cent) need 4-decimal balances, holds, ledger entries and
 * rewards, and 6-decimal per-unit prices. Orders, deposits and withdrawals stay in cents.
 */
return new class extends Migration
{
    /** @var array<string, array<string, array{0: int, 1: int, 2: bool, 3: string|null}>> table => column => [precision, scale, nullable, default] */
    private const COLUMNS = [
        'wallets' => [
            'balance' => [18, 4, false, '0'],
            'locked_balance' => [18, 4, false, '0'],
        ],
        'wallet_holds' => [
            'amount' => [18, 4, false, null],
        ],
        'transactions' => [
            'amount' => [18, 4, false, null],
        ],
        'campaigns' => [
            'locked_creator_price' => [18, 6, false, null],
            'locked_agent_reward' => [16, 4, false, null],
        ],
        'order_items' => [
            'unit_price' => [20, 6, false, null],
        ],
        'campaign_participations' => [
            'reward_amount' => [16, 4, false, null],
        ],
        'campaign_budgets' => [
            'funded_amount' => [18, 4, false, '0'],
            'paid_amount' => [18, 4, false, '0'],
            'refunded_amount' => [18, 4, false, '0'],
            'released_amount' => [18, 4, false, '0'],
        ],
        'referral_commissions' => [
            'base_amount' => [18, 4, false, null],
            'amount' => [18, 4, false, null],
        ],
        'platform_product_variants' => [
            'unit_price' => [20, 6, true, null],
        ],
    ];

    private const ORIGINAL = [
        'wallets' => ['balance' => [14, 2], 'locked_balance' => [14, 2]],
        'wallet_holds' => ['amount' => [14, 2]],
        'transactions' => ['amount' => [14, 2]],
        'campaigns' => ['locked_creator_price' => [12, 2], 'locked_agent_reward' => [12, 2]],
        'order_items' => ['unit_price' => [18, 2]],
        'campaign_participations' => ['reward_amount' => [12, 2]],
        'campaign_budgets' => ['funded_amount' => [14, 2], 'paid_amount' => [14, 2], 'refunded_amount' => [14, 2], 'released_amount' => [14, 2]],
        'referral_commissions' => ['base_amount' => [14, 2], 'amount' => [14, 2]],
        'platform_product_variants' => ['unit_price' => [18, 4]],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $this->alter($table, $columns);
        }
    }

    public function down(): void
    {
        foreach (self::ORIGINAL as $table => $columns) {
            $spec = [];
            foreach ($columns as $column => [$precision, $scale]) {
                [, , $nullable, $default] = self::COLUMNS[$table][$column];
                $spec[$column] = [$precision, $scale, $nullable, $default];
            }
            $this->alter($table, $spec);
        }
    }

    /**
     * @param  array<string, array{0: int, 1: int, 2: bool, 3: string|null}>  $columns
     */
    private function alter(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $present = array_filter(array_keys($columns), fn (string $column) => Schema::hasColumn($table, $column));
        if ($present === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $present) {
            foreach ($present as $column) {
                [$precision, $scale, $nullable, $default] = $columns[$column];
                $definition = $blueprint->decimal($column, $precision, $scale);
                if ($nullable) {
                    $definition->nullable();
                }
                if ($default !== null) {
                    $definition->default($default);
                }
                $definition->change();
            }
        });
    }
};
