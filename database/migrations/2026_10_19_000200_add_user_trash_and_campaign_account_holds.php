<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('anonymized_at');
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'status_before_hold')) {
                $table->string('status_before_hold', 32)->nullable()->after('status');
            }
            if (! Schema::hasColumn('campaigns', 'account_hold_reason')) {
                $table->string('account_hold_reason', 40)->nullable()->after('status_before_hold');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'account_hold_reason')) {
                $table->dropColumn('account_hold_reason');
            }
            if (Schema::hasColumn('campaigns', 'status_before_hold')) {
                $table->dropColumn('status_before_hold');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
