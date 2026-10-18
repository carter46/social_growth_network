@props([
    'prefix' => null,
    'compact' => false,
])

@php
    $user = auth()->user();
    $isAdmin = $user->hasRole('admin');
    $prefix = $prefix ?: ($isAdmin ? 'admin' : 'dashboard');
    $prefix = \Illuminate\Support\Str::before($prefix, '.account');
    $items = [
        ['href' => route($prefix.'.account.profile'), 'label' => 'Profile', 'icon' => 'user'],
        ['href' => route($prefix.'.account.security'), 'label' => 'Security', 'icon' => 'lock'],
    ];
    if (! $isAdmin && \Illuminate\Support\Facades\Route::has($prefix.'.youtube-channel.show') && \App\Services\Campaigns\YoutubeIdentityService::enabled()) {
        $items[] = ['href' => route($prefix.'.youtube-channel.show'), 'label' => 'YouTube channel', 'icon' => 'verified'];
    }
    $items[] = ['href' => route($prefix.'.account.notifications'), 'label' => 'Notifications', 'icon' => 'notifications'];
    $items[] = ['href' => route($prefix.'.account.preferences'), 'label' => 'Preferences', 'icon' => 'tune'];
    $items[] = ['href' => route($prefix.'.account.sessions'), 'label' => 'Sessions', 'icon' => 'monitoring'];
@endphp

<div class="relative" x-data="accountMenu" data-account-menu>
    <button
        type="button"
        class="flex min-h-11 items-center gap-3 rounded-xl px-1.5 py-1 text-left transition-colors hover:bg-muted/40 focus-ring"
        @click="toggle()"
        :aria-expanded="open.toString()"
        aria-haspopup="menu"
        aria-label="Account menu"
    >
        @unless ($compact)
            <span class="hidden text-right sm:block">
                <span class="block max-w-40 truncate text-sm font-semibold text-text-primary">
                    {{ $user->username ?: ($user->name ?: $user->email) }}
                </span>
                <span class="mt-0.5 block text-xs text-text-secondary">{{ $isAdmin ? 'Admin' : 'Member' }}</span>
            </span>
        @endunless
        <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-border-default bg-primary/20 text-xs font-semibold text-primary">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
            @else
                <span aria-hidden="true">{{ $user->initials() }}</span>
            @endif
        </span>
        @unless ($compact)
            <x-ui.icon name="chevron-down" class="hidden h-4 w-4 text-text-secondary sm:inline-flex" />
        @endunless
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top.right
        @click.outside="close()"
        @keydown.escape.window="close(true)"
        class="absolute right-0 z-50 mt-2 w-60 overflow-hidden rounded-xl border border-border-default bg-elevated p-2 shadow-xl"
        role="menu"
    >
        @foreach ($items as $item)
            <a
                href="{{ $item['href'] }}"
                class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-muted/60 hover:text-text-primary focus-ring"
                role="menuitem"
                @click="close()"
            >
                <x-ui.icon :name="$item['icon']" class="h-4 w-4" />
                {{ $item['label'] }}
            </a>
        @endforeach

        <div class="my-1 border-t border-border-default"></div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium text-danger hover:bg-danger/10 focus-ring" role="menuitem">
                <x-ui.icon name="logout" class="h-4 w-4" />
                Logout
            </button>
        </form>
    </div>
</div>
