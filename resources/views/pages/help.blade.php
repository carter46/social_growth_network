@extends('layouts.marketing')

@section('title', 'Help Center')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $heroBg = asset('assets/images/helpcenter.jpg');
    if (! is_file(public_path('assets/images/helpcenter.jpg'))) {
        $heroBg = asset('assets/images/homeslider1.jpg');
    }

    $toneStyles = [
        'primary' => [
            'card' => 'bg-primary/5 border-primary/20 hover:border-primary/40',
            'icon' => 'bg-primary text-white',
            'badge' => 'bg-primary/15 text-primary',
            'link' => 'text-primary',
        ],
        'amber' => [
            'card' => 'bg-amber-50 border-amber-200 hover:border-amber-300',
            'icon' => 'bg-amber-500 text-white',
            'badge' => 'bg-amber-100 text-amber-800',
            'link' => 'text-amber-800',
        ],
        'emerald' => [
            'card' => 'bg-emerald-50 border-emerald-200 hover:border-emerald-300',
            'icon' => 'bg-emerald-600 text-white',
            'badge' => 'bg-emerald-100 text-emerald-800',
            'link' => 'text-emerald-800',
        ],
        'sky' => [
            'card' => 'bg-sky-50 border-sky-200 hover:border-sky-300',
            'icon' => 'bg-sky-600 text-white',
            'badge' => 'bg-sky-100 text-sky-800',
            'link' => 'text-sky-800',
        ],
        'violet' => [
            'card' => 'bg-violet-50 border-violet-200 hover:border-violet-300',
            'icon' => 'bg-violet-600 text-white',
            'badge' => 'bg-violet-100 text-violet-800',
            'link' => 'text-violet-800',
        ],
        'rose' => [
            'card' => 'bg-rose-50 border-rose-200 hover:border-rose-300',
            'icon' => 'bg-rose-500 text-white',
            'badge' => 'bg-rose-100 text-rose-800',
            'link' => 'text-rose-800',
        ],
    ];

    $resolvedCategories = collect($categories)->map(function (array $cat) use ($toneStyles) {
        $slug = $cat['article'] ?? $cat['key'] ?? null;
        $tone = $toneStyles[$cat['tone'] ?? 'primary'] ?? $toneStyles['primary'];

        return array_merge($cat, [
            'resolved_href' => $slug ? route('help.article', $slug) : route('help'),
            'styles' => $tone,
            'material_icon' => $cat['icon'] ?? 'help',
        ]);
    })->values();

    $supportHref = auth()->check()
        ? route('dashboard.support.index')
        : route('login');

    $quickTopics = [
        'Fund Wallet',
        'Monnify Checkout',
        'Buying a Service',
        'Proof Submission',
        'KYC Verification',
        'Verified Payouts',
    ];
@endphp

<div
    x-data="{
        q: '',
        open: false,
        active: -1,
        faqOpen: 0,
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
        setQuick(topic) {
            this.q = topic;
            this.open = true;
            this.active = -1;
            this.$nextTick(() => this.$refs.search?.focus());
        },
        runSearch() {
            const list = this.suggestions;
            if (list.length === 1) {
                this.select(list[0]);
                return;
            }
            if (this.active >= 0 && list[this.active]) {
                this.select(list[this.active]);
                return;
            }
            this.open = (this.q || '').trim().length > 0;
            if (list.length) {
                document.getElementById('faqs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
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
                e.preventDefault();
                this.runSearch();
            } else if (e.key === 'Escape') {
                this.open = false;
                this.active = -1;
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
    class="w-full bg-white"
>

{{-- Hero with full-bleed background + home-style search --}}
<section class="relative w-full min-h-[70vh] sm:min-h-[75vh] flex items-end sm:items-center overflow-hidden">
    <div class="absolute inset-0 z-0">
        <img src="{{ $heroBg }}" alt="" class="w-full h-full object-cover object-center" loading="eager">
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/75 via-slate-900/55 to-slate-900/35" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-transparent to-black/15" aria-hidden="true"></div>
    </div>

    <div class="relative z-10 max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 lg:py-20 w-full">
        <div class="flex flex-wrap items-center gap-1.5 text-sm text-slate-300 mb-4">
            <a href="{{ route('home') }}" class="hover:text-white transition-colors">Home</a>
            <span class="text-slate-500">/</span>
            <span class="text-white font-semibold">Help Center</span>
        </div>

        <div class="max-w-3xl">
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-primary/90 text-white rounded-full text-[11px] font-bold uppercase tracking-wider mb-4 shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse" aria-hidden="true"></span>
                Knowledge Base
            </div>
            <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white tracking-tight mb-3 leading-tight">
                How can we help you today?
            </h1>
            <p class="text-base sm:text-lg text-slate-100/90 mb-8 leading-relaxed max-w-2xl">
                Guides for Creators, Agents, wallets, proof, and account security.
            </p>

            <div class="relative w-full max-w-2xl mb-5">
                <div class="w-full bg-white/45 backdrop-blur-md border border-white/40 rounded-xl p-1.5 shadow-xl flex flex-col sm:flex-row items-stretch sm:items-center gap-1.5">
                    <div class="flex items-center gap-2 px-3 py-2 w-full min-w-0">
                        <span class="material-symbols-outlined text-slate-600/80 shrink-0" aria-hidden="true">search</span>
                        <label for="help-search" class="sr-only">Search help center</label>
                        <input
                            id="help-search"
                            x-ref="search"
                            x-model="q"
                            @keydown="onKey($event)"
                            @focus="open = (q || '').trim().length > 0"
                            type="search"
                            autocomplete="off"
                            placeholder="Search help articles…"
                            class="w-full min-w-0 bg-transparent text-slate-900 text-sm placeholder:text-slate-600/70 focus:outline-none border-0 focus:ring-0 p-0"
                            role="combobox"
                            :aria-expanded="open"
                            aria-controls="help-search-results"
                            aria-autocomplete="list"
                        >
                    </div>
                    <button
                        type="button"
                        @click="runSearch()"
                        class="w-full sm:w-auto px-6 py-2.5 bg-primary/90 hover:bg-primary text-white text-sm font-semibold rounded-lg transition-colors shrink-0"
                    >
                        Search
                    </button>
                </div>

                <div
                    id="help-search-results"
                    x-show="open"
                    x-cloak
                    class="absolute left-0 right-0 top-full mt-2 z-40 rounded-xl border border-slate-200 bg-white shadow-xl overflow-hidden"
                    role="listbox"
                >
                    <template x-if="suggestions.length === 0 && (q || '').trim()">
                        <p class="px-5 py-4 text-sm text-slate-500">No results for “<span x-text="(q || '').trim()"></span>”.</p>
                    </template>
                    <ul class="max-h-80 overflow-y-auto py-2">
                        <template x-for="(item, i) in suggestions" :key="item.href + item.label">
                            <li role="option" :aria-selected="active === i">
                                <button
                                    type="button"
                                    class="w-full text-left px-5 py-3 hover:bg-slate-50 transition-colors flex flex-col gap-0.5"
                                    :class="active === i ? 'bg-slate-50' : ''"
                                    @click="select(item)"
                                    @mouseenter="active = i"
                                >
                                    <span class="text-sm font-medium text-slate-900" x-text="item.label"></span>
                                    <span class="text-[11px] text-slate-500" x-text="item.hint || item.type"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-slate-200">Frequent topics:</span>
                @foreach($quickTopics as $topic)
                    <button
                        type="button"
                        @click="setQuick(@js($topic))"
                        class="px-3 py-1 bg-white/15 hover:bg-white/25 text-white text-xs font-medium rounded-full border border-white/25 backdrop-blur-sm transition-colors"
                    >
                        {{ $topic }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- Compact status strip --}}
<section class="w-full bg-emerald-600 text-white">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center justify-between gap-3 text-sm">
        <div class="inline-flex items-center gap-2 font-semibold">
            <span class="w-2 h-2 rounded-full bg-white animate-pulse" aria-hidden="true"></span>
            Platform operational
        </div>
        <div class="flex flex-wrap gap-4 text-emerald-50 text-xs sm:text-sm">
            <span>Support desk · avg. &lt; 8 mins</span>
            <span class="hidden sm:inline">Monnify settlements active</span>
        </div>
    </div>
</section>

{{-- Categories: 3 columns × 6 compact colorful cards --}}
<section class="bg-slate-50 border-b border-slate-100">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="mb-8">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-primary/10 text-primary text-[11px] font-bold uppercase tracking-wider mb-2">
                Browse topics
            </span>
            <h2 class="font-display text-2xl sm:text-3xl font-bold text-slate-900">Help categories</h2>
            <p class="text-sm text-slate-600 mt-1 max-w-xl">Six focused guides — pick the path that matches what you need.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
            @foreach($resolvedCategories as $cat)
                <a
                    href="{{ $cat['resolved_href'] }}"
                    class="group rounded-2xl border p-5 sm:p-6 transition-all hover:shadow-md {{ $cat['styles']['card'] }} flex flex-col h-full"
                >
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="w-11 h-11 rounded-xl {{ $cat['styles']['icon'] }} flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                            <span class="material-symbols-outlined text-2xl" aria-hidden="true">{{ $cat['material_icon'] }}</span>
                        </div>
                        @if(! empty($cat['badge']))
                            <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full {{ $cat['styles']['badge'] }}">
                                {{ $cat['badge'] }}
                            </span>
                        @endif
                    </div>
                    <h3 class="font-display text-lg font-bold text-slate-900 mb-1.5">{{ $cat['title'] }}</h3>
                    <p class="text-sm text-slate-600 leading-relaxed flex-1 mb-4">{{ $cat['description'] }}</p>
                    <span class="inline-flex items-center gap-1 text-sm font-semibold {{ $cat['styles']['link'] }}">
                        {{ $cat['cta'] }}
                        <span class="material-symbols-outlined text-base transition-transform group-hover:translate-x-0.5" aria-hidden="true">arrow_forward</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- FAQ --}}
@if(count($faqs))
<section id="faqs" class="bg-white scroll-mt-24">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-8">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 text-[11px] font-bold uppercase tracking-wider mb-2">
                    FAQ
                </span>
                <h2 class="font-display text-2xl sm:text-3xl font-bold text-slate-900">Common questions</h2>
            </div>
            <div class="space-y-3">
                @foreach($faqs as $i => $faq)
                    <div class="bg-slate-50 rounded-xl border border-slate-100 overflow-hidden">
                        <button
                            type="button"
                            class="w-full px-5 py-4 text-left flex items-center justify-between gap-4 hover:bg-slate-100/70 transition-colors"
                            @click="faqOpen = faqOpen === {{ $i }} ? -1 : {{ $i }}"
                            :aria-expanded="faqOpen === {{ $i }}"
                        >
                            <span class="font-display text-sm sm:text-base font-semibold text-slate-900 flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-lg" aria-hidden="true">{{ $faq['icon'] ?? 'help' }}</span>
                                </span>
                                <span>{{ $faq['q'] ?? '' }}</span>
                            </span>
                            <span
                                class="material-symbols-outlined text-slate-400 transition-transform duration-200 shrink-0"
                                :class="faqOpen === {{ $i }} ? 'rotate-180' : ''"
                                aria-hidden="true"
                            >expand_more</span>
                        </button>
                        <div
                            x-show="faqOpen === {{ $i }}"
                            x-cloak
                            class="px-5 pb-5 text-sm text-slate-600 leading-relaxed"
                        >
                            <p class="mb-3 pl-11">{{ $faq['a'] ?? '' }}</p>
                            @if(! empty($faq['article']))
                                <a
                                    href="{{ route('help.article', $faq['article']) }}{{ ! empty($faq['section']) ? '#'.$faq['section'] : '' }}"
                                    class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline pl-11"
                                >
                                    <span>Read the full guide</span>
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">arrow_forward</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif

{{-- PWA install --}}
<section class="bg-gradient-to-br from-primary to-sky-700 text-white">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-14">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-8 mb-8">
            <div class="max-w-2xl">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 text-[11px] font-bold uppercase tracking-wider mb-3">
                    <span class="material-symbols-outlined text-sm" aria-hidden="true">install_mobile</span>
                    Progressive Web App
                </span>
                <h2 class="font-display text-2xl sm:text-3xl font-bold">
                    Install {{ $brandName }}
                </h2>
                <p class="text-sm sm:text-base text-white/85 mt-2 leading-relaxed">
                    Add the app to your home screen or desktop for faster access to campaigns, tasks, and wallet updates.
                </p>
            </div>
            <div class="shrink-0 flex flex-col sm:flex-row gap-3">
                <button
                    type="button"
                    data-pwa-install="mobile"
                    class="md:hidden px-5 py-2.5 bg-white text-primary hover:bg-slate-50 rounded-lg font-semibold text-sm transition-colors inline-flex items-center justify-center gap-2 shadow-sm"
                >
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">smartphone</span>
                    <span data-pwa-label>Download Mobile App</span>
                </button>
                <button
                    type="button"
                    data-pwa-install="desktop"
                    class="hidden md:inline-flex px-5 py-2.5 bg-white text-primary hover:bg-slate-50 rounded-lg font-semibold text-sm transition-colors items-center justify-center gap-2 shadow-sm"
                >
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">desktop_windows</span>
                    <span data-pwa-label>Download Desktop App</span>
                </button>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white/10 backdrop-blur-sm border border-white/15 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined" aria-hidden="true">phone_iphone</span>
                    <p class="font-semibold">iOS Safari</p>
                </div>
                <p class="text-sm text-white/80 leading-relaxed">Share → Add to Home Screen → Add.</p>
            </div>
            <div class="bg-white/10 backdrop-blur-sm border border-white/15 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined" aria-hidden="true">android</span>
                    <p class="font-semibold">Android Chrome</p>
                </div>
                <p class="text-sm text-white/80 leading-relaxed">Menu → Install app / Add to home screen.</p>
            </div>
            <div class="bg-white/10 backdrop-blur-sm border border-white/15 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined" aria-hidden="true">laptop_mac</span>
                    <p class="font-semibold">Desktop</p>
                </div>
                <p class="text-sm text-white/80 leading-relaxed">Use the install icon in the address bar, then Install.</p>
            </div>
        </div>
    </div>
</section>

{{-- Support CTA --}}
<section class="bg-white">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="rounded-2xl bg-slate-900 text-white p-6 sm:p-8 lg:p-10 overflow-hidden relative">
            <div class="absolute -top-16 -right-16 w-48 h-48 bg-primary/40 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
            <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                <div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-white/10 text-[11px] font-bold uppercase tracking-wider mb-3">
                        Support
                    </span>
                    <h2 class="font-display text-2xl sm:text-3xl font-bold mb-2">Still need help?</h2>
                    <p class="text-sm text-slate-300 leading-relaxed">
                        Open a ticket for payment or proof issues, or reach us from the Contact page.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 md:justify-end">
                    <a href="{{ $supportHref }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/20 text-sm font-semibold transition-colors">
                        <span class="material-symbols-outlined text-base" aria-hidden="true">receipt_long</span>
                        My tickets
                    </a>
                    <a href="{{ route('contact') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-primary hover:bg-primary-hover text-sm font-semibold transition-colors">
                        <span class="material-symbols-outlined text-base" aria-hidden="true">forum</span>
                        Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

</div>
@endsection
