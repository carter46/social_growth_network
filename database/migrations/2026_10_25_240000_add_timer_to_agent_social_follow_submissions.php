<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agent_social_follow_submissions')) {
            return;
        }

        Schema::table('agent_social_follow_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('agent_social_follow_submissions', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('agent_social_follow_submissions', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('started_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('agent_social_follow_submissions')) {
            return;
        }

        Schema::table('agent_social_follow_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('agent_social_follow_submissions', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
            if (Schema::hasColumn('agent_social_follow_submissions', 'started_at')) {
                $table->dropColumn('started_at');
            }
        });
    }
};
