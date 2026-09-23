<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_tools')) {
            return;
        }

        // MySQL-only widen: SQLite affinity already accepts long strings without MODIFY.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // livechat_url was VARCHAR(255) but validation allowed longer links —
        // second saves with longer URLs failed with "Data too long for column".
        if (Schema::hasColumn('user_tools', 'livechat_url')) {
            DB::statement('ALTER TABLE `user_tools` MODIFY `livechat_url` TEXT NULL');
        }

        if (Schema::hasColumn('user_tools', 'livechat_name')) {
            DB::statement('ALTER TABLE `user_tools` MODIFY `livechat_name` VARCHAR(500) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_tools')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('user_tools', 'livechat_url')) {
            DB::statement('ALTER TABLE `user_tools` MODIFY `livechat_url` VARCHAR(255) NULL');
        }

        if (Schema::hasColumn('user_tools', 'livechat_name')) {
            DB::statement('ALTER TABLE `user_tools` MODIFY `livechat_name` VARCHAR(255) NULL');
        }
    }
};
