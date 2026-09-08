@extends('layouts.marketing')

@section('title', $siteHeading ?? 'Digital Campaign Marketplace')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $heading = filled($siteHeading ?? null) ? $siteHeading : 'Get more from your digital content.';
    $tagline = filled($siteTagline ?? null)
        ? $siteTagline
        : 'Choose the campaign you need, select your preferred package, and get your campaign started in minutes.';
    $categoryCards = collect($categoryCards ?? [])->values();
    $featuredProducts = collect($featuredProducts ?? []);
    $filterCategories = $categoryCards->take(5);
    $marketplaceCards = $categoryCards->take(5);
    $heroSlides = [
        ['src' => asset('assets/images/homeslider1.jpg'), 'alt' => 'Video creator in creative studio'],
        ['src' => asset('assets/images/homeslider2.jpg'), 'alt' => 'Creator filming with ring light'],
        ['src' => asset('assets/images/homeslider3.jpg'), 'alt' => 'Entrepreneur reviewing campaign analytics'],
    ];
    $badgeTones = [
        'text-red-600',
        'text-purple-600',
        'text-teal-600',
        'text-emerald-600',
    ];
    $badgeIcons = ['smart_display', 'share', 'public', 'task_alt'];
    $agentPreview = $featuredProducts->first(fn ($p) => (bool) ($p->is_campaign ?? false))
        ?? $featuredProducts->first();
@endphp

{{-- 2. Hero --}}
<section
    class="relative min-h-[640px] lg:min-h-[700px] flex items-center overflow-hidden bg-navy-dark"
    x-data="{
        current: 0,
        total: {{ count($heroSlides) }},
        timer: null,
        init() {
            this.timer = setInterval(() => { this.current = (this.current + 1) % this.total }, 6000);
        },
        destroy() {
            if (this.timer) clearInterval(this.timer);
        },
        go(i) { this.current = i; }
    }"
>
    <div class="absolute inset-0 z-0">
        @foreach($heroSlides as $index => $slide)
            <div
                class="hero-slide absolute inset-0"
                :class="current === {{ $index }} ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                @if($index !== 0) style="opacity: 0;" @endif
            >
                <img
                    src="{{ $slide['src'] }}"
                    alt="{{ $slide['alt'] }}"
                    class="w-full h-full object-cover object-center"
                    :class="current === {{ $index }} ? 'transform scale-100 transition-transform duration-[10000ms] ease-out' : ''"
                    @if($index > 0) loading="lazy" @endif
                >
            </div>
        @endforeach
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/55 via-slate-900/35 to-slate-900/20"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/50 via-transparent to-black/10"></div>
    </div>

    <div class="relative z-10 max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24 w-full">
        <div class="max-w-3xl w-full">
            <h1 class="text-4xl sm:text-5xl lg:text-[54px] font-extrabold text-white tracking-tight leading-[1.15] mb-5 font-display">
                {{ $heading }}
            </h1>
            <p class="text-lg sm:text-xl text-slate-100/90 font-normal leading-relaxed mb-8 max-w-2xl">
                {{ $tagline }}
            </p>

            <form
                action="{{ route('services') }}"
                method="GET"
                class="w-full max-w-2xl bg-white/45 backdrop-blur-md border border-white/40 rounded-xl p-1.5 shadow-xl flex flex-col sm:flex-row items-stretch sm:items-center gap-1.5 mb-6"
            >
                <div class="flex items-center gap-2 px-3 py-2 w-full min-w-0">
                    <span class="material-symbols-outlined text-slate-600/80 shrink-0" aria-hidden="true">search</span>
                    <label for="home-services-q" class="sr-only">Search campaigns</label>
                    <input
                        id="home-services-q"
                        type="search"
                        name="q"
                        class="w-full min-w-0 bg-transparent text-slate-900 text-sm placeholder:text-slate-600/70 focus:outline-none border-0 focus:ring-0 p-0"
                        placeholder="Search campaign services…"
                    >
                </div>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-primary/90 hover:bg-primary text-white text-sm font-semibold rounded-lg transition-colors shrink-0">
                    Search
                </button>
            </form>

            <div class="flex flex-wrap items-center gap-2.5 sm:gap-4">
                <a class="inline-flex items-center gap-1.5 sm:gap-2 bg-primary hover:bg-primary-hover text-white font-semibold text-sm sm:text-base px-4 py-2.5 sm:px-6 sm:py-3.5 rounded-lg shadow-lg hover:shadow-xl transition-all" href="#services">
                    <span>Explore Services</span>
                    <span class="material-symbols-outlined text-base sm:text-lg" aria-hidden="true">arrow_forward</span>
                </a>
                <a class="inline-flex items-center justify-center bg-white/10 hover:bg-white/20 text-white font-medium text-sm sm:text-base px-4 py-2.5 sm:px-6 sm:py-3.5 rounded-lg border border-white/30 backdrop-blur-md transition-all" href="#creators">
                    Create Campaign
                </a>
            </div>
        </div>
    </div>

    <div class="absolute bottom-6 left-0 right-0 z-20 flex justify-center items-center gap-2.5">
        @foreach($heroSlides as $index => $slide)
            <button
                type="button"
                @click="go({{ $index }})"
                :class="current === {{ $index }} ? 'h-2 w-8 bg-white' : 'h-2 w-2 bg-white/40 hover:bg-white/70'"
                class="rounded-full transition-all duration-300"
                aria-label="Go to slide {{ $index + 1 }}"
            ></button>
        @endforeach
    </div>
</section>

{{-- 3. Marketplace catalog: product cards + category filter pills --}}
<section class="py-20 lg:py-28 bg-white border-b border-slate-100" id="services" x-data="{ filter: 'all' }">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6">
            <div>
                <span class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">Marketplace catalog</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display">What do you want to grow?</h2>
                <p class="text-slate-600 text-base sm:text-lg mt-2 max-w-xl">Browse predefined packages, see upfront pricing, and launch instantly.</p>
            </div>
            @if($filterCategories->isNotEmpty())
                {{-- Desktop: wrap pills --}}
                <div class="hidden md:flex flex-wrap gap-2 p-1.5 bg-slate-100 rounded-xl self-start md:self-auto">
                    <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 font-medium hover:text-slate-900'" class="px-4 py-2 rounded-lg text-sm transition-all">All</button>
                    @foreach($filterCategories as $cat)
                        <button
                            type="button"
                            @click="filter = '{{ $cat['slug'] }}'"
                            :class="filter === '{{ $cat['slug'] }}' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 font-medium hover:text-slate-900'"
                            class="px-4 py-2 rounded-lg text-sm transition-all"
                        >{{ $cat['label'] ?? $cat['slug'] }}</button>
                    @endforeach
                </div>

                {{-- Mobile: All fixed; other categories scroll underneath --}}
                <div class="md:hidden relative w-full">
                    <div class="absolute inset-y-0 left-0 z-10 flex items-center bg-slate-100 pl-1.5 pr-1 rounded-l-xl">
                        <button
                            type="button"
                            @click="filter = 'all'"
                            :class="filter === 'all' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 font-medium'"
                            class="px-3.5 py-2 rounded-lg text-sm transition-all shrink-0"
                        >All</button>
                        <div class="pointer-events-none absolute top-0 bottom-0 left-full w-6 bg-gradient-to-r from-slate-100 to-transparent" aria-hidden="true"></div>
                    </div>
                    <div class="overflow-x-auto scrollbar-hide bg-slate-100 rounded-xl pl-[4.25rem]">
                        <div class="flex items-center gap-2 p-1.5 min-w-max">
                            @foreach($filterCategories as $cat)
                                <button
                                    type="button"
                                    @click="filter = '{{ $cat['slug'] }}'"
                                    :class="filter === '{{ $cat['slug'] }}' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 font-medium'"
                                    class="px-3.5 py-2 rounded-lg text-sm transition-all shrink-0"
                                >{{ $cat['label'] ?? $cat['slug'] }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        @if($featuredProducts->isNotEmpty())
            @php $browse = app(\App\Modules\Catalog\Services\CatalogBrowseService::class); @endphp
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                @foreach($featuredProducts as $product)
                    @php
                        $href = $browse->productUrl($product);
                        $heroUrl = media_url($product->heroMedia ?? null, $product->hero_image, 'medium');
                        $tone = $badgeTones[$loop->index % count($badgeTones)];
                        $icon = $badgeIcons[$loop->index % count($badgeIcons)];
                        $categorySlug = $product->categorySlug() ?? '';
                        $categoryLabel = $product->serviceCategory?->name
                            ?? ($product->productType?->serviceCategory?->name ?? 'Campaign');
                        $fromPrice = $product->displayPrice();
                    @endphp
                    <div
                        class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between"
                        x-show="filter === 'all' || filter === '{{ $categorySlug }}'"
                    >
                        <div>
                            <div class="relative h-48 w-full overflow-hidden bg-slate-100">
                                @if($heroUrl)
                                    <img src="{{ $heroUrl }}" alt="{{ $product->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-primary/25 via-slate-200 to-slate-100"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                                <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/95 backdrop-blur-md {{ $tone }} text-xs font-bold shadow-sm">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">{{ $icon }}</span>
                                    {{ $categoryLabel }}
                                </span>
                            </div>
                            <div class="p-5">
                                <h3 class="text-xl font-bold text-slate-900 mb-2 group-hover:text-primary transition-colors font-display">
                                    <a href="{{ $href }}">{{ $product->title }}</a>
                                </h3>
                                <p class="text-slate-600 text-sm leading-relaxed mb-4">
                                    {{ $product->short_description ?: 'Predefined package with upfront pricing and secure checkout.' }}
                                </p>
                            </div>
                        </div>
                        <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
                            <span class="text-xs font-medium text-slate-500">
                                @if($fromPrice)
                                    From ₦{{ number_format((float) $fromPrice, 0) }}
                                @else
                                    From predefined packages
                                @endif
                            </span>
                            <a class="inline-flex items-center gap-1 text-primary font-semibold text-sm hover:underline group-hover:translate-x-0.5 transition-transform" href="{{ $href }}">
                                View package →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif($marketplaceCards->isNotEmpty())
            {{-- Fallback: category cards if products are empty --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                @foreach($marketplaceCards as $card)
                    @php
                        $tone = $badgeTones[$loop->index % count($badgeTones)];
                        $icon = $badgeIcons[$loop->index % count($badgeIcons)];
                        $image = $card['card_image'] ?? $card['banner_image'] ?? null;
                        $href = $card['href'] ?? route('services');
                        $slug = $card['slug'] ?? '';
                    @endphp
                    <div
                        class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between"
                        x-show="filter === 'all' || filter === '{{ $slug }}'"
                    >
                        <div>
                            <div class="relative h-48 w-full overflow-hidden bg-slate-100">
                                @if($image)
                                    <img src="{{ $image }}" alt="{{ $card['label'] ?? 'Campaign' }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-primary/25 via-slate-200 to-slate-100"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                                <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/95 backdrop-blur-md {{ $tone }} text-xs font-bold shadow-sm">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">{{ $icon }}</span>
                                    {{ $card['label'] ?? 'Campaigns' }}
                                </span>
                            </div>
                            <div class="p-5">
                                <h3 class="text-xl font-bold text-slate-900 mb-2 group-hover:text-primary transition-colors font-display">
                                    <a href="{{ $href }}">{{ $card['label'] ?? 'Campaigns' }}</a>
                                </h3>
                                <p class="text-slate-600 text-sm leading-relaxed mb-4">
                                    {{ $card['short_description'] ?? $card['hero_subtitle'] ?? 'Predefined packages with upfront pricing and secure checkout.' }}
                                </p>
                            </div>
                        </div>
                        <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
                            <span class="text-xs font-medium text-slate-500">
                                @if(! empty($card['from_price']))
                                    From ₦{{ number_format((float) $card['from_price'], 0) }}
                                @else
                                    From predefined packages
                                @endif
                            </span>
                            <a class="inline-flex items-center gap-1 text-primary font-semibold text-sm hover:underline group-hover:translate-x-0.5 transition-transform" href="{{ $href }}">
                                Explore Packages →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-slate-500 text-center py-12">Campaign packages will appear here once published in the catalog.</p>
        @endif
    </div>
</section>

{{-- 4. How it works --}}
<section class="py-20 lg:py-24 bg-[#F8FAFC]" id="how-it-works">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <span class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">How {{ $brandName }} works</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display">Launch in three simple steps.</h2>
            <p class="text-slate-600 text-base sm:text-lg mt-2">A predictable, straightforward framework built for zero friction.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
            @foreach([
                ['n' => '01', 'icon' => 'category', 'title' => 'Choose', 'body' => 'Pick the campaign and predefined quantity that matches your goal with upfront fixed pricing.', 'foot' => 'Step 1 • No haggling'],
                ['n' => '02', 'icon' => 'tune', 'title' => 'Set Up', 'body' => 'Add your target URL and simple campaign instructions in our streamlined submission form.', 'foot' => 'Step 2 • 2-minute setup'],
                ['n' => '03', 'icon' => 'rocket_launch', 'title' => 'Launch', 'body' => 'Pay securely and watch verified task completion in real-time as tasks post to your ledger.', 'foot' => 'Step 3 • Escrow protected'],
            ] as $step)
                <div class="bg-white p-8 rounded-xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-6">
                            <span class="text-3xl font-black text-primary font-display">{{ $step['n'] }}</span>
                            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-primary">
                                <span class="material-symbols-outlined text-2xl" aria-hidden="true">{{ $step['icon'] }}</span>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-2.5 font-display">{{ $step['title'] }}</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">{{ $step['body'] }}</p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        <span>{{ $step['foot'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- 5. Creators --}}
<section class="py-20 lg:py-28 bg-white overflow-hidden" id="creators">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
            <div class="lg:col-span-6 relative">
                <div class="relative rounded-2xl overflow-hidden shadow-xl border border-slate-100">
                    <img
                        src="{{ asset('assets/images/homeslider2.jpg') }}"
                        alt="Creator launching campaigns on {{ $brandName }}"
                        class="w-full h-auto max-h-[580px] object-cover object-center"
                        loading="lazy"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-transparent to-transparent"></div>
                    <div class="absolute bottom-6 left-6 right-6 text-white">
                        <p class="font-bold text-lg">Built for creators &amp; founders</p>
                        <p class="text-sm text-slate-200">Independent creators · Campaign packages · {{ $brandName }}</p>
                    </div>
                </div>
                <div class="absolute -bottom-6 -right-6 -z-10 w-64 h-64 bg-blue-100/60 rounded-full blur-3xl pointer-events-none"></div>
            </div>
            <div class="lg:col-span-6 flex flex-col items-start">
                <span class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-2">For creators &amp; founders</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight mb-5 font-display">
                    Everything you need to get your campaign moving.
                </h2>
                <p class="text-slate-600 text-base sm:text-lg leading-relaxed mb-8">
                    Stop negotiating with unpredictable freelancers or risking bot farms. {{ $brandName }} gives you fixed upfront packages, escrow protection, and verified human activity.
                </p>
                <div class="space-y-4 mb-9 w-full">
                    @foreach([
                        ['title' => 'Upfront predefined pricing — zero bidding wars', 'body' => 'Know exactly what you pay and get before committing any budget.'],
                        ['title' => 'Guaranteed verified human activity & proof validation', 'body' => 'Every completion undergoes multi-point telemetry and submission checks.'],
                        ['title' => 'Real-time progress monitoring directly in your account', 'body' => 'Track execution velocity, view completed agent actions, and download reports.'],
                    ] as $point)
                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-base font-bold" aria-hidden="true">check</span>
                            </div>
                            <div>
                                <h4 class="text-base font-bold text-slate-900">{{ $point['title'] }}</h4>
                                <p class="text-sm text-slate-500">{{ $point['body'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-5">
                    <a class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white font-semibold text-base px-6 py-3.5 rounded-lg shadow-sm hover:shadow transition-all" href="#services">
                        <span>Start a Campaign</span>
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                    </a>
                    <a class="inline-flex items-center gap-1.5 text-slate-700 hover:text-primary font-semibold text-base transition-colors" href="{{ route('help') }}">
                        <span>See Sample Reports</span>
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 6. Agents --}}
<section class="py-14 bg-slate-50 border-y border-slate-200/60" id="agents">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-2xl p-6 sm:p-8 lg:p-10 border border-slate-200/80 shadow-sm">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-7">
                    <span class="inline-flex items-center gap-1 text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        <span class="material-symbols-outlined text-base text-slate-400" aria-hidden="true">payments</span>
                        Earn on {{ $brandName }}
                    </span>
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mb-3 font-display">
                        Want to earn by completing digital tasks?
                    </h3>
                    <p class="text-slate-600 text-base leading-relaxed mb-6 max-w-xl">
                        Join the Agent side of {{ $brandName }}. Discover available tasks on your phone or computer, submit proof, and receive verified fast payouts.
                    </p>
                    <div class="flex flex-wrap items-center gap-4">
                        <a class="inline-flex items-center justify-center bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm sm:text-base px-5 py-2.5 rounded-lg shadow-sm transition-colors" href="{{ route('register.agent') }}">
                            Become an Agent
                        </a>
                        <a class="inline-flex items-center gap-1 text-sm sm:text-base font-semibold text-slate-600 hover:text-slate-900 transition-colors" href="#how-it-works">
                            <span>Learn More</span>
                            <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
                        </a>
                    </div>
                </div>
                <div class="lg:col-span-5 w-full">
                    @php
                        $previewTitle = $agentPreview?->title ?? 'YouTube Video Review & Feedback';
                        $previewBody = $agentPreview?->short_description
                            ?: 'Watch 3 minutes, provide honest feedback, and verify timestamp.';
                        $reward = $agentPreview?->agent_reward_per_completion ?? null;
                        $previewHref = $agentPreview
                            ? app(\App\Modules\Catalog\Services\CatalogBrowseService::class)->productUrl($agentPreview)
                            : route('register.agent');
                    @endphp
                    <div class="bg-slate-50 rounded-xl p-5 border border-slate-200">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-200/80 text-xs font-semibold text-slate-500">
                            <span class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Live Task Available
                            </span>
                            <span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded border border-emerald-200 font-bold">Open spots</span>
                        </div>
                        <div class="py-3">
                            <h4 class="font-bold text-slate-900 text-base mb-1">{{ $previewTitle }}</h4>
                            <p class="text-xs text-slate-500 mb-3">{{ $previewBody }}</p>
                            <div class="flex items-center justify-between bg-white px-3 py-2 rounded-lg border border-slate-200/70 mb-3">
                                <span class="text-xs text-slate-600 font-medium">Verified Reward</span>
                                <span class="text-sm font-extrabold text-emerald-600">
                                    @if($reward !== null && (float) $reward > 0)
                                        ₦{{ number_format((float) $reward, 0) }} per task
                                    @elseif($agentPreview)
                                        From ₦{{ number_format($agentPreview->displayPrice(), 0) }}
                                    @else
                                        ₦250 per review · 5 mins
                                    @endif
                                </span>
                            </div>
                        </div>
                        <a href="{{ $previewHref }}" class="w-full py-2 bg-slate-200/70 hover:bg-primary hover:text-white text-slate-700 font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-1">
                            <span>Preview Task Requirements</span>
                            <span class="material-symbols-outlined text-sm" aria-hidden="true">open_in_new</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 7. Final CTA --}}
<section class="py-20 lg:py-24 bg-navy-dark text-white relative overflow-hidden">
    <div class="absolute -top-24 -right-24 w-96 h-96 bg-primary/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="relative z-10 max-w-site mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="max-w-2xl mx-auto">
            <div class="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-blue-400 mx-auto mb-6">
                <span class="material-symbols-outlined text-3xl" aria-hidden="true">campaign</span>
            </div>
            <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight mb-4 text-white font-display">
                Ready to start your campaign?
            </h2>
            <p class="text-slate-300 text-base sm:text-lg mb-8 max-w-xl mx-auto">
                Browse available packages, select your tier, and launch in less than 2 minutes.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                <a class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white font-semibold text-base px-8 py-4 rounded-lg shadow-lg hover:shadow-xl transition-all" href="#services">
                    <span>Explore Services</span>
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                </a>
            </div>
        </div>
    </div>
</section>

{{-- 8. FAQ strip (matches design) --}}
<section class="py-12 bg-white" id="faq">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-slate-500">
        Have questions? View our comprehensive
        <a class="text-primary font-semibold hover:underline" href="{{ route('help') }}">FAQ guide</a>
        or reach out to
        <a class="text-primary font-semibold hover:underline" href="{{ route('contact') }}">24/7 campaign support</a>.
    </div>
</section>
@endsection
