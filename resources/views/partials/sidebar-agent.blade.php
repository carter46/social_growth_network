{{-- Hide drawer before Alpine boots so mobile doesn't flash open, without relying on new Tailwind utilities. --}}
<style>
    @media (max-width: 1023.98px) {
        #sidebar:not([data-nav-ready]) {
            transform: translate3d(-100%, 0, 0);
        }
    }
</style>
<aside
    id="sidebar"
    x-ref="mobileDrawer"
    x-init="$el.setAttribute('data-nav-ready', '1')"
    class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[88vw] flex-col border-r border-border-default bg-sidebar shadow-panel transform transition-transform duration-200 motion-reduce:transition-none lg:static lg:h-full lg:min-h-0 lg:w-64 lg:max-w-none lg:translate-x-0 lg:shadow-none"
    :class="open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    :aria-hidden="(!open && window.innerWidth < 1024).toString()"
    @keydown.tab="trapFocus($event)"
>
    <div class="flex shrink-0 items-center justify-between gap-2 px-4 pb-2 pt-4 lg:hidden">
        <p class="text-sm font-semibold text-text-primary">Menu</p>
        <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-text-secondary hover:bg-muted/50 hover:text-text-primary focus-ring" @click="close()" aria-label="Close menu">
            <x-ui.icon name="x" class="w-5 h-5" />
        </button>
    </div>

    <div class="hidden shrink-0 px-5 pb-3 pt-5 lg:block">
        @include('partials.dashboard-brand', ['href' => route('agent')])
    </div>

    <div class="flex min-h-0 flex-1 flex-col px-3 pb-4 pt-2">
        <x-dashboard.nav role="agent" :user="auth()->user()" label="Agent navigation" />
    </div>

    <div class="shrink-0 border-t border-border-default p-4 lg:hidden" data-mobile-theme-switcher>
        <p class="mb-2 px-1 text-[10px] font-bold uppercase tracking-wider text-text-muted">Appearance</p>
        <x-dashboard.theme-switcher />
    </div>
</aside>
