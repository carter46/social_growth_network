<?php

namespace App\Http\Controllers;

use App\Models\DomainRegistration;
use App\Models\PlatformProduct;
use App\Models\UserTool;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $wallet = $user->wallet;

        $balanceNgn = $wallet ? $wallet->availableBalance() : 0;
        $lockedNgn = $wallet ? (float) $wallet->locked_balance : 0;
        $totalNgn = $wallet ? (float) $wallet->balance : 0;

        $activeOrdersCount = $user->orders()
            ->where('source', 'platform')
            ->whereIn('status', ['pending', 'processing'])
            ->count();
        $ordersAwaiting = $user->orders()
            ->where('source', 'platform')
            ->where('status', 'processing')
            ->count();

        $myToolsCount = UserTool::query()->ownedBy($user->id)->count()
            + DomainRegistration::query()->forUser($user->id)->count();

        $featuredServices = PlatformProduct::query()
            ->visibleToPublic()
            ->with('heroMedia')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        return view('dashboard.user.overview', [
            'wallet' => $wallet,
            'balanceNgn' => $balanceNgn,
            'lockedNgn' => $lockedNgn,
            'totalNgn' => $totalNgn,
            'activeOrdersCount' => $activeOrdersCount,
            'ordersAwaitingLabel' => $ordersAwaiting > 0 ? "{$ordersAwaiting} in progress" : 'All caught up',
            'myToolsCount' => $myToolsCount,
            'featuredServices' => $featuredServices,
            'kycLevel' => $user->kyc_level,
            'activeCampaignsCount' => \App\Models\Campaign::query()
                ->where('creator_id', $user->id)
                ->whereIn('status', ['active', 'pending_review', 'paused'])
                ->count(),
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

        return view('dashboard.user.wallet', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'kycLevel' => $user->kyc_level,
            'kycRequired' => \App\Models\SystemSetting::kycRequired(),
            'canCreateWallet' => $user->hasApprovedKyc(),
        ]);
    }

    public function serviceOrders(): View
    {
        $orders = auth()->user()
            ->orders()
            ->where('source', 'platform')
            ->with(['items.variant', 'domainRegistrations'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('dashboard.user.orders', [
            'orders' => $orders,
            'source' => 'platform',
            'title' => 'My Orders',
            'breadcrumbParent' => ['Services', route('dashboard.services')],
            'emptyTitle' => 'No service orders yet',
            'emptyDescription' => 'When you buy a platform service, it will appear here.',
            'emptyAction' => ['href' => route('dashboard.services'), 'label' => 'Browse services'],
        ]);
    }

    public function social(): RedirectResponse
    {
        return redirect()->route('dashboard.services');
    }

    public function documents(): RedirectResponse
    {
        return redirect()->route('dashboard.services');
    }
}
