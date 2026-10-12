<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payout_routes')) {
            Schema::create('payout_routes', function (Blueprint $table) {
                $table->id();
                $table->string('country', 2);
                $table->string('method', 30)->nullable();
                $table->json('gateway_keys');
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['country', 'method'], 'payout_routes_country_method_uq');
            });
        }

        if (! Schema::hasTable('payout_coverage')) {
            Schema::create('payout_coverage', function (Blueprint $table) {
                $table->id();
                $table->string('gateway_key', 40);
                $table->string('country', 2);
                $table->string('method', 30);
                $table->string('currency', 3);
                $table->boolean('resolve_supported')->default(false);
                $table->decimal('provider_min', 14, 2)->nullable();
                $table->decimal('provider_max', 14, 2)->nullable();
                $table->string('state', 20)->default('unavailable');
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('test_payout_reference', 100)->nullable();
                $table->timestamp('manual_expires_at')->nullable();
                $table->text('note')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['gateway_key', 'country', 'method'], 'payout_coverage_route_uq');
                $table->index(['country', 'method', 'state'], 'payout_coverage_lookup_idx');
            });
        }

        if (! Schema::hasTable('payout_accounts')) {
            Schema::create('payout_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('country', 2);
                $table->string('currency', 3);
                $table->string('method', 30);
                $table->string('bank_code', 40)->nullable();
                $table->string('bank_name', 150)->nullable();
                $table->text('details');
                $table->string('display_mask', 60);
                $table->string('account_holder_name', 150);
                $table->string('verification', 20)->default('pending_review');
                $table->timestamp('verified_at')->nullable();
                $table->string('resolved_by', 40)->nullable();
                $table->string('rejected_reason', 255)->nullable();
                $table->json('provider_beneficiary_ids')->nullable();
                $table->timestamp('bounce_terms_accepted_at')->nullable();
                $table->decimal('bounce_fee_disclosed', 14, 2)->nullable();
                $table->text('bounce_terms_text')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->boolean('active')->default(true);
                $table->foreignId('legacy_user_bank_account_id')->nullable()->constrained('user_bank_accounts')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'active', 'is_primary'], 'payout_accounts_user_primary_idx');
            });
        }

        if (! Schema::hasTable('withdrawal_payout_attempts')) {
            Schema::create('withdrawal_payout_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('withdrawal_id')->constrained('withdrawals')->cascadeOnDelete();
                $table->unsignedSmallInteger('attempt_no');
                $table->string('gateway_key', 40);
                $table->string('reference', 100)->unique();
                $table->string('provider_reference', 128)->nullable();
                $table->string('status', 20)->default('pending');
                // 1 while pending/processing/unknown, null otherwise: at most one live attempt per withdrawal.
                $table->unsignedTinyInteger('live_flag')->nullable();
                $table->string('trigger', 30)->default('initial');
                $table->string('failure_code', 100)->nullable();
                $table->text('failure_reason')->nullable();
                $table->string('failure_class', 30)->nullable();
                $table->boolean('recoverable')->default(false);
                $table->decimal('amount', 14, 2);
                $table->decimal('return_charge', 14, 2)->nullable();
                $table->json('request_snapshot')->nullable();
                $table->json('response_snapshot')->nullable();
                $table->json('quote_snapshot')->nullable();
                $table->foreignId('authorised_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('authorised_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->unique(['withdrawal_id', 'attempt_no'], 'wpa_withdrawal_attempt_uq');
                $table->unique(['withdrawal_id', 'live_flag'], 'wpa_one_live_attempt_uq');
                $table->index(['gateway_key', 'provider_reference'], 'wpa_gateway_provider_ref_idx');
                $table->index(['status', 'updated_at'], 'wpa_status_updated_idx');
            });
        }

        if (! Schema::hasTable('payout_fee_rules')) {
            Schema::create('payout_fee_rules', function (Blueprint $table) {
                $table->id();
                $table->string('country', 2)->nullable();
                $table->string('method', 30)->nullable();
                $table->string('gateway_key', 40)->nullable();
                $table->decimal('flat_fee', 14, 2)->default(0);
                $table->decimal('percent_fee', 6, 3)->default(0);
                $table->decimal('min_fee', 14, 2)->nullable();
                $table->decimal('max_fee', 14, 2)->nullable();
                $table->decimal('verified_provider_fee_flat', 14, 2)->nullable();
                $table->decimal('verified_provider_fee_percent', 6, 3)->nullable();
                $table->string('verified_source', 30)->nullable();
                $table->string('verified_reference', 100)->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('min_withdrawal', 14, 2)->nullable();
                $table->decimal('max_withdrawal', 14, 2)->nullable();
                $table->boolean('active')->default(true);
                $table->text('drift_note')->nullable();
                $table->timestamp('drift_flagged_at')->nullable();
                $table->timestamps();

                $table->index(['country', 'method', 'gateway_key', 'active'], 'payout_fee_rules_match_idx');
            });
        }

        Schema::table('withdrawals', function (Blueprint $table) {
            if (! Schema::hasColumn('withdrawals', 'payout_account_id')) {
                $table->foreignId('payout_account_id')->nullable()->after('user_bank_account_id')->constrained('payout_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('withdrawals', 'destination_country')) {
                $table->string('destination_country', 2)->nullable()->after('payout_account_id');
                $table->string('destination_currency', 3)->nullable()->after('destination_country');
                $table->string('method', 30)->nullable()->after('destination_currency');
                $table->text('destination_details')->nullable()->after('method');
            }
            if (! Schema::hasColumn('withdrawals', 'provider_transfer_id')) {
                $table->string('provider_transfer_id', 128)->nullable()->after('provider_payout_reference');
            }
            if (! Schema::hasColumn('withdrawals', 'fee_amount')) {
                $table->decimal('fee_amount', 14, 2)->default(0)->after('amount');
                $table->decimal('provider_fee', 14, 2)->default(0)->after('fee_amount');
                $table->decimal('platform_fee', 14, 2)->default(0)->after('provider_fee');
                $table->decimal('net_amount', 14, 2)->nullable()->after('platform_fee');
                $table->decimal('absorbed_fee', 14, 2)->default(0)->after('net_amount');
                $table->foreignId('fee_rule_id')->nullable()->after('absorbed_fee')->constrained('payout_fee_rules')->nullOnDelete();
                $table->json('quote_snapshot')->nullable()->after('fee_rule_id');
                $table->json('requote_snapshot')->nullable()->after('quote_snapshot');
                $table->timestamp('confirmation_expires_at')->nullable()->after('requote_snapshot');
            }
            if (! Schema::hasColumn('withdrawals', 'bounce_fee_amount')) {
                $table->decimal('bounce_fee_amount', 14, 2)->default(0)->after('confirmation_expires_at');
                $table->string('bounce_failure_code', 100)->nullable()->after('bounce_fee_amount');
                $table->timestamp('bounce_waived_at')->nullable()->after('bounce_failure_code');
                $table->foreignId('bounce_waived_by')->nullable()->after('bounce_waived_at')->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('currency_conversion_archive')) {
            Schema::create('currency_conversion_archive', function (Blueprint $table) {
                $table->id();
                $table->string('batch', 40);
                $table->string('table_name', 64);
                $table->unsignedBigInteger('row_id')->nullable();
                $table->string('column_name', 191);
                $table->string('original_value', 64)->nullable();
                $table->string('new_value', 64)->nullable();
                $table->decimal('rate', 18, 6);
                $table->timestamp('created_at')->nullable();

                $table->index(['table_name', 'row_id'], 'cca_table_row_idx');
                $table->index('batch', 'cca_batch_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_conversion_archive');

        Schema::table('withdrawals', function (Blueprint $table) {
            foreach (['bounce_waived_by', 'fee_rule_id', 'payout_account_id'] as $fk) {
                if (Schema::hasColumn('withdrawals', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
        });
        Schema::table('withdrawals', function (Blueprint $table) {
            $columns = array_filter([
                'destination_country', 'destination_currency', 'method', 'destination_details', 'provider_transfer_id',
                'fee_amount', 'provider_fee', 'platform_fee', 'net_amount', 'absorbed_fee', 'quote_snapshot',
                'requote_snapshot', 'confirmation_expires_at', 'bounce_fee_amount', 'bounce_failure_code', 'bounce_waived_at',
            ], fn ($c) => Schema::hasColumn('withdrawals', $c));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('payout_fee_rules');
        Schema::dropIfExists('withdrawal_payout_attempts');
        Schema::dropIfExists('payout_accounts');
        Schema::dropIfExists('payout_coverage');
        Schema::dropIfExists('payout_routes');
    }
};
