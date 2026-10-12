<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('platform_products', 'task_limits')) {
            Schema::table('platform_products', function (Blueprint $table) {
                $table->json('task_limits')->nullable()->after('task_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('platform_products', 'task_limits')) {
            Schema::table('platform_products', function (Blueprint $table) {
                $table->dropColumn('task_limits');
            });
        }
    }
};
