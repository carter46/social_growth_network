<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the old seeded NGN meta description. A description an admin has edited is left alone.
 */
return new class extends Migration
{
    private const OLD = 'NGN wallet marketplace. Deposit, buy with escrow, sell digital products and services.';

    private const NEW = 'Social Growth Network connects Creators and Agents for YouTube campaign packages, proof verification, USD wallet funding, and payouts.';

    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')
            ->where('key', 'site_meta_description')
            ->where('value', self::OLD)
            ->update(['value' => self::NEW, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')
            ->where('key', 'site_meta_description')
            ->where('value', self::NEW)
            ->update(['value' => self::OLD, 'updated_at' => now()]);
    }
};
