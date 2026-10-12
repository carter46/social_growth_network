<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('referral_commissions')) {
            return;
        }

        Schema::table('referral_commissions', function (Blueprint $table) {
            if (! Schema::hasColumn('referral_commissions', 'reversed_amount')) {
                $table->decimal('reversed_amount', 14, 4)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('referral_commissions', 'owed_amount')) {
                // Part of a reversal the referrer could not cover yet; offset against later earnings.
                $table->decimal('owed_amount', 14, 4)->default(0)->after('reversed_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('referral_commissions')) {
            return;
        }

        Schema::table('referral_commissions', function (Blueprint $table) {
            foreach (['owed_amount', 'reversed_amount'] as $column) {
                if (Schema::hasColumn('referral_commissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
