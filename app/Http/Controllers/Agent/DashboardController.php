<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\CampaignParticipation;
use App\Support\MemberShell;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $wallet = $user->wallet;

        $activeTasks = CampaignParticipation::query()
            ->where('agent_id', $user->id)
            ->whereIn('status', ['started', 'submitted', 'under_review'])
            ->count();

        $completedTasks = CampaignParticipation::query()
            ->where('agent_id', $user->id)
            ->whereIn('status', ['approved', 'paid'])
            ->count();

        return view('dashboard.agent.overview', [
            'wallet' => $wallet,
            'balanceNgn' => $wallet ? $wallet->availableBalance() : 0,
            'lockedNgn' => $wallet ? (float) $wallet->locked_balance : 0,
            'activeTasks' => $activeTasks,
            'completedTasks' => $completedTasks,
            'kycLevel' => $user->kyc_level,
        ]);
    }

    public function wallet(): View
    {
        $user = auth()->user();
        $wallet = $user->wallet;

        $transactions = $user->transactions()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('dashboard.agent.wallet', [
            'layout' => MemberShell::layout(),
            'prefix' => MemberShell::prefix(),
            'wallet' => $wallet,
            'transactions' => $transactions,
            'kycLevel' => $user->kyc_level,
            'kycRequired' => \App\Models\SystemSetting::kycRequired(),
            'canCreateWallet' => $user->hasApprovedKyc(),
        ]);
    }
}
