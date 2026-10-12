<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_webhooks', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_webhooks', 'attempts')) {
                $table->unsignedSmallInteger('attempts')->default(0);
            }
            if (! Schema::hasColumn('payment_webhooks', 'next_retry_at')) {
                $table->timestamp('next_retry_at')->nullable()->index();
            }
        });

        foreach (['wallet_fundings', 'orders', 'withdrawal_payout_attempts'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'last_checked_at')) {
                    $table->timestamp('last_checked_at')->nullable()->index();
                }
            });
        }

        Schema::table('withdrawal_payout_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('withdrawal_payout_attempts', 'reclaim_pending')) {
                $table->boolean('reclaim_pending')->default(false)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_payout_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('withdrawal_payout_attempts', 'reclaim_pending')) {
                $table->dropIndex(['reclaim_pending']);
                $table->dropColumn('reclaim_pending');
            }
        });

        foreach (['wallet_fundings', 'orders', 'withdrawal_payout_attempts'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (Schema::hasColumn($name, 'last_checked_at')) {
                    $table->dropIndex(['last_checked_at']);
                    $table->dropColumn('last_checked_at');
                }
            });
        }

        Schema::table('payment_webhooks', function (Blueprint $table) {
            if (Schema::hasColumn('payment_webhooks', 'next_retry_at')) {
                $table->dropIndex(['next_retry_at']);
                $table->dropColumn('next_retry_at');
            }
            if (Schema::hasColumn('payment_webhooks', 'attempts')) {
                $table->dropColumn('attempts');
            }
        });
    }
};
