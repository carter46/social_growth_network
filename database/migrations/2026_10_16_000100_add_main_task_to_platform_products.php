<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('platform_products', 'main_task')) {
            Schema::table('platform_products', function (Blueprint $table) {
                // Null keeps the default: the task that matches the product's metric.
                $table->string('main_task', 30)->nullable()->after('task_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('platform_products', 'main_task')) {
            Schema::table('platform_products', function (Blueprint $table) {
                $table->dropColumn('main_task');
            });
        }
    }
};
