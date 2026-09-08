<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_products') || ! Schema::hasColumn('platform_products', 'is_campaign')) {
            return;
        }

        // Opt-in campaigns: new products must explicitly enable is_campaign.
        // Existing row values are left unchanged; only the column default changes.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE platform_products MODIFY is_campaign TINYINT(1) NOT NULL DEFAULT 0');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE platform_products ALTER COLUMN is_campaign SET DEFAULT false');
        } elseif ($driver === 'sqlite') {
            // SQLite cannot reliably alter column defaults in-place; new installs use migration 000200 default false.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_products') || ! Schema::hasColumn('platform_products', 'is_campaign')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE platform_products MODIFY is_campaign TINYINT(1) NOT NULL DEFAULT 1');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE platform_products ALTER COLUMN is_campaign SET DEFAULT true');
        }
    }
};
