@extends('layouts.marketing')

@section('title', 'Help Center')

@section('content')
@php
    $resolvedCategories = collect($categories)->map(function (array $cat) {
        $slug = $cat['article'] ?? $cat['key'] ?? null;
        $href = $slug
            ? route('help.article', $slug)
            : route('help');

        return array_merge($cat, ['resolved_href' => $href]);
    })->values();

    $supportHref = auth()->check()
        ? route('dashboard.support.index')
        : route('login');

    $toneBg = [
        'primary' => 'bg-primary/10 text-accent',
        'accent' => 'bg-accent/10 text-accent',
        'warning' => 'bg-warning/10 text-warning',
        'success' => 'bg-success/10 text-success',
        'info' => 'bg-sky-500/10 text-sky-400',
    ];
@endphp

@include('partials.marketing.page-header', [
    'breadcrumbs' => [
        ['label' => 'Home', 'href' => route('home')],
        ['label' => 'Help Center'],
    ],
    'title' => 'Help Center',
    'subtitle' => 'Search our knowledge base or get in touch with support for wallet, services, and account help.',
    'image' => 'assets/images/helpcenter.jpg',
])

<div
    x-data="{
        q: '',
        open: false,
        active: -1,
        index: {{ Js::from($searchIndex ?? []) }},
        get suggestions() {
            const term = (this.q || '').trim().toLowerCase();
            if (!term) return [];
            return this.index
                .filter((item) => String(item.text || '').toLowerCase().includes(term))
                .slice(0, 8);
        },
        select(item) {
            if (!item?.href) return;
            window.location.href = item.href;
        },
        onKey(e) {
            const list = this.suggestions;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.open = true;
                this.active = Math.min(this.active + 1, list.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.active = Math.max(this.active - 1, 0);
            } else if (e.key === 'Enter') {
                if (this.active >= 0 && list[this.active]) {
                    e.preventDefault();
                    this.select(list[this.active]);
                }
            } else if (e.key === 'Escape') {
                this.open = false;
                this.active = -1;
                this.q = '';
            }
        },
        init() {
            window.addEventListener('keydown', (e) => {
                if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
                    e.preventDefault();
                    this.$refs.search?.focus();
                }
            });
            this.$watch('q', () => { this.open = (this.q || '').trim().length > 0; this.active = -1; });
        }
    }"
    @click.outside="open = false"
>

<section class="max-w-marketing mx-auto px-5 sm:px-6 -mt-2 mb-10 sm:mb-12">
    <div class="max-w-2xl mx-auto relative">
        <span class="absolute left-5 top-1/2 -translate-y-1/2 text-accent pointer-events-none z-10">
            <x-ui.icon name="search" class="w-5 h-5" />
        </span>
        <label for="help-search" class="sr-only">Search help center</label>
        <input
            id="help-search"
            x-ref="search"
            x-model="q"
            @keydown="onKey($event)"
            @focus="open = (q || '').trim().length > 0"
            type="search"
            autocomplete="off"
            placeholder="Search our knowledge base..."
            class="w-full pl-14 pr-16 py-4 sm:py-5 bg-elevated rounded-full border border-border-default focus:border-accent focus:ring-1 focus:ring-accent/40 text-sm sm:text-base text-text-primary placeholder:text-text-muted transition-all shadow-xl"
            role="combobox"
            :aria-expanded="open"
            aria-controls="help-search-results"
            aria-autocomplete="list"
        >
        <kbd class="absolute right-5 top-1/2 -translate-y-1/2 px-2 py-1 bg-muted rounded text-[10px] font-medium text-text-muted border border-border-subtle hidden sm:inline-block">/</kbd>

        <div
            id="help-search-results"
            x-show="open"
            x-cloak
            class="absolute left-0 right-0 top-full mt-2 z-40 rounded-2xl border border-border-default bg-elevated shadow-2xl overflow-hidden"
            role="listbox"
        >
            <template x-if="suggestions.length === 0 && (q || '').trim()">
                <p class="px-5 py-4 text-sm text-text-secondary">No results for “<span x-text="(q || '').trim()"></span>”.</p>
            </template>
            <ul class="max-h-80 overflow-y-auto py-2">
                <template x-for="(item, i) in suggestions" :key="item.href + item.label">
                    <li role="option" :aria-selected="active === i">
                        <button
                            type="button"
                            class="w-full text-left px-5 py-3 hover:bg-muted/60 transition-colors flex flex-col gap-0.5"
                            :class="active === i ? 'bg-muted/60' : ''"
                            @click="select(item)"
                            @mouseenter="active = i"
                        >
                            <span class="text-sm font-medium text-white" x-text="item.label"></span>
                            <span class="text-[11px] text-text-muted" x-text="item.hint || item.type"></span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</section>

{{-- Categories (always visible — not filtered by search) --}}
<section class="max-w-marketing mx-auto px-5 sm:px-6 pb-14 sm:pb-16">
    <div class="mb-6 sm:mb-8">
        <h2 class="font-display text-xl sm:text-2xl font-semibold text-accent mb-1">Browse categories</h2>
        <p class="text-sm text-text-secondary">Explore common topics and guides</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
        @foreach($resolvedCategories as $cat)
            <a
                href="{{ $cat['resolved_href'] }}"
                class="group glassmorphism p-6 sm:p-7 rounded-xl flex flex-col items-start hover:border-accent/40 transition-all duration-300 hover:-translate-y-1"
            >
                <div class="w-14 h-14 rounded-full flex items-center justify-center mb-4 {{ $toneBg[$cat['tone'] ?? 'primary'] ?? $toneBg['primary'] }}">
                    <x-ui.icon :name="$cat['icon']" class="w-6 h-6" />
                </div>
                <h3 class="font-display text-lg font-semibold text-white mb-2">{{ $cat['title'] }}</h3>
                <p class="text-sm text-text-secondary leading-relaxed mb-5 flex-1">{{ $cat['description'] }}</p>
                <span class="mt-auto text-xs font-bold uppercase tracking-wider text-accent inline-flex items-center gap-2 group-hover:gap-3 transition-all">
                    {{ $cat['cta'] }}
                    <x-ui.icon name="arrow-right" class="w-4 h-4" />
                </span>
            </a>
        @endforeach
    </div>
</section>

@if(count($faqs))
<section id="faqs" class="bg-elevated/40 border-y border-border-subtle py-14 sm:py-16">
    <div class="px-5 sm:px-6 max-w-3xl mx-auto">
        <h2 class="font-display text-xl sm:text-2xl font-semibold text-white text-center mb-8">
            Frequently asked questions
        </h2>
        <div class="space-y-3">
            @foreach($faqs as $faq)
                <details
                    class="group glassmorphism rounded-xl overflow-hidden [&_summary::-webkit-details-marker]:hidden"
                    @if($loop->first) open @endif
                >
                    <summary class="flex justify-between items-center gap-4 p-4 sm:p-5 cursor-pointer hover:bg-white/5 transition-colors">
                        <span class="font-semibold text-sm sm:text-base text-white text-left">{{ $faq['q'] ?? '' }}</span>
                        <span class="text-text-secondary transition-transform group-open:rotate-180 shrink-0">
                            <x-ui.icon name="chevron-down" class="w-5 h-5" />
                        </span>
                    </summary>
                    <div class="px-4 sm:px-5 pb-4 sm:pb-5 text-sm text-text-secondary leading-relaxed border-t border-border-subtle pt-3">
                        {{ $faq['a'] ?? '' }}
                        @if(! empty($faq['article']))
                            <p class="mt-3">
                                <a href="{{ route('help.article', $faq['article']) }}{{ ! empty($faq['section']) ? '#'.$faq['section'] : '' }}" class="text-accent font-medium hover:underline">
                                    Read the full guide
                                </a>
                            </p>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Still need help: two CTAs --}}
<section class="max-w-marketing mx-auto px-5 sm:px-6 py-14 sm:py-16 pb-16 sm:pb-20">
    <div class="rounded-2xl sm:rounded-3xl bg-gradient-to-br from-elevated to-muted/40 border border-border-subtle p-6 sm:p-10 lg:p-12 overflow-hidden relative">
        <div class="absolute -bottom-20 -right-20 w-64 h-64 bg-primary/20 rounded-full blur-[100px] pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -top-20 -left-20 w-64 h-64 bg-accent/10 rounded-full blur-[100px] pointer-events-none" aria-hidden="true"></div>
        <div class="relative z-10 text-center">
            <h2 class="font-display text-2xl sm:text-3xl lg:text-4xl font-bold text-white mb-3 sm:mb-4">Still need help?</h2>
            <p class="text-sm sm:text-base text-text-secondary mb-8 max-w-2xl mx-auto leading-relaxed">
                Check your support tickets or reach us on the Contact page for live chat, phone, and email.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 md:gap-4 w-full max-w-xl mx-auto">
                <a href="{{ $supportHref }}" class="flex flex-col items-center justify-center gap-2 p-4 md:p-5 min-h-0 bg-surface/70 rounded-xl border border-border-subtle hover:border-accent/40 hover:bg-muted/40 transition-colors">
                    <span class="text-accent"><x-ui.icon name="chat" class="w-6 h-6" /></span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-white">My tickets</span>
                </a>
                <a href="{{ route('contact') }}" class="flex flex-col items-center justify-center gap-2 p-4 md:p-5 min-h-0 bg-surface/70 rounded-xl border border-border-subtle hover:border-accent/40 hover:bg-muted/40 transition-colors">
                    <span class="text-accent"><x-ui.icon name="support" class="w-6 h-6" /></span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-white">Contact us</span>
                </a>
            </div>
        </div>
    </div>
</section>

</div>
@endsection
