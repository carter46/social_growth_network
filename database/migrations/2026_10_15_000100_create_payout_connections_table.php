<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payout_connections')) {
            return;
        }

        Schema::create('payout_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('account_id', 100)->nullable();
            $table->string('status', 32)->default('not_started');
            $table->json('capabilities')->nullable();
            $table->json('requirements')->nullable();
            $table->string('status_reason', 255)->nullable();
            $table->timestamp('last_event_at', 6)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['provider', 'account_id']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_connections');
    }
};
