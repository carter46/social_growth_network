<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * YouTube-only catalog. Rows for removed platforms are kept for order and
 * campaign history: products are archived and categories deactivated, nothing is deleted.
 */
return new class extends Migration
{
    private const PLATFORMS = ['facebook', 'instagram', 'tiktok', 'twitter'];

    public function up(): void
    {
        if (Schema::hasTable('platform_products')) {
            $categoryIds = Schema::hasTable('service_categories') && Schema::hasColumn('platform_products', 'service_category_id')
                ? DB::table('service_categories')->whereIn('slug', self::PLATFORMS)->pluck('id')->all()
                : [];

            DB::table('platform_products')
                ->where(function ($query) use ($categoryIds) {
                    foreach (self::PLATFORMS as $platform) {
                        $query->orWhere('slug', 'like', $platform.'-%');
                    }
                    if ($categoryIds !== []) {
                        $query->orWhereIn('service_category_id', $categoryIds);
                    }
                })
                ->where('status', '!=', 'archived')
                ->update(['status' => 'archived', 'updated_at' => now()]);
        }

        if (Schema::hasTable('service_categories')) {
            DB::table('service_categories')
                ->whereIn('slug', self::PLATFORMS)
                ->update(['is_active' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Left as-is: the platforms' code was removed together with this migration.
    }
};
