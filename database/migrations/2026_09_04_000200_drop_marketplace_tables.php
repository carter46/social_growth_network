<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_holds')) {
            $escrowListingHolds = DB::table('wallet_holds')
                ->whereIn('reason_type', ['escrow', 'listing'])
                ->where('status', 'active')
                ->get(['wallet_id', 'amount']);

            foreach ($escrowListingHolds as $hold) {
                DB::table('wallets')
                    ->where('id', $hold->wallet_id)
                    ->decrement('locked_balance', (float) $hold->amount);
            }

            DB::table('wallet_holds')
                ->whereIn('reason_type', ['escrow', 'listing'])
                ->delete();
        }

        if (Schema::hasTable('orders')) {
            DB::table('orders')->where('source', 'marketplace')->delete();
        }

        Schema::disableForeignKeyConstraints();

        foreach (['watchlists', 'reviews', 'messages', 'escrows', 'listing_versions'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }

        if (Schema::hasTable('listings')) {
            Schema::drop('listings');
        }

        if (Schema::hasTable('marketplace_products')) {
            Schema::drop('marketplace_products');
        }

        if (Schema::hasTable('categories')) {
            Schema::drop('categories');
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'listing_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('listing_id');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Irreversible: marketplace subsystem removed.
    }
};
