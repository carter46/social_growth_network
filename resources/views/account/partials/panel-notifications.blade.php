<x-dashboard.card>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-text-primary">Notification center</h2>
            <p class="mt-1 text-sm text-text-secondary">
                You have {{ $unreadCount }} unread {{ \Illuminate\Support\Str::plural('notification', $unreadCount) }}.
            </p>
        </div>
        @php
            $inboxHref = ($prefix === 'admin' && \Illuminate\Support\Facades\Route::has('admin.notifications'))
                ? route('admin.notifications')
                : route($prefix === 'agent' ? 'agent.notifications' : 'dashboard.notifications');
        @endphp
        <x-dashboard.button :href="$inboxHref" variant="secondary">
            View notifications
        </x-dashboard.button>
    </div>
</x-dashboard.card>

<x-dashboard.alert type="info">
    Delivery-channel preferences will appear here as additional channels become available.
</x-dashboard.alert>
