<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agent_youtube_identities')) {
            return;
        }

        if (! Schema::hasColumn('agent_youtube_identities', 'reclaim_from_user_id')) {
            Schema::table('agent_youtube_identities', function (Blueprint $table) {
                $table->foreignId('reclaim_from_user_id')
                    ->nullable()
                    ->after('reviewed_by')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('agent_youtube_identities')) {
            return;
        }

        if (Schema::hasColumn('agent_youtube_identities', 'reclaim_from_user_id')) {
            Schema::table('agent_youtube_identities', function (Blueprint $table) {
                $table->dropConstrainedForeignId('reclaim_from_user_id');
            });
        }
    }
};
