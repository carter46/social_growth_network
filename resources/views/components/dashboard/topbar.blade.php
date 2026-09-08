@props([
    'context' => 'Dashboard',
    'homeRoute' => null,
    'destinations' => [],
])

@php
    $user = auth()->user();
    $homeRoute = $homeRoute ?? ($user?->homeRoute() ? url($user->homeRoute()) : route('dashboard'));
    if ($user?->hasRole('admin')) {
        $role = 'admin';
        $accountPrefix = 'admin.account';
    } elseif ($user?->hasRole('agent')) {
        $role = 'agent';
        $accountPrefix = 'agent.account';
    } else {
        $role = 'user';
        $accountPrefix = 'dashboard.account';
    }
    $destinations = $destinations ?: \App\Support\DashboardNavigation::searchIndex($role, $user);
@endphp

{{-- Shared topbar: logo lives in the sidebar only (admin + user). --}}
<header class="h-16 shrink-0 border-b border-border-default bg-header z-30">
    {{-- Mobile topbar --}}
    <div class="flex h-full items-center justify-between gap-2 px-4 lg:hidden">
        <div class="flex min-w-0 items-center gap-2">
            <button
                type="button"
                class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-text-secondary hover:text-text-primary focus-ring"
                aria-label="Open menu"
                aria-controls="sidebar"
                @click="toggle()"
                :aria-expanded="open.toString()"
            >
                <x-ui.icon name="menu" class="w-6 h-6" />
            </button>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-text-primary">{{ $context }}</p>
            </div>
        </div>
        <div class="flex items-center gap-1">
            <x-dashboard.notification-menu />
            <x-dashboard.account-menu :prefix="$accountPrefix" compact />
        </div>
    </div>

    {{-- Desktop topbar — no brand logo (sidebar owns branding) --}}
    <div class="hidden h-full items-center justify-between gap-4 px-4 sm:px-6 lg:flex lg:px-10">
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-text-primary">{{ $context }}</p>
        </div>
        <div class="flex items-center gap-2 sm:gap-3">
            <div class="hidden lg:block" data-desktop-theme-switcher>
                <x-dashboard.theme-switcher />
            </div>
            <x-dashboard.notification-menu />
            <div class="hidden sm:block mx-1 h-10 w-px bg-border-default"></div>
            <x-dashboard.account-menu :prefix="$accountPrefix" />
        </div>
    </div>
</header>
