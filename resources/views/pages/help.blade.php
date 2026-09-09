@extends('layouts.marketing')

@section('title', 'Help Center')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $heroBg = asset('assets/images/helpcenter.jpg');
    if (! is_file(public_path('assets/images/helpcenter.jpg'))) {
        $heroBg = asset('assets/images/homeslider1.jpg');
    }

    $toneIcon = [
        'primary' => 'bg-primary/10 text-primary',
        'amber' => 'bg-amber-50 text-amber-600',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'sky' => 'bg-sky-50 text-sky-700',
        'violet' => 'bg-violet-50 text-violet-700',
        'rose' => 'bg-rose-50 text-rose-600',
    ];

    $resolvedCategories = collect($categories)->map(function (array $cat) use ($toneIcon) {
        $slug = $cat['article'] ?? $cat['key'] ?? null;

        return array_merge($cat, [
            'resolved_href' => $slug ? route('help.article', $slug) : route('help'),
            'icon_class' => $toneIcon[$cat['tone'] ?? 'primary'] ?? $toneIcon['primary'],
            'material_icon' => $cat['icon'] ?? 'help',
        ]);
    })->values();

    $supportHref = auth()->check()
        ? route('dashboard.support.index')
        : route('login');

    $quickTopics = [
        'Fund Wallet',
        'Proof Submission',
        'KYC Verification',
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

{{-- Hero: fill viewport below sticky header (same as home) --}}
<section class="home-hero relative flex items-center overflow-hidden bg-navy-dark">
    <div class="absolute inset-0 z-0">
        <img src="{{ $heroBg }}" alt="" class="w-full h-full object-cover object-center" loading="eager">
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/55 via-slate-900/35 to-slate-900/20" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/50 via-transparent to-black/10" aria-hidden="true"></div>
    </div>

    <div class="relative z-10 max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 lg:py-16 w-full">
        <div class="max-w-3xl w-full">
            <h1 class="font-display text-3xl sm:text-4xl md:text-5xl lg:text-[54px] font-extrabold text-white tracking-tight leading-[1.15] mb-4 sm:mb-5">
                How can we help you today?
            </h1>
            <p class="text-base sm:text-lg lg:text-xl text-slate-100/90 font-normal leading-relaxed mb-6 sm:mb-8 max-w-2xl">
                Guides for Creators, Agents, wallets, proof, and account security.
            </p>

            <div class="relative w-full max-w-2xl mb-6">
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

{{-- Categories --}}
<section class="w-full bg-white border-b border-slate-100">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-20">
        <div class="mb-10">
            <span class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">Browse topics</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Help categories</h2>
            <p class="text-slate-600 text-base sm:text-lg mt-2 max-w-xl">Six focused guides — pick the path that matches what you need.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
            @foreach($resolvedCategories as $cat)
                <a
                    href="{{ $cat['resolved_href'] }}"
                    class="group bg-white rounded-2xl border border-slate-100 p-5 sm:p-6 shadow-sm hover:shadow-md hover:border-slate-200 transition-all flex flex-col h-full"
                >
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="w-11 h-11 rounded-xl {{ $cat['icon_class'] }} flex items-center justify-center group-hover:scale-105 transition-transform">
                            <span class="material-symbols-outlined text-2xl" aria-hidden="true">{{ $cat['material_icon'] }}</span>
                        </div>
                        @if(! empty($cat['badge']))
                            <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full bg-slate-50 text-slate-600 border border-slate-100">
                                {{ $cat['badge'] }}
                            </span>
                        @endif
                    </div>
                    <h3 class="font-display text-lg font-bold text-slate-900 mb-1.5">{{ $cat['title'] }}</h3>
                    <p class="text-sm text-slate-600 leading-relaxed flex-1 mb-4">{{ $cat['description'] }}</p>
                    <span class="inline-flex items-center gap-1 text-sm font-semibold text-primary">
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
<section id="faqs" class="w-full bg-slate-50 border-b border-slate-100 scroll-mt-24">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-20">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-10">
                <span class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">FAQ</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Common questions</h2>
            </div>
            <div class="space-y-3">
                @foreach($faqs as $i => $faq)
                    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
                        <button
                            type="button"
                            class="w-full px-5 py-4 text-left flex items-center justify-between gap-4 hover:bg-slate-50 transition-colors"
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

{{-- Install PWA — same CTA card pattern as Creators / Agents --}}
<section class="w-full bg-white py-14 sm:py-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-primary text-white rounded-3xl p-8 sm:p-12 lg:p-16 shadow-xl relative overflow-hidden text-center flex flex-col items-center">
            <div class="absolute -right-20 -top-20 w-96 h-96 rounded-full bg-primary-hover/40 blur-3xl pointer-events-none" aria-hidden="true"></div>
            <div class="absolute -left-20 -bottom-20 w-96 h-96 rounded-full bg-emerald-500/20 blur-3xl pointer-events-none" aria-hidden="true"></div>
            <div class="relative z-10 max-w-3xl flex flex-col items-center">
                <span class="text-[11px] font-bold uppercase tracking-widest text-blue-100 mb-3">
                    Progressive Web App
                </span>
                <h2 class="font-display text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight mb-4 text-white">
                    Install {{ $brandName }} on your devices
                </h2>
                <p class="text-base sm:text-lg text-blue-100 max-w-2xl mb-8 leading-relaxed">
                    Add the app to your home screen or desktop for faster access to campaigns, tasks, and wallet updates.
                </p>
                <div class="flex flex-wrap items-center justify-center gap-3 w-full sm:w-auto">
                    <button
                        type="button"
                        data-pwa-install="mobile"
                        class="md:hidden inline-flex items-center justify-center gap-1.5 font-semibold text-sm bg-white text-primary hover:bg-slate-50 px-6 py-3 rounded-lg shadow transition-colors"
                    >
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">smartphone</span>
                        <span data-pwa-label>Download Mobile App</span>
                    </button>
                    <button
                        type="button"
                        data-pwa-install="desktop"
                        class="hidden md:inline-flex items-center justify-center gap-1.5 font-semibold text-sm bg-white text-primary hover:bg-slate-50 px-6 py-3 rounded-lg shadow transition-colors"
                    >
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">desktop_windows</span>
                        <span data-pwa-label>Download Desktop App</span>
                    </button>
                </div>
                <div class="mt-8 pt-6 flex flex-wrap items-center justify-center gap-4 sm:gap-6 text-sm text-blue-100">
                    <span>• iOS: Share → Add to Home Screen</span>
                    <span>• Android: Menu → Install app</span>
                    <span>• Desktop: Install from the address bar</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Support CTA --}}
<section class="w-full bg-slate-50 py-14 sm:py-20 border-t border-slate-100">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="w-full bg-slate-900 rounded-2xl p-8 sm:p-12 lg:p-16 text-white shadow-xl flex flex-col items-center text-center">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Support</span>
            <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-white mt-2 max-w-2xl">
                Still need help?
            </h2>
            <p class="text-base sm:text-lg text-slate-300 mt-3 max-w-xl leading-relaxed">
                Open a ticket for payment or proof issues, or reach us from the Contact page.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3 mt-8">
                <a href="{{ $supportHref }}" class="inline-flex items-center gap-1.5 font-semibold text-sm bg-white/10 hover:bg-white/15 border border-white/20 text-white px-6 py-3 rounded-xl transition-colors">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">receipt_long</span>
                    <span>My tickets</span>
                </a>
                <a href="{{ route('contact') }}" class="inline-flex items-center gap-1.5 font-semibold text-sm bg-primary hover:bg-primary-hover text-white px-6 py-3 rounded-xl transition-colors shadow-md">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">forum</span>
                    <span>Contact Support</span>
                </a>
            </div>
        </div>
    </div>
</section>

</div>
@endsection
