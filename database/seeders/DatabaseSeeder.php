<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            SystemSettingSeeder::class,
            AnalyticsProviderSeeder::class,
            CommunicationsSeeder::class,
            PlatformCategorySeeder::class,
            PlatformCatalogSeeder::class,
            PlatformWalletSeeder::class,
        ]);

        // Link products to fixed hierarchy + set category keys (non-destructive).
        Artisan::call('catalog:backfill-hierarchy');

        // Demo data when ALLOW_DEMO_DATA / SEED_DEMO_DATA is true (works with APP_ENV=production for pre-launch).
        if (\App\Support\Demo\DemoGate::allowDemoData()) {
            $this->call([
                \Database\Seeders\Demo\DemoPlatformSeeder::class,
            ]);
        }
    }
}
