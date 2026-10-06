<?php

use App\Services\Referrals\ReferralCodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'referral_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('referral_code', 16)->nullable()->unique();
                $table->foreignId('referred_by_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('referral_commissions')) {
            Schema::create('referral_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('kind', 32);
                $table->string('source_type', 64);
                $table->unsignedBigInteger('source_id');
                $table->decimal('base_amount', 14, 2);
                $table->decimal('rate', 5, 2);
                $table->decimal('amount', 14, 2);
                $table->string('status', 16)->default('pending');
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->foreignId('reversal_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->timestamp('credited_at')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();

                $table->unique(['kind', 'source_type', 'source_id'], 'referral_commissions_source_unique');
                $table->index(['referrer_id', 'status']);
                $table->index(['referrer_id', 'referred_user_id']);
            });
        }

        $this->backfillAgentCodes();
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');

        if (Schema::hasColumn('users', 'referral_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('referred_by_id');
                $table->dropUnique(['referral_code']);
                $table->dropColumn('referral_code');
            });
        }
    }

    private function backfillAgentCodes(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $generator = new ReferralCodeGenerator;

        $agentIds = DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'agent')
            ->whereNull('users.referral_code')
            ->orderBy('users.id')
            ->pluck('users.id');

        foreach ($agentIds as $id) {
            DB::table('users')->where('id', $id)->update(['referral_code' => $generator->generate()]);
        }
    }
};
