<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('withdrawal_payout_attempts') && ! Schema::hasColumn('withdrawal_payout_attempts', 'provider_fee_charged')) {
            Schema::table('withdrawal_payout_attempts', function (Blueprint $table) {
                $table->decimal('provider_fee_charged', 14, 4)->nullable()->after('return_charge');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('withdrawal_payout_attempts', 'provider_fee_charged')) {
            Schema::table('withdrawal_payout_attempts', function (Blueprint $table) {
                $table->dropColumn('provider_fee_charged');
            });
        }
    }
};
