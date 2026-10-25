<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaign_participations') && ! Schema::hasColumn('campaign_participations', 'proof_purge_at')) {
            Schema::table('campaign_participations', function (Blueprint $table) {
                $table->timestamp('proof_purge_at')->nullable()->after('proof_url');
                $table->index('proof_purge_at');
            });
        }

        if (Schema::hasTable('campaign_participation_tasks') && ! Schema::hasColumn('campaign_participation_tasks', 'proof_purge_at')) {
            Schema::table('campaign_participation_tasks', function (Blueprint $table) {
                $table->timestamp('proof_purge_at')->nullable()->after('proof_url');
                $table->index('proof_purge_at');
            });
        }

        if (Schema::hasTable('agent_social_follow_proofs') && ! Schema::hasColumn('agent_social_follow_proofs', 'proof_purge_at')) {
            Schema::table('agent_social_follow_proofs', function (Blueprint $table) {
                $table->timestamp('proof_purge_at')->nullable()->after('proof_url');
                $table->index('proof_purge_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('campaign_participations') && Schema::hasColumn('campaign_participations', 'proof_purge_at')) {
            Schema::table('campaign_participations', function (Blueprint $table) {
                $table->dropIndex(['proof_purge_at']);
                $table->dropColumn('proof_purge_at');
            });
        }

        if (Schema::hasTable('campaign_participation_tasks') && Schema::hasColumn('campaign_participation_tasks', 'proof_purge_at')) {
            Schema::table('campaign_participation_tasks', function (Blueprint $table) {
                $table->dropIndex(['proof_purge_at']);
                $table->dropColumn('proof_purge_at');
            });
        }

        if (Schema::hasTable('agent_social_follow_proofs') && Schema::hasColumn('agent_social_follow_proofs', 'proof_purge_at')) {
            Schema::table('agent_social_follow_proofs', function (Blueprint $table) {
                $table->dropIndex(['proof_purge_at']);
                $table->dropColumn('proof_purge_at');
            });
        }
    }
};
