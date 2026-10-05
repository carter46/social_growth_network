@props([
    'limit' => 5,
])

@php
    $isAdmin = auth()->user()?->hasRole('admin') ?? false;
    $useAdminNotifications = $isAdmin && \Illuminate\Support\Facades\Route::has('admin.notifications');

    try {
        if ($useAdminNotifications) {
            $unread = \Illuminate\Support\Facades\Schema::hasTable('admin_notifications')
                ? \App\Models\AdminNotification::unreadCount()
                : 0;
            $items = \Illuminate\Support\Facades\Schema::hasTable('admin_notifications')
                ? \App\Models\AdminNotification::query()->orderByDesc('created_at')->limit($limit)->get()
                : collect();
            $inboxRoute = route('admin.notifications');
            $readRoute = fn ($n) => route('admin.notifications.read', $n);
        } else {
            $unread = auth()->user()?->unreadNotificationsCount() ?? 0;
            $items = (\Illuminate\Support\Facades\Schema::hasTable('user_notifications') && auth()->user())
                ? auth()->user()->notifications()->orderByDesc('created_at')->limit($limit)->get()
                : collect();
            $inboxRoute = \App\Support\MemberShell::userRoute('notifications');
            $readRoute = null;
        }
    } catch (\Throwable) {
        $unread = 0;
        $items = collect();
        $inboxRoute = $useAdminNotifications
            ? (\Illuminate\Support\Facades\Route::has('admin.notifications') ? route('admin.notifications') : '#')
            : (\Illuminate\Support\Facades\Route::has('dashboard.notifications') ? route('dashboard.notifications') : '#');
        $readRoute = null;
    }
@endphp

<div
    class="relative"
    x-data="notificationMenu()"
    @keydown.escape.window="open && close()"
    data-notification-menu
>
    <button
        type="button"
        class="relative inline-flex min-h-10 min-w-10 items-center justify-center rounded-xl bg-muted/40 text-text-secondary transition-colors hover:text-primary focus-ring"
        @click="toggle()"
        :aria-expanded="open.toString()"
        aria-haspopup="menu"
        aria-label="Notifications"
    >
        <x-ui.icon name="notifications" class="w-5 h-5" />
        @if ($unread > 0)
            <span class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-white">
                {{ $unread > 9 ? '9+' : $unread }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="close()"
        class="absolute right-0 z-50 mt-2 w-80 max-w-[90vw] overflow-hidden rounded-2xl border border-border-default bg-surface shadow-panel"
        role="menu"
    >
        <div class="flex items-center justify-between border-b border-border-default px-4 py-3">
            <p class="text-sm font-semibold text-text-primary">Notifications</p>
            @if ($unread > 0)
                <span class="text-xs text-text-muted">{{ $unread }} unread</span>
            @endif
        </div>
        <div class="max-h-80 overflow-y-auto">
            @forelse ($items as $notification)
                @if ($useAdminNotifications)
                    <form method="POST" action="{{ $readRoute($notification) }}" class="block">
                        @csrf
                        <button
                            type="submit"
                            class="w-full text-left border-b border-border-default px-4 py-3 last:border-b-0 hover:bg-muted/40 {{ $notification->read_at ? '' : 'bg-primary/5' }}"
                            @click="close()"
                        >
                            <p class="text-sm font-medium text-text-primary">{{ $notification->title }}</p>
                            @if ($notification->body)
                                <p class="mt-0.5 line-clamp-2 text-xs text-text-secondary">{{ $notification->body }}</p>
                            @endif
                        </button>
                    </form>
                @else
                    <a
                        href="{{ $notification->action_url ?: $inboxRoute }}"
                        class="block border-b border-border-default px-4 py-3 last:border-b-0 hover:bg-muted/40 {{ $notification->read_at ? '' : 'bg-primary/5' }}"
                        @click="close()"
                    >
                        <p class="text-sm font-medium text-text-primary">{{ $notification->title }}</p>
                        @if ($notification->body)
                            <p class="mt-0.5 line-clamp-2 text-xs text-text-secondary">{{ $notification->body }}</p>
                        @endif
                    </a>
                @endif
            @empty
                <p class="px-4 py-6 text-sm text-text-muted">No notifications yet.</p>
            @endforelse
        </div>
        <div class="border-t border-border-default px-4 py-2">
            <a href="{{ $inboxRoute }}" class="text-sm font-medium text-primary hover:underline" @click="close()">View all</a>
        </div>
    </div>
</div>
