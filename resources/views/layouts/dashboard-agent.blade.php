<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $dashboardThemeResolved ?? 'light' }}" data-theme-preference="{{ $dashboardThemePreference ?? 'light' }}">
@include('partials.dashboard.shell-head', ['defaultTitle' => 'Agent'])
<body class="dashboard-shell bg-surface font-sans text-text-primary h-dvh overflow-hidden" data-dashboard-shell="agent" x-data="mobileNav" @keydown.escape.window="open && close()">
    <script>
        try {
            if (sessionStorage.getItem('dashboard-page-loader') === '1') {
                document.body.classList.add('dashboard-page-loading');
            }
        } catch (e) {}
    </script>
    @include('partials.dashboard.shell-skip-link')

    <div class="relative flex h-full min-h-0 w-full flex-col">
        @if (session('impersonating'))
            @include('partials.impersonation-banner', [
                'impersonatorName' => $impersonatorName ?? null,
            ])
        @endif

        <x-dashboard.topbar context="Agent" :home-route="route('agent')" />

        <div class="relative flex min-h-0 flex-1">
            @include('partials.dashboard.shell-mobile-overlay')
            @include('partials.sidebar-agent')

            <x-dashboard.scroll-main>
                @yield('content')
            </x-dashboard.scroll-main>
        </div>
    </div>

    <x-dashboard.command-palette role="agent" />
    <x-ui.toast />
    @stack('scripts')
    @include('partials.dashboard.unregister-service-worker')
</body>
</html>
