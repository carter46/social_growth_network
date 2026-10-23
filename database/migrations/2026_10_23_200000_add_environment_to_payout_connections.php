<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payout_connections') || Schema::hasColumn('payout_connections', 'environment')) {
            return;
        }

        Schema::table('payout_connections', function (Blueprint $table) {
            $table->string('environment', 16)->nullable()->after('provider');
            $table->index(['provider', 'environment']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payout_connections') || ! Schema::hasColumn('payout_connections', 'environment')) {
            return;
        }

        Schema::table('payout_connections', function (Blueprint $table) {
            $table->dropIndex(['provider', 'environment']);
            $table->dropColumn('environment');
        });
    }
};
