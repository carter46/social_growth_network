<?php

namespace App\Console\Commands;

use App\Models\ReferralCommission;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Referrals\ReferralCommissionService;
use Illuminate\Console\Command;

class ReleasePendingReferralCommissions extends Command
{
    protected $signature = 'referrals:release-pending';

    protected $description = 'Credit pending referral commissions for referrers who now have a wallet';

    public function handle(ReferralCommissionService $commissions): int
    {
        $credited = 0;

        $referrerIds = ReferralCommission::query()
            ->where('status', ReferralCommission::STATUS_PENDING)
            ->whereIn('referrer_id', Wallet::query()->select('user_id'))
            ->distinct()
            ->pluck('referrer_id');

        User::query()->whereIn('id', $referrerIds)->each(function (User $referrer) use ($commissions, &$credited) {
            $credited += $commissions->releasePending($referrer);
        });

        $this->info("Credited {$credited} pending referral commission(s).");

        return self::SUCCESS;
    }
}
