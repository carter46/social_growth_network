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

        $notifications = ($user && \Illuminate\Support\Facades\Schema::hasTable('user_notifications'))
            ? $user->notifications()->orderByDesc('created_at')->paginate(20)
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);

        $layout = $isAdmin ? 'layouts.dashboard-admin' : 'layouts.dashboard-user';
        $notificationReadAll = $isAdmin ? 'admin.inbox.read-all' : 'dashboard.notifications.read-all';
        $notificationRead = $isAdmin ? 'admin.inbox.read' : 'dashboard.notifications.read';

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
