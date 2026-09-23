<?php

use App\Models\PlatformProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_product_variants')
            || ! Schema::hasColumn('platform_product_variants', 'included_units')
            || ! Schema::hasTable('platform_products')
            || ! Schema::hasColumn('platform_products', 'is_campaign')) {
            return;
        }

        $ids = DB::table('platform_product_variants as v')
            ->join('platform_products as p', 'p.id', '=', 'v.platform_product_id')
            ->where('p.is_campaign', true)
            ->whereNull('v.included_units')
            ->where(function ($q) {
                $q->whereNull('v.pricing_mode')
                    ->orWhere('v.pricing_mode', PlatformProductVariant::PRICING_FIXED);
            })
            ->select('v.id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $payload = [
            'included_units' => PlatformProductVariant::REFERENCE_UNITS,
        ];

        if (Schema::hasColumn('platform_product_variants', 'pricing_mode')) {
            $payload['pricing_mode'] = PlatformProductVariant::PRICING_FIXED;
        }

        DB::table('platform_product_variants')
            ->whereIn('id', $ids)
            ->update($payload);
    }

    public function down(): void
    {
        // Irreversible data backfill — leave values in place.
    }
};
