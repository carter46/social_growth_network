<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $agent */
        $agent = $request->user();
        $code = $agent->ensureReferralCode();

        $referred = User::query()->where('referred_by_id', $agent->id);
        $commissions = ReferralCommission::query()->where('referrer_id', $agent->id);

        $stats = [
            'total' => (clone $referred)->count(),
            'agents' => (clone $referred)->role('agent')->count(),
            'creators' => (clone $referred)->role('user')->count(),
            'earned' => (float) (clone $commissions)->where('status', ReferralCommission::STATUS_CREDITED)->sum('amount'),
            'pending' => (float) (clone $commissions)->where('status', ReferralCommission::STATUS_PENDING)->sum('amount'),
        ];

        $referrals = User::query()
            ->where('referred_by_id', $agent->id)
            ->with('roles:id,name')
            ->addSelect([
                'referral_earned' => ReferralCommission::query()
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('referred_user_id', 'users.id')
                    ->where('referrer_id', $agent->id)
                    ->where('status', ReferralCommission::STATUS_CREDITED),
            ])
            ->latest('id')
            ->paginate(15);

        return view('dashboard.agent.referrals.index', [
            'code' => $code,
            'link' => $code ? route('register', ['ref' => $code]) : null,
            'settings' => SystemSetting::referralSettings(),
            'stats' => $stats,
            'referrals' => $referrals,
        ]);
    }
}
