<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_products')) {
            // Retire youtube-subscribers from the active catalog (16-product set).
            $subscriberIds = DB::table('platform_products')->where('slug', 'youtube-subscribers')->pluck('id');
            if ($subscriberIds->isNotEmpty()) {
                $referenced = Schema::hasTable('campaigns')
                    && DB::table('campaigns')->whereIn('platform_product_id', $subscriberIds)->exists();
                if ($referenced) {
                    DB::table('platform_products')
                        ->whereIn('id', $subscriberIds)
                        ->update(['status' => 'draft']);
                } else {
                    DB::table('platform_products')->whereIn('id', $subscriberIds)->delete();
                }
            }
        }

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (! Schema::hasColumn('campaigns', 'engagement_metric')) {
                    $table->string('engagement_metric', 32)->nullable()->after('target_url');
                }
                if (! Schema::hasColumn('campaigns', 'baseline_count')) {
                    $table->unsignedBigInteger('baseline_count')->nullable()->after('engagement_metric');
                }
                if (! Schema::hasColumn('campaigns', 'baseline_captured_at')) {
                    $table->timestamp('baseline_captured_at')->nullable()->after('baseline_count');
                }
                if (! Schema::hasColumn('campaigns', 'last_verified_count')) {
                    $table->unsignedBigInteger('last_verified_count')->nullable()->after('baseline_captured_at');
                }
                if (! Schema::hasColumn('campaigns', 'verification_mode')) {
                    $table->string('verification_mode', 32)->nullable()->after('last_verified_count');
                }
                if (! Schema::hasColumn('campaigns', 'verification_locked_participation_id')) {
                    // Soft pointer (no FK): avoids circular FK with campaign_participations.
                    $table->unsignedBigInteger('verification_locked_participation_id')->nullable()->after('verification_mode');
                }
            });
        }

        if (Schema::hasTable('campaign_participations')) {
            Schema::table('campaign_participations', function (Blueprint $table) {
                if (! Schema::hasColumn('campaign_participations', 'pre_count')) {
                    $table->unsignedBigInteger('pre_count')->nullable()->after('proof_notes');
                }
                if (! Schema::hasColumn('campaign_participations', 'post_count')) {
                    $table->unsignedBigInteger('post_count')->nullable()->after('pre_count');
                }
                if (! Schema::hasColumn('campaign_participations', 'verify_attempts')) {
                    $table->unsignedTinyInteger('verify_attempts')->default(0)->after('post_count');
                }
                if (! Schema::hasColumn('campaign_participations', 'verification_started_at')) {
                    $table->timestamp('verification_started_at')->nullable()->after('verify_attempts');
                }
            });
        }

        if (! Schema::hasTable('campaign_watch_sessions')) {
            Schema::create('campaign_watch_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_participation_id')->constrained('campaign_participations')->cascadeOnDelete();
                $table->string('token', 64)->unique();
                $table->unsignedInteger('required_seconds');
                $table->timestamp('started_at');
                $table->timestamp('claimed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_watch_sessions');

        if (Schema::hasTable('campaign_participations')) {
            Schema::table('campaign_participations', function (Blueprint $table) {
                foreach (['verification_started_at', 'verify_attempts', 'post_count', 'pre_count'] as $col) {
                    if (Schema::hasColumn('campaign_participations', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                foreach ([
                    'verification_locked_participation_id',
                    'verification_mode',
                    'last_verified_count',
                    'baseline_captured_at',
                    'baseline_count',
                    'engagement_metric',
                ] as $col) {
                    if (Schema::hasColumn('campaigns', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
