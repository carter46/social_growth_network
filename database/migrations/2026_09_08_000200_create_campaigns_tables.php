<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_products')) {
            Schema::table('platform_products', function (Blueprint $table) {
                if (! Schema::hasColumn('platform_products', 'is_campaign')) {
                    $table->boolean('is_campaign')->default(false)->after('base_price');
                }
                if (! Schema::hasColumn('platform_products', 'agent_reward_per_completion')) {
                    $table->decimal('agent_reward_per_completion', 12, 2)->nullable()->after('is_campaign');
                }
                if (! Schema::hasColumn('platform_products', 'estimated_minutes')) {
                    $table->unsignedInteger('estimated_minutes')->nullable()->after('agent_reward_per_completion');
                }
            });
        }

        if (! Schema::hasTable('campaigns')) {
            Schema::create('campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
                $table->foreignId('platform_product_id')->nullable()->constrained('platform_products')->nullOnDelete();
                $table->foreignId('platform_product_variant_id')->nullable()->constrained('platform_product_variants')->nullOnDelete();
                $table->string('title');
                $table->string('target_url')->nullable();
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('completed_count')->default(0);
                $table->decimal('locked_creator_price', 12, 2);
                $table->decimal('locked_agent_reward', 12, 2);
                $table->string('status')->index();
                $table->unsignedInteger('estimated_minutes')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('campaign_participations')) {
            Schema::create('campaign_participations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
                $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
                $table->string('status')->index();
                $table->string('proof_url')->nullable();
                $table->text('proof_notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->decimal('reward_amount', 12, 2);
                $table->timestamps();

                $table->unique(['campaign_id', 'agent_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_participations');
        Schema::dropIfExists('campaigns');

        if (Schema::hasTable('platform_products')) {
            Schema::table('platform_products', function (Blueprint $table) {
                foreach (['estimated_minutes', 'agent_reward_per_completion', 'is_campaign'] as $column) {
                    if (Schema::hasColumn('platform_products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
