<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_product_variants') || ! Schema::hasTable('platform_products')) {
            return;
        }

        if (! Schema::hasColumn('platform_product_variants', 'pricing_units')) {
            Schema::table('platform_product_variants', function (Blueprint $table) {
                $table->unsignedInteger('pricing_units')->default(1000)->after('unit_price');
            });
        }

        if (! Schema::hasColumn('platform_products', 'agent_reward_percent')) {
            Schema::table('platform_products', function (Blueprint $table) {
                $column = $table->decimal('agent_reward_percent', 5, 2)->nullable();
                if (Schema::hasColumn('platform_products', 'agent_reward_per_completion')) {
                    $column->after('agent_reward_per_completion');
                }
            });
        }

        $hasIncluded = Schema::hasColumn('platform_product_variants', 'included_units');
        $hasFlatReward = Schema::hasColumn('platform_products', 'agent_reward_per_completion');

        DB::table('platform_products')->orderBy('id')->each(function ($product) use ($hasIncluded, $hasFlatReward) {
            $variants = DB::table('platform_product_variants')
                ->where('platform_product_id', $product->id)
                ->orderByDesc('is_active')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $primary = $variants->first();
            if (! $primary) {
                return;
            }

            $wasPerUnit = ($primary->pricing_mode ?? 'fixed') === 'per_unit';
            $included = $hasIncluded ? (int) ($primary->included_units ?? 0) : 0;
            $pricingUnits = $wasPerUnit ? 1000 : ($included > 0 ? $included : 1000);
            $minUnits = (int) ($primary->min_units ?? 0) ?: $pricingUnits;
            $maxUnits = max($minUnits, (int) ($primary->max_units ?? 0) ?: 100000);
            $unitPrice = bcdiv((string) $primary->price, (string) $pricingUnits, 4);

            DB::table('platform_product_variants')->where('id', $primary->id)->update([
                'pricing_mode' => 'per_unit',
                'pricing_units' => $pricingUnits,
                'unit_price' => $unitPrice,
                'min_units' => $minUnits,
                'max_units' => $maxUnits,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
            ]);

            DB::table('platform_product_variants')
                ->where('platform_product_id', $product->id)
                ->where('id', '!=', $primary->id)
                ->update(['is_active' => false, 'is_default' => false]);

            $productUpdate = [
                'base_price' => round((float) $unitPrice * $minUnits, 2),
            ];

            $flatReward = $hasFlatReward ? (float) ($product->agent_reward_per_completion ?? 0) : 0;
            if ($flatReward > 0 && (float) $unitPrice > 0 && $product->agent_reward_percent === null) {
                $productUpdate['agent_reward_percent'] = min(100, round($flatReward / (float) $unitPrice * 100, 2));
            }

            DB::table('platform_products')->where('id', $product->id)->update($productUpdate);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('platform_product_variants', 'pricing_units')) {
            Schema::table('platform_product_variants', fn (Blueprint $table) => $table->dropColumn('pricing_units'));
        }

        if (Schema::hasColumn('platform_products', 'agent_reward_percent')) {
            Schema::table('platform_products', fn (Blueprint $table) => $table->dropColumn('agent_reward_percent'));
        }
    }
};
