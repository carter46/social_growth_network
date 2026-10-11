<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive schema for multi-task YouTube products: task order, completion timer,
 * per-step progress, agent channel identities, action records and campaign budgets.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_products')) {
            Schema::table('platform_products', function (Blueprint $table) {
                if (! Schema::hasColumn('platform_products', 'task_order')) {
                    $table->json('task_order')->nullable();
                }
                if (! Schema::hasColumn('platform_products', 'completion_minutes')) {
                    $table->unsignedInteger('completion_minutes')->nullable();
                }
            });
        }

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (! Schema::hasColumn('campaigns', 'required_tasks')) {
                    $table->json('required_tasks')->nullable();
                }
                if (! Schema::hasColumn('campaigns', 'channel_url')) {
                    $table->string('channel_url', 500)->nullable();
                }
                if (! Schema::hasColumn('campaigns', 'channel_id')) {
                    $table->string('channel_id', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('campaigns', 'video_id')) {
                    $table->string('video_id', 32)->nullable()->index();
                }
                if (! Schema::hasColumn('campaigns', 'comment_instructions')) {
                    $table->text('comment_instructions')->nullable();
                }
                if (! Schema::hasColumn('campaigns', 'completion_minutes')) {
                    $table->unsignedInteger('completion_minutes')->nullable();
                }
            });
        }

        if (Schema::hasTable('campaign_participations')) {
            Schema::table('campaign_participations', function (Blueprint $table) {
                if (! Schema::hasColumn('campaign_participations', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->index();
                }
                if (! Schema::hasColumn('campaign_participations', 'last_heartbeat_at')) {
                    $table->timestamp('last_heartbeat_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('campaign_participation_tasks')) {
            Schema::create('campaign_participation_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('participation_id')->constrained('campaign_participations')->cascadeOnDelete();
                $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
                $table->string('task', 16);
                $table->unsignedTinyInteger('position');
                $table->string('status', 16)->index();
                $table->string('evidence_method', 32)->nullable();
                $table->string('proof_url', 500)->nullable();
                $table->text('proof_notes')->nullable();
                $table->json('proof_meta')->nullable();
                $table->timestamp('done_at')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->timestamp('next_check_at')->nullable()->index();
                $table->timestamp('accepted_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('rejection_reason')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamps();

                $table->unique(['participation_id', 'task']);
            });
        }

        if (! Schema::hasTable('agent_youtube_identities')) {
            Schema::create('agent_youtube_identities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('channel_id', 64)->nullable()->index();
                // Set only while verified, so one channel serves one account (nulls never collide).
                $table->string('active_channel_id', 64)->nullable()->unique();
                $table->string('channel_url', 500)->nullable();
                $table->string('handle', 120)->nullable();
                $table->string('title')->nullable();
                $table->string('thumbnail_url', 500)->nullable();
                $table->string('method', 16)->default('code');
                $table->string('status', 16)->index();
                $table->string('verification_code', 32)->nullable();
                $table->timestamp('code_expires_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->unsignedSmallInteger('check_attempts')->default(0);
                $table->text('oauth_token')->nullable();
                $table->text('failure_reason')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('agent_youtube_actions')) {
            Schema::create('agent_youtube_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('agent_channel_id', 64)->nullable();
                $table->string('action', 16);
                $table->string('target_channel_id', 64)->nullable();
                $table->string('target_video_id', 32)->nullable();
                $table->string('external_ref', 128)->nullable();
                $table->unsignedBigInteger('campaign_id')->nullable()->index();
                $table->unsignedBigInteger('campaign_participation_id')->nullable()->index();
                $table->string('evidence', 32);
                $table->string('status', 16)->default('verified');
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamps();

                $table->unique(['action', 'external_ref']);
                $table->index(['user_id', 'action', 'target_channel_id']);
                $table->index(['user_id', 'action', 'target_video_id']);
            });
        }

        if (! Schema::hasTable('campaign_budgets')) {
            Schema::create('campaign_budgets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->unique()->constrained('campaigns')->cascadeOnDelete();
                $table->decimal('funded_amount', 14, 2)->default(0);
                $table->decimal('paid_amount', 14, 2)->default(0);
                $table->decimal('refunded_amount', 14, 2)->default(0);
                $table->decimal('released_amount', 14, 2)->default(0);
                $table->string('status', 16)->default('held')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_budgets');
        Schema::dropIfExists('agent_youtube_actions');
        Schema::dropIfExists('agent_youtube_identities');
        Schema::dropIfExists('campaign_participation_tasks');

        $drops = [
            'campaign_participations' => ['expires_at', 'last_heartbeat_at'],
            'campaigns' => ['required_tasks', 'channel_url', 'channel_id', 'video_id', 'comment_instructions', 'completion_minutes'],
            'platform_products' => ['task_order', 'completion_minutes'],
        ];

        foreach ($drops as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        if (in_array($column, ['channel_id', 'video_id', 'expires_at'], true)) {
                            $table->dropIndex([$column]);
                        }
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
