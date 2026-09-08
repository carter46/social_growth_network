@extends('layouts.marketing')

@section('title', $siteHeading ?? 'Digital Campaign Marketplace')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $heading = $siteHeading ?: 'Get more from your digital content.';
    $tagline = $siteTagline ?: 'Choose the campaign you need, select your preferred package, and get your campaign started in minutes.';
    $ecosystemItems = collect($ecosystemItems ?? []);
    $categoryCards = collect($categoryCards ?? []);
    $featuredProducts = collect($featuredProducts ?? []);
    $popularTags = collect($popularTags ?? []);
    $filterCategories = $categoryCards->filter(fn ($c) => ($c['count'] ?? 0) > 0 || ! empty($c['href']))->values();
    $heroSlides = [
        ['src' => asset('assets/images/homeslider1.jpg'), 'alt' => 'Creator in studio'],
        ['src' => asset('assets/images/homeslider2.jpg'), 'alt' => 'Campaign production'],
        ['src' => asset('assets/images/homeslider3.jpg'), 'alt' => 'Growth analytics'],
    ];
    $badgeTones = [
        'text-red-600',
        'text-purple-600',
        'text-teal-600',
        'text-emerald-600',
        'text-blue-600',
        'text-orange-600',
    ];
    $badgeIcons = ['smart_display', 'share', 'public', 'task_alt', 'campaign', 'groups'];
    $faqs = [
        [
            'q' => 'What is '.$brandName.'?',
            'a' => $brandName.' is a digital campaign marketplace — browse predefined packages, pay upfront, and launch growth or engagement campaigns in minutes.',
        ],
        [
            'q' => 'How do I start a campaign?',
            'a' => 'Open Services, pick a product, choose a package, then check out with wallet, card/transfer, or bank transfer when enabled.',
        ],
        [
            'q' => 'Can I earn as an agent?',
            'a' => 'Yes. Register as an agent, complete available digital tasks with proof, and receive verified payouts.',
        ],
        [
            'q' => 'Where do I track progress?',
            'a' => 'After checkout, campaigns and orders appear in your dashboard so you can monitor delivery and support.',
        ],
    ];
@endphp

{{-- Hero --}}
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
                    @if($index > 0) loading="lazy" @endif
                >
            </div>
        @endforeach
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/70 to-slate-900/40"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-black/20"></div>
    </div>

    <div class="relative z-10 max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24 w-full">
        <div class="max-w-3xl">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-white text-xs font-semibold tracking-wider uppercase mb-6 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                Digital campaigns &amp; growth
            </div>

            <h1 class="text-4xl sm:text-5xl lg:text-[54px] font-extrabold text-white tracking-tight leading-[1.15] mb-5 font-display">
                {{ $heading }}
            </h1>
            <p class="text-lg sm:text-xl text-slate-200/90 font-normal leading-relaxed mb-8 max-w-2xl">
                {{ $tagline }}
            </p>

            <div class="bg-white rounded-xl shadow-2xl p-2.5 sm:p-3 mb-4 max-w-2xl border border-white/40">
                <form action="{{ route('services') }}" method="GET" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <div class="relative flex-1 flex items-center pl-3">
                        <span class="material-symbols-outlined text-slate-400 text-2xl mr-2.5 shrink-0" aria-hidden="true">search</span>
                        <label for="home-services-q" class="sr-only">Search campaigns</label>
                        <input
                            id="home-services-q"
                            type="search"
                            name="q"
                            class="w-full bg-transparent text-slate-800 placeholder-slate-400 text-sm sm:text-base border-none focus:outline-none focus:ring-0 p-0 font-medium"
                            placeholder="What do you want to promote? (e.g. YouTube views, social engagement…)"
                        >
                    </div>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-3.5 rounded-lg font-semibold text-sm sm:text-base flex items-center justify-center gap-2 shadow-md hover:shadow-lg transition-all shrink-0">
                        <span>Search</span>
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                    </button>
                </form>
            </div>

            @if($popularTags->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm text-slate-300 mb-8">
                    <span class="text-slate-400 font-medium">Popular:</span>
                    @foreach($popularTags as $tag)
                        <a class="px-2.5 py-1 rounded-md bg-white/10 hover:bg-white/20 text-white backdrop-blur-sm transition-colors" href="{{ $tag['href'] }}">{{ $tag['label'] }}</a>
                    @endforeach
                </div>
            @else
                <div class="mb-8"></div>
            @endif

            <div class="flex flex-wrap items-center gap-4">
                <a class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white font-semibold text-base px-6 py-3.5 rounded-lg shadow-lg hover:shadow-xl transition-all" href="#services">
                    <span>Explore Services</span>
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                </a>
                <a class="inline-flex items-center justify-center bg-white/10 hover:bg-white/20 text-white font-medium text-base px-6 py-3.5 rounded-lg border border-white/30 backdrop-blur-md transition-all" href="{{ route('register') }}">
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
                :aria-label="'Go to slide {{ $index + 1 }}'"
            ></button>
        @endforeach
    </div>
</section>

{{-- Marketplace catalog: products (+ category filters) --}}
<section class="py-20 lg:py-28 bg-white border-b border-slate-100" id="services"
    x-data="{ filter: 'all' }"
>
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6">
            <div>
                <span class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">Marketplace catalog</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display">What do you want to grow?</h2>
                <p class="text-slate-600 text-base sm:text-lg mt-2 max-w-xl">Browse predefined packages, see upfront pricing, and launch instantly.</p>
            </div>
            @if($filterCategories->isNotEmpty())
                <div class="flex flex-wrap gap-2 p-1.5 bg-slate-100 rounded-xl self-start md:self-auto">
                    <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 font-medium hover:text-slate-900'" class="px-4 py-2 rounded-lg text-sm transition-all">All Campaigns</button>
                    @foreach($filterCategories->take(5) as $cat)
                        <button
                            type="button"
                            @click="filter = '{{ $cat['slug'] }}'"
                            :class="filter === '{{ $cat['slug'] }}' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 font-medium hover:text-slate-900'"
                            class="px-4 py-2 rounded-lg text-sm transition-all"
                        >{{ $cat['label'] ?? $cat['slug'] }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        @if($featuredProducts->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                @foreach($featuredProducts as $product)
                    @php
                        $browse = app(\App\Modules\Catalog\Services\CatalogBrowseService::class);
                        $href = $browse->productUrl($product);
                        $heroUrl = media_url($product->heroMedia ?? null, $product->hero_image, 'medium');
                        $catSlug = $product->productType?->serviceCategory?->slug ?? '';
                        $catLabel = $product->productType?->serviceCategory?->name
                            ?? $product->productType?->name
                            ?? 'Campaign';
                        $tone = $badgeTones[$loop->index % count($badgeTones)];
                        $icon = $badgeIcons[$loop->index % count($badgeIcons)];
                    @endphp
                    <div
                        class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between"
                        x-show="filter === 'all' || filter === '{{ $catSlug }}'"
                    >
                        <div>
                            <div class="relative h-48 w-full overflow-hidden bg-slate-100">
                                @if($heroUrl)
                                    <img src="{{ $heroUrl }}" alt="{{ $product->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-primary/30 via-slate-200 to-slate-100"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                                <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/95 backdrop-blur-md {{ $tone }} text-xs font-bold shadow-sm">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">{{ $icon }}</span>
                                    {{ $catLabel }}
                                </span>
                            </div>
                            <div class="p-5">
                                <h3 class="text-xl font-bold text-slate-900 mb-2 group-hover:text-primary transition-colors font-display">
                                    <a href="{{ $href }}">{{ $product->title }}</a>
                                </h3>
                                <p class="text-slate-600 text-sm leading-relaxed mb-4 line-clamp-3">
                                    {{ $product->short_description ?: 'Predefined package with upfront pricing and secure checkout.' }}
                                </p>
                            </div>
                        </div>
                        <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between mt-auto gap-3">
                            <span class="text-xs font-medium text-slate-500">From ₦{{ number_format($product->displayPrice(), 0) }}</span>
                            <a class="inline-flex items-center gap-1 text-primary font-semibold text-sm hover:underline shrink-0" href="{{ $href }}">
                                Explore Packages →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif($categoryCards->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                @foreach($categoryCards->take(8) as $card)
                    @php
                        $tone = $badgeTones[$loop->index % count($badgeTones)];
                        $icon = $badgeIcons[$loop->index % count($badgeIcons)];
                        $image = $card['card_image'] ?? $card['banner_image'] ?? null;
                    @endphp
                    <div class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between">
                        <div>
                            <div class="relative h-48 w-full overflow-hidden bg-slate-100">
                                @if($image)
                                    <img src="{{ $image }}" alt="{{ $card['label'] ?? '' }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-primary/30 via-slate-200 to-slate-100"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                                <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/95 {{ $tone }} text-xs font-bold shadow-sm">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">{{ $icon }}</span>
                                    {{ $card['label'] ?? 'Category' }}
                                </span>
                            </div>
                            <div class="p-5">
                                <h3 class="text-xl font-bold text-slate-900 mb-2 group-hover:text-primary font-display">{{ $card['label'] ?? 'Category' }}</h3>
                                <p class="text-slate-600 text-sm leading-relaxed mb-4 line-clamp-3">{{ $card['short_description'] ?? $card['hero_subtitle'] ?? 'Browse packages in this category.' }}</p>
                            </div>
                        </div>
                        <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
                            <span class="text-xs font-medium text-slate-500">{{ ($card['count'] ?? 0) }} packages</span>
                            <a class="inline-flex items-center gap-1 text-primary font-semibold text-sm hover:underline" href="{{ $card['href'] ?? route('services') }}">
                                Explore Packages →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-slate-500 text-center py-12">Campaign packages will appear here once published in the catalog.</p>
        @endif

        <div class="mt-10 text-center">
            <a href="{{ route('services') }}" class="inline-flex items-center gap-2 text-primary font-semibold hover:underline">
                View all services
                <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
            </a>
        </div>
    </div>
</section>

{{-- How it works --}}
<section class="py-20 lg:py-24 bg-surface-muted" id="how-it-works">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <span class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">How {{ $brandName }} works</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display">Launch in three simple steps.</h2>
            <p class="text-slate-600 text-base sm:text-lg mt-2">A predictable, straightforward framework built for zero friction.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach([
                ['n' => '01', 'icon' => 'category', 'title' => 'Choose', 'body' => 'Pick the campaign and predefined quantity that matches your goal with upfront fixed pricing.', 'foot' => 'Step 1 • No haggling'],
                ['n' => '02', 'icon' => 'tune', 'title' => 'Set Up', 'body' => 'Add your target URL and simple campaign instructions in our streamlined submission form.', 'foot' => 'Step 2 • 2-minute setup'],
                ['n' => '03', 'icon' => 'rocket_launch', 'title' => 'Launch', 'body' => 'Pay securely and track delivery from your dashboard as work completes.', 'foot' => 'Step 3 • Secure checkout'],
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

{{-- Creators --}}
<section class="py-20 lg:py-28 bg-white overflow-hidden" id="creators">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
            <div class="lg:col-span-6 relative">
                <div class="relative rounded-2xl overflow-hidden shadow-xl border border-slate-100">
                    <img
                        src="{{ asset('assets/images/homeslider2.jpg') }}"
                        alt="Creators launching campaigns on {{ $brandName }}"
                        class="w-full h-auto max-h-[580px] object-cover object-center"
                        loading="lazy"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-transparent to-transparent"></div>
                    <div class="absolute bottom-6 left-6 right-6 text-white">
                        <p class="font-bold text-lg">Built for creators &amp; founders</p>
                        <p class="text-sm text-slate-200">Fixed packages · Secure checkout · Dashboard tracking</p>
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
                    Stop negotiating with unpredictable freelancers. {{ $brandName }} gives you fixed upfront packages, secure checkout, and clear delivery tracking.
                </p>
                <div class="space-y-4 mb-9 w-full">
                    @foreach([
                        ['title' => 'Upfront predefined pricing — zero bidding wars', 'body' => 'Know exactly what you pay and get before committing any budget.'],
                        ['title' => 'Clear packages with measurable deliverables', 'body' => 'Pick a tier that matches your goal and launch without custom quotes.'],
                        ['title' => 'Track progress directly in your account', 'body' => 'Orders and campaigns live in your dashboard after checkout.'],
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
                    <a class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white font-semibold text-base px-6 py-3.5 rounded-lg shadow-sm hover:shadow transition-all" href="{{ route('services') }}">
                        <span>Start a Campaign</span>
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                    </a>
                    <a class="inline-flex items-center gap-1.5 text-slate-700 hover:text-primary font-semibold text-base transition-colors" href="{{ route('register') }}">
                        <span>Create creator account</span>
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Agents --}}
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
                        Join the Agent side of {{ $brandName }}. Discover available tasks, submit proof, and receive verified payouts.
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
                    @php $preview = $featuredProducts->first(); @endphp
                    <div class="bg-slate-50 rounded-xl p-5 border border-slate-200">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-200/80 text-xs font-semibold text-slate-500">
                            <span class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Live marketplace
                            </span>
                            <span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded border border-emerald-200 font-bold">Open spots</span>
                        </div>
                        <div class="py-3">
                            <h4 class="font-bold text-slate-900 text-base mb-1">{{ $preview?->title ?? 'Digital campaign tasks' }}</h4>
                            <p class="text-xs text-slate-500 mb-3">{{ $preview?->short_description ?: 'Complete structured tasks, submit proof, and get paid after verification.' }}</p>
                            <div class="flex items-center justify-between bg-white px-3 py-2 rounded-lg border border-slate-200/70 mb-3">
                                <span class="text-xs text-slate-600 font-medium">Creator packages from</span>
                                <span class="text-sm font-extrabold text-emerald-600">
                                    @if($preview)
                                        ₦{{ number_format($preview->displayPrice(), 0) }}
                                    @else
                                        Browse catalog
                                    @endif
                                </span>
                            </div>
                        </div>
                        <a href="{{ $preview ? app(\App\Modules\Catalog\Services\CatalogBrowseService::class)->productUrl($preview) : route('register.agent') }}" class="w-full py-2 bg-slate-200/70 hover:bg-primary hover:text-white text-slate-700 font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-1">
                            <span>{{ $preview ? 'Preview package' : 'Join as agent' }}</span>
                            <span class="material-symbols-outlined text-sm" aria-hidden="true">open_in_new</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Final CTA --}}
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
                Browse available packages, select your tier, and launch on {{ $brandName }}.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                <a class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white font-semibold text-base px-8 py-4 rounded-lg shadow-lg hover:shadow-xl transition-all" href="{{ route('services') }}">
                    <span>Explore Services</span>
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                </a>
            </div>
        </div>
    </div>
</section>

{{-- FAQ --}}
<section class="py-16 sm:py-20 bg-white" id="faq">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-extrabold font-display text-slate-900 mb-3">Frequently asked questions</h2>
            <p class="text-slate-600">Quick answers about buying and earning on {{ $brandName }}.</p>
        </div>
        <div class="mx-auto max-w-3xl space-y-4">
            @foreach($faqs as $faq)
                <details class="group rounded-xl border border-slate-200 bg-slate-50/50 px-5 py-4">
                    <summary class="cursor-pointer list-none font-semibold text-slate-900">{{ $faq['q'] }}</summary>
                    <p class="mt-3 text-sm text-slate-600 leading-relaxed">{{ $faq['a'] }}</p>
                </details>
            @endforeach
        </div>
        <p class="mt-10 text-center text-sm text-slate-500">
            Have more questions? View our <a class="text-primary font-semibold hover:underline" href="{{ route('help') }}">Help Center</a>
            or <a class="text-primary font-semibold hover:underline" href="{{ route('contact') }}">contact support</a>.
        </p>
    </div>
</section>
@endsection
