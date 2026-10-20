<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'address_line1')) {
                $table->string('address_line1', 160)->nullable()->after('country');
            }
            if (! Schema::hasColumn('users', 'address_city')) {
                $table->string('address_city', 80)->nullable()->after('address_line1');
            }
            if (! Schema::hasColumn('users', 'address_state')) {
                $table->string('address_state', 80)->nullable()->after('address_city');
            }
            if (! Schema::hasColumn('users', 'address_postal_code')) {
                $table->string('address_postal_code', 20)->nullable()->after('address_state');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['address_line1', 'address_city', 'address_state', 'address_postal_code'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
