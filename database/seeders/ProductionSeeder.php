<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SystemSettingSeeder::class,
        ]);

        // Legacy flavor categories (optional; dropped by cleanup migration).
        if (Schema::hasTable('platform_categories')) {
            $this->call(PlatformCategorySeeder::class);
        }

        $this->call([
            PlatformCatalogSeeder::class,
            PlatformWalletSeeder::class,
        ]);

        if (Schema::hasTable('service_categories')) {
            Artisan::call('catalog:backfill-hierarchy');
            $this->command?->info(trim(Artisan::output()));
        }
    }
}
