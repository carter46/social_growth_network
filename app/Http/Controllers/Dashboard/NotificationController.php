<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $isAdmin = (bool) $user?->hasRole('admin');
        $isAgent = (bool) $user?->hasRole('agent');

        $notifications = ($user && \Illuminate\Support\Facades\Schema::hasTable('user_notifications'))
            ? $user->notifications()->orderByDesc('created_at')->paginate(20)
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);

        if ($isAdmin) {
            $layout = 'layouts.dashboard-admin';
            $notificationReadAll = 'admin.inbox.read-all';
            $notificationRead = 'admin.inbox.read';
        } elseif ($isAgent) {
            $layout = 'layouts.dashboard-agent';
            $notificationReadAll = 'agent.notifications.read-all';
            $notificationRead = 'agent.notifications.read';
        } else {
            $layout = 'layouts.dashboard-user';
            $notificationReadAll = 'dashboard.notifications.read-all';
            $notificationRead = 'dashboard.notifications.read';
        }

        return view('dashboard.user.notifications', compact(
            'notifications',
            'layout',
            'notificationReadAll',
            'notificationRead',
        ));
    }

    public function markRead(UserNotification $notification): RedirectResponse
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $notification->markAsRead();

        if ($notification->action_url) {
            return redirect($notification->action_url);
        }

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        auth()->user()
            ->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', __('All notifications marked as read.'));
    }
}
