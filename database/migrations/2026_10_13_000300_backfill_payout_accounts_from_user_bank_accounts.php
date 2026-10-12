<?php

use App\Models\PayoutAccount;
use App\Models\UserBankAccount;
use App\Support\Payouts\AccountFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Each member's active Nigerian bank (verified by name lookup) becomes their primary payout account.
 * The old rows stay for history; the link is kept in legacy_user_bank_account_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payout_accounts') || ! Schema::hasTable('user_bank_accounts')) {
            return;
        }

        UserBankAccount::query()
            ->where('active', true)
            ->orderBy('id')
            ->chunkById(200, function ($banks) {
                foreach ($banks as $bank) {
                    if (PayoutAccount::withTrashed()->where('legacy_user_bank_account_id', $bank->id)->exists()) {
                        continue;
                    }
                    $number = AccountFormat::digits((string) $bank->account_number);
                    if ($number === '') {
                        continue;
                    }
                    $hasPrimary = PayoutAccount::query()->where('user_id', $bank->user_id)->where('active', true)->where('is_primary', true)->exists();

                    PayoutAccount::create([
                        'user_id' => $bank->user_id,
                        'country' => 'NG',
                        'currency' => 'NGN',
                        'method' => 'bank_account',
                        'bank_code' => $bank->bank_code,
                        'bank_name' => $bank->bank_name,
                        'details' => ['account_number' => $number],
                        'display_mask' => AccountFormat::mask($number),
                        'account_holder_name' => (string) ($bank->verified_name ?: ''),
                        'verification' => $bank->verified_at ? PayoutAccount::RESOLVED : PayoutAccount::PENDING_REVIEW,
                        'verified_at' => $bank->verified_at,
                        'resolved_by' => $bank->verified_by ? 'legacy_'.$bank->verified_by : null,
                        'is_primary' => ! $hasPrimary,
                        'active' => true,
                        'legacy_user_bank_account_id' => $bank->id,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('payout_accounts')) {
            PayoutAccount::withTrashed()->whereNotNull('legacy_user_bank_account_id')->forceDelete();
        }
    }
};
