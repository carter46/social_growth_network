<?php

use App\Support\PlatformProductSlugRedirect;
use Database\Seeders\PlatformCatalogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expand social catalog on existing DBs that still have pre-rename Views slugs.
 * Fresh installs / empty catalogs use DatabaseSeeder (PlatformCatalogSeeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_products')) {
            return;
        }

        $legacySlugs = PlatformProductSlugRedirect::legacySlugs();
        if ($legacySlugs === []) {
            return;
        }

        // Only when old Views rows still exist (production upgrade). Avoids
        // seeding 17 products during RefreshDatabase before feature tests create fixtures.
        $hasLegacy = DB::table('platform_products')->whereIn('slug', $legacySlugs)->exists();
        if (! $hasLegacy) {
            return;
        }

        (new PlatformCatalogSeeder)->run();

        if (Schema::hasColumn('platform_products', 'service_category_id')) {
            Artisan::call('catalog:flatten-category-products');
        }
    }

    public function down(): void
    {
        // Irreversible data expansion / rename — no-op.
    }
};
