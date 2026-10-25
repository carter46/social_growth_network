<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_social_follow_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('status');
        });

        Schema::create('agent_social_follow_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('agent_social_follow_submissions')->cascadeOnDelete();
            $table->unsignedBigInteger('social_link_id')->nullable();
            $table->string('platform', 80);
            $table->string('url', 500);
            $table->unsignedBigInteger('icon_media_id')->nullable();
            $table->string('proof_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_social_follow_proofs');
        Schema::dropIfExists('agent_social_follow_submissions');
    }
};
