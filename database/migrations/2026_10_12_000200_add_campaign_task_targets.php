<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('campaigns', 'task_targets')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->json('task_targets')->nullable()->after('required_tasks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('campaigns', 'task_targets')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropColumn('task_targets');
            });
        }
    }
};
