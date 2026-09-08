<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_products') || ! Schema::hasTable('service_categories')) {
            return;
        }

        if (! Schema::hasColumn('platform_products', 'service_category_id')) {
            Schema::table('platform_products', function (Blueprint $table) {
                $table->foreignId('service_category_id')
                    ->nullable()
                    ->after('product_type_id')
                    ->constrained('service_categories')
                    ->nullOnDelete();
            });
        }

        // Required: backfill ownership immediately so visibility does not empty the storefront.
        try {
            Artisan::call('catalog:flatten-category-products');
        } catch (\Throwable $e) {
            // Command may be unavailable mid-deploy before code is fully loaded; operators can re-run.
            // Dual-read visibility still shows products via ProductType until flatten succeeds.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_products')) {
            return;
        }

        if (Schema::hasColumn('platform_products', 'service_category_id')) {
            Schema::table('platform_products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('service_category_id');
            });
        }
    }
};
