<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purged_user_records')) {
            Schema::create('purged_user_records', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('original_user_id')->unique();
                $table->string('email')->nullable()->index();
                $table->string('name')->nullable()->index();
                $table->string('username')->nullable()->index();
                $table->string('phone', 32)->nullable()->index();
                $table->string('role', 32)->nullable();
                $table->timestamp('registered_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('trashed_at')->nullable();
                $table->timestamp('purged_at')->nullable()->index();
                $table->decimal('wallet_balance_at_purge', 18, 4)->nullable();
                $table->decimal('wallet_locked_at_purge', 18, 4)->nullable();
                $table->json('transaction_refs')->nullable();
                $table->json('login_security_refs')->nullable();
                $table->json('snapshot')->nullable();
                $table->string('purge_reason', 80)->nullable();
                $table->string('purge_process', 120)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('account_reactivation_codes')) {
            Schema::create('account_reactivation_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('email');
                $table->string('code_hash');
                $table->timestamp('expires_at');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('used_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'expires_at']);
                $table->index(['email', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_reactivation_codes');
        Schema::dropIfExists('purged_user_records');
    }
};
