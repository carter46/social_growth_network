@extends('layouts.marketing')

@section('title', 'Help Center')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $imgReliability = asset('assets/images/homeslider2.jpg');

    $sectionIcons = [
        'choose-role' => 'groups',
        'create-account' => 'description',
        'email-verification' => 'security',
        'wallet' => 'account_balance_wallet',
        'first-steps' => 'rocket_launch',
        'support' => 'support_agent',
        'browse' => 'shopping_bag',
        'creator-launch' => 'campaign',
        'agent-tasks' => 'task_alt',
        'proof' => 'fact_check',
        'tracking' => 'query_stats',
        'funding' => 'payments',
        'checkout' => 'shopping_cart_checkout',
        'agent-rewards' => 'payments',
        'withdrawals' => 'sync_alt',
        'disputes' => 'support_agent',
        'passwords' => 'lock',
        'kyc' => 'badge',
        'proof-standards' => 'verified',
        'reporting' => 'flag',
        'support-safety' => 'policy',
    ];

    $toneIcon = [
        'primary' => 'bg-primary/10 text-primary',
        'warning' => 'bg-slate-100 text-primary',
        'success' => 'bg-emerald-50 text-emerald-700',
        'info' => 'bg-sky-50 text-sky-700',
    ];

    $allArticles = \App\Support\HelpContent::all();
    $sectionCount = collect($allArticles)->sum(fn ($a) => count($a['sections'] ?? []));

    $resolvedCategories = collect($categories)->map(function (array $cat) use ($sectionIcons, $toneIcon) {
        $slug = $cat['article'] ?? $cat['key'] ?? null;
        $article = $slug ? \App\Support\HelpContent::find($slug) : null;
        $sections = collect($article['sections'] ?? [])->take(3)->map(function (array $section) use ($slug, $sectionIcons) {
            $id = $section['id'] ?? '';

            return [
                'title' => $section['title'] ?? $section['nav'] ?? 'Section',
                'href' => $slug
                    ? route('help.article', $slug).($id !== '' ? '#'.$id : '')
                    : route('help'),
                'icon' => $sectionIcons[$id] ?? 'description',
            ];
        })->values()->all();

        $count = count($article['sections'] ?? []);

        return array_merge($cat, [
            'resolved_href' => $slug ? route('help.article', $slug) : route('help'),
            'preview_sections' => $sections,
            'article_count' => $count,
            'icon_class' => $toneIcon[$cat['tone'] ?? 'primary'] ?? $toneIcon['primary'],
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

{{-- Hero & Instant Search --}}
<section class="relative w-full bg-slate-50 py-14 sm:py-16 lg:py-20 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-slate-100/80 via-transparent to-white pointer-events-none" aria-hidden="true"></div>
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500 mb-4">
            <a href="{{ route('home') }}" class="hover:text-primary transition-colors">Home</a>
            <span class="text-slate-300">/</span>
            <span class="text-primary font-semibold">Help Center &amp; Knowledge Base</span>
        </div>

        <div class="max-w-3xl">
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-primary/10 text-primary rounded-full text-[11px] font-bold uppercase tracking-wider mb-3">
                <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse" aria-hidden="true"></span>
                Knowledge Base &amp; Support Portal
            </div>
            <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight mb-3 leading-tight">
                How can we help you today?
            </h1>
            <p class="text-base sm:text-lg text-slate-600 mb-8 leading-relaxed">
                Search our documentation, operation playbooks, tutorials, and troubleshooting guides for wallet funding, secure checkout, and proof compliance.
            </p>

            <div class="relative w-full bg-white rounded-xl shadow-md border border-slate-100 p-1.5 flex items-center gap-1">
                <span class="material-symbols-outlined text-slate-400 ml-3 shrink-0" aria-hidden="true">search</span>
                <label for="help-search" class="sr-only">Search help center</label>
                <input
                    id="help-search"
                    x-ref="search"
                    x-model="q"
                    @keydown="onKey($event)"
                    @focus="open = (q || '').trim().length > 0"
                    type="search"
                    autocomplete="off"
                    placeholder="Search help articles, guides, error codes… (press / to focus)"
                    class="w-full h-11 bg-transparent px-2 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none"
                    role="combobox"
                    :aria-expanded="open"
                    aria-controls="help-search-results"
                    aria-autocomplete="list"
                >
                <kbd class="hidden md:inline-flex items-center justify-center px-2 py-0.5 bg-slate-100 text-slate-500 text-[11px] font-semibold rounded-lg shrink-0">/</kbd>
                <button
                    type="button"
                    @click="runSearch()"
                    class="px-4 sm:px-5 h-11 bg-primary hover:bg-primary-hover text-white font-semibold text-sm rounded-lg transition-colors inline-flex items-center gap-1.5 shrink-0"
                >
                    <span>Search</span>
                    <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
                </button>

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

            <div class="flex flex-wrap items-center gap-2 mt-4">
                <span class="text-xs font-medium text-slate-500">Frequent topics:</span>
                @foreach($quickTopics as $topic)
                    <button
                        type="button"
                        @click="setQuick(@js($topic))"
                        class="px-3 py-1 bg-white hover:bg-slate-100 text-slate-600 text-xs font-medium rounded-full shadow-sm border border-slate-100 transition-colors"
                    >
                        {{ $topic }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- Live Health & SLA Metrics Bar --}}
<section class="w-full bg-white py-5 shadow-sm border-y border-slate-100">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-700 shrink-0">
                <span class="material-symbols-outlined" aria-hidden="true">health_and_safety</span>
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-600 shrink-0" aria-hidden="true"></span>
                    <p class="text-sm font-semibold text-slate-900 truncate">Platform Operational</p>
                </div>
                <p class="text-xs text-slate-500">High checkout system uptime</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
                <span class="material-symbols-outlined" aria-hidden="true">timer</span>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">Avg. Response Time</p>
                <p class="text-xs text-slate-500">&lt; 8 mins on support desk</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600 shrink-0">
                <span class="material-symbols-outlined" aria-hidden="true">verified</span>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">Monnify &amp; Bank Webhooks</p>
                <p class="text-xs text-slate-500">Instant settlement active</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-sky-50 flex items-center justify-center text-sky-700 shrink-0">
                <span class="material-symbols-outlined" aria-hidden="true">policy</span>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">Automated Audits</p>
                <p class="text-xs text-slate-500">Proof inspection online</p>
            </div>
        </div>
    </div>
</section>

{{-- Knowledge Base Categories --}}
<section class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-16 lg:py-20 w-full">
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-4">
        <div>
            <span class="text-[11px] font-bold uppercase tracking-widest text-primary">Documentation Directory</span>
            <h2 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 mt-1">Browse Help Categories</h2>
            <p class="text-sm sm:text-base text-slate-600 max-w-xl mt-2 leading-relaxed">
                Deep-dive into step-by-step guides, payment workflows, agent verification, and compliance structures.
            </p>
        </div>
        <div class="md:text-right shrink-0">
            <span class="text-xs font-medium text-slate-500">
                Showing {{ $resolvedCategories->count() }} main directories · {{ $sectionCount }} detailed sections
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
        @foreach($resolvedCategories as $cat)
            <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-100 hover:shadow-md transition-shadow flex flex-col justify-between group h-full">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-lg {{ $cat['icon_class'] }} flex items-center justify-center group-hover:scale-105 transition-transform">
                            <span class="material-symbols-outlined text-2xl" aria-hidden="true">{{ $cat['material_icon'] }}</span>
                        </div>
                        <span class="text-xs font-medium px-3 py-1 rounded-full bg-slate-100 text-slate-600">
                            {{ $cat['article_count'] }} {{ \Illuminate\Support\Str::plural('section', $cat['article_count']) }}
                        </span>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-1">{{ $cat['title'] }}</h3>
                    <p class="text-sm text-slate-600 mb-6 leading-relaxed">{{ $cat['description'] }}</p>
                    @if(count($cat['preview_sections']))
                        <ul class="space-y-2 mb-6">
                            @foreach($cat['preview_sections'] as $preview)
                                <li>
                                    <a href="{{ $preview['href'] }}" class="flex items-center justify-between text-sm text-slate-800 hover:text-primary transition-colors py-1 gap-3">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <span class="material-symbols-outlined text-base text-slate-400 shrink-0" aria-hidden="true">{{ $preview['icon'] }}</span>
                                            <span class="truncate">{{ $preview['title'] }}</span>
                                        </span>
                                        <span class="material-symbols-outlined text-base text-slate-300 shrink-0" aria-hidden="true">chevron_right</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="pt-3 mt-auto">
                    <a href="{{ $cat['resolved_href'] }}" class="font-semibold text-sm text-primary hover:text-primary-hover inline-flex items-center gap-1.5">
                        <span>{{ $cat['cta'] }}</span>
                        <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- Platform Reliability Split --}}
<section class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 w-full">
    <div class="bg-slate-100 rounded-2xl overflow-hidden shadow-sm grid grid-cols-1 lg:grid-cols-12">
        <div class="lg:col-span-7 p-6 sm:p-8 lg:p-10 flex flex-col justify-center">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-white rounded-full w-fit mb-3">
                <span class="material-symbols-outlined text-primary text-base" aria-hidden="true">verified</span>
                <span class="text-xs font-semibold text-primary">Autonomous Verification Engine</span>
            </div>
            <h2 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 mb-3">
                Institutional security with deterministic checkout
            </h2>
            <p class="text-sm sm:text-base text-slate-600 mb-6 leading-relaxed">
                Unlike unmoderated micro-task platforms, {{ $brandName }} coordinates every interaction with clear milestones. Creator funds stay protected until individual proof assets are reviewed and released.
            </p>
            <div class="grid grid-cols-3 gap-3 p-4 bg-white rounded-xl mb-6 border border-slate-100">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Resolution</span>
                    <p class="font-display text-2xl font-bold text-slate-900 mt-0.5">99.4%</p>
                    <span class="text-xs text-emerald-700">Low-dispute rate</span>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Settlement</span>
                    <p class="font-display text-2xl font-bold text-slate-900 mt-0.5">2–5m</p>
                    <span class="text-xs text-slate-500">Bank auto-credit</span>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Compliance</span>
                    <p class="font-display text-2xl font-bold text-slate-900 mt-0.5">Tier 2</p>
                    <span class="text-xs text-primary">KYC standard</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('help.article', 'billing-wallets-payments') }}" class="px-5 py-2.5 bg-slate-900 text-white rounded-lg font-semibold text-sm hover:bg-slate-800 transition-colors inline-flex items-center gap-1.5">
                    <span>Learn payment protocol</span>
                    <span class="material-symbols-outlined text-sm" aria-hidden="true">open_in_new</span>
                </a>
                <a href="{{ route('agents') }}" class="px-5 py-2.5 bg-white text-slate-900 hover:bg-slate-50 rounded-lg font-semibold text-sm border border-slate-200 transition-colors">
                    Agent audit rules
                </a>
            </div>
        </div>
        <div class="lg:col-span-5 relative min-h-[280px] lg:min-h-full">
            <img
                src="{{ $imgReliability }}"
                alt="Campaign specialist reviewing metrics in a bright workspace"
                class="absolute inset-0 w-full h-full object-cover"
                loading="lazy"
            >
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-transparent lg:hidden" aria-hidden="true"></div>
            <div class="absolute bottom-4 left-4 right-4 bg-white/95 backdrop-blur-md p-4 rounded-xl shadow-lg border border-white/40">
                <div class="flex items-center justify-between text-sm font-semibold text-slate-900 gap-3">
                    <span class="flex items-center gap-1.5 text-emerald-700">
                        <span class="material-symbols-outlined text-base" aria-hidden="true">check_circle</span>
                        Monnify gateway validated
                    </span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 shrink-0">Live</span>
                </div>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">Real-time webhook notifications synchronized with bank clearing standards.</p>
            </div>
        </div>
    </div>
</section>

{{-- FAQ Accordion --}}
@if(count($faqs))
<section id="faqs" class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-16 lg:py-20 w-full scroll-mt-24">
    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-10">
            <span class="text-[11px] font-bold uppercase tracking-widest text-primary">Platform Answers</span>
            <h2 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 mt-1">Frequently Asked Questions</h2>
            <p class="text-sm text-slate-600 mt-2">
                Clear operational answers for creators and verified task agents.
            </p>
        </div>
        <div class="space-y-3">
            @foreach($faqs as $i => $faq)
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden transition-all">
                    <button
                        type="button"
                        class="w-full px-5 sm:px-6 py-4 sm:py-5 text-left flex items-center justify-between gap-4 hover:bg-slate-50/80 transition-colors"
                        @click="faqOpen = faqOpen === {{ $i }} ? -1 : {{ $i }}"
                        :aria-expanded="faqOpen === {{ $i }}"
                    >
                        <span class="font-display text-base font-semibold text-slate-900 flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary text-xl shrink-0" aria-hidden="true">{{ $faq['icon'] ?? 'help' }}</span>
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
                        class="px-5 sm:px-6 pb-5 text-sm text-slate-600 leading-relaxed"
                    >
                        <p class="mb-3">{{ $faq['a'] ?? '' }}</p>
                        @if(! empty($faq['article']))
                            <a
                                href="{{ route('help.article', $faq['article']) }}{{ ! empty($faq['section']) ? '#'.$faq['section'] : '' }}"
                                class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline"
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
</section>
@endif

{{-- PWA Install Guide --}}
<section class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 w-full">
    <div class="bg-slate-50 rounded-2xl p-6 sm:p-8 lg:p-10 border border-slate-100">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-white text-slate-800 rounded-md text-[11px] font-bold uppercase tracking-wider mb-2 border border-slate-100">
                    <span class="material-symbols-outlined text-primary text-sm" aria-hidden="true">install_mobile</span>
                    Cross-platform application
                </div>
                <h2 class="font-display text-2xl sm:text-3xl font-bold text-slate-900">
                    Install {{ $brandName }} on your devices
                </h2>
                <p class="text-sm text-slate-600 mt-2 leading-relaxed">
                    Add {{ $brandName }} to your mobile home screen or computer desktop for quick access to campaigns, task notifications, and wallet statements.
                </p>
            </div>
            <div class="shrink-0">
                <button
                    type="button"
                    data-pwa-install="desktop"
                    class="px-5 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-lg font-semibold text-sm transition-colors inline-flex items-center gap-2 shadow-sm"
                >
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">download</span>
                    <span data-pwa-label>Install Web App</span>
                </button>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-100">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-700">
                        <span class="material-symbols-outlined" aria-hidden="true">phone_iphone</span>
                    </div>
                    <div>
                        <p class="font-display text-base font-semibold text-slate-900">iOS (Apple Safari)</p>
                        <p class="text-xs text-slate-500">iPhone &amp; iPad</p>
                    </div>
                </div>
                <ol class="space-y-2 text-sm text-slate-600">
                    <li class="flex items-start gap-2"><span class="font-semibold text-primary">1.</span><span>Tap the <strong class="text-slate-900">Share</strong> button in Safari.</span></li>
                    <li class="flex items-start gap-2"><span class="font-semibold text-primary">2.</span><span>Tap <strong class="text-slate-900">Add to Home Screen</strong>.</span></li>
                    <li class="flex items-start gap-2"><span class="font-semibold text-primary">3.</span><span>Confirm by tapping <strong class="text-slate-900">Add</strong>.</span></li>
                </ol>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-100">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-700">
                        <span class="material-symbols-outlined" aria-hidden="true">android</span>
                    </div>
                    <div>
                        <p class="font-display text-base font-semibold text-slate-900">Android (Chrome)</p>
                        <p class="text-xs text-slate-500">Pixel, Samsung, OnePlus</p>
                    </div>
                </div>
                <ol class="space-y-2 text-sm text-slate-600">
                    <li class="flex items-start gap-2"><span class="font-semibold text-primary">1.</span><span>Open the browser menu (three dots).</span></li>
                    <li class="flex items-start gap-2"><span class="font-semibold text-primary">2.</span><span>Select <strong class="text-slate-900">Install app</strong> or <strong class="text-slate-900">Add to home screen</strong>.</span></li>
                    <li class="flex items-start gap-2"><span class="font-semibold text-primary">3.</span><span>Confirm to pin {{ $brandName }} to your app drawer.</span></li>
                </ol>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-100">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-700">
                        <span class="material-symbols-outlined" aria-hidden="true">laptop_mac</span>
                    </div>
                    <div>
                        <p class="font-display text-base font-semibold text-slate-900">Desktop (Chrome / Edge)</p>
                        <p class="text-xs text-slate-500">macOS &amp; Windows</p>
                    </div>
                </div>
                <ol class="space-y-2 text-sm text-slate-600">
                    <li class="flex items-start gap-2"><span class="font-semibold text-primary">1.</span><span>Locate the <strong class="text-slate-900">Install</strong> icon in the address bar.</span></li>
                    <li class="flex items-start gap-2"><span class="font-semibold text-primary">2.</span><span>Click <strong class="text-slate-900">Install</strong> in the popup.</span></li>
                    <li class="flex items-start gap-2"><span class="font-semibold text-primary">3.</span><span>Launch anytime from your dock or taskbar.</span></li>
                </ol>
            </div>
        </div>
    </div>
</section>

{{-- Still Need Help --}}
<section class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-16 lg:py-20 w-full">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 sm:p-8 lg:p-10">
        <div class="max-w-2xl mx-auto text-center mb-10">
            <div class="w-14 h-14 mx-auto rounded-full bg-primary/10 text-primary flex items-center justify-center mb-4">
                <span class="material-symbols-outlined text-3xl" aria-hidden="true">support_agent</span>
            </div>
            <h2 class="font-display text-2xl sm:text-3xl font-bold text-slate-900">
                Still need help or have an unresolved issue?
            </h2>
            <p class="text-base text-slate-600 mt-2 leading-relaxed">
                Our operations desk handles payment audits, campaign adjustments, and agent submission disputes.
            </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
            <div class="bg-slate-50 rounded-xl p-6 flex flex-col justify-between hover:bg-slate-100/80 transition-colors border border-slate-100">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center text-primary shadow-sm border border-slate-100">
                            <span class="material-symbols-outlined" aria-hidden="true">confirmation_number</span>
                        </div>
                        <h3 class="font-display text-base font-semibold text-slate-900">Support Tickets</h3>
                    </div>
                    <p class="text-sm text-slate-600 mb-6 leading-relaxed">
                        Open a tracked ticket or monitor existing resolutions for payment queries and proof validation appeals.
                    </p>
                </div>
                <a href="{{ $supportHref }}" class="w-full py-2.5 px-5 bg-white hover:bg-slate-50 text-slate-900 rounded-lg font-semibold text-sm shadow-sm border border-slate-200 inline-flex items-center justify-center gap-2 transition-colors">
                    <span class="material-symbols-outlined text-base" aria-hidden="true">receipt_long</span>
                    <span>View My Tickets</span>
                </a>
            </div>
            <div class="bg-slate-50 rounded-xl p-6 flex flex-col justify-between hover:bg-slate-100/80 transition-colors border border-slate-100">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center text-emerald-700 shadow-sm border border-slate-100">
                            <span class="material-symbols-outlined" aria-hidden="true">chat</span>
                        </div>
                        <h3 class="font-display text-base font-semibold text-slate-900">Live Direct Support</h3>
                    </div>
                    <p class="text-sm text-slate-600 mb-6 leading-relaxed">
                        Connect with our team via live chat on the Contact page, or open a ticket from your dashboard for payment and campaign help.
                    </p>
                </div>
                <a href="{{ route('contact') }}" class="w-full py-2.5 px-5 bg-primary hover:bg-primary-hover text-white rounded-lg font-semibold text-sm shadow-sm inline-flex items-center justify-center gap-2 transition-colors">
                    <span class="material-symbols-outlined text-base" aria-hidden="true">forum</span>
                    <span>Contact Support</span>
                </a>
            </div>
        </div>
    </div>
</section>

</div>
@endsection
