@extends('layouts.marketing')

@section('title', $siteHeading ?? 'Digital Campaign Marketplace')

@section('content')
@php
    $brandName = $siteName ?? config('app.name', 'Social Growth Network');
    $categoryCards = collect($categoryCards ?? [])->values();
    $marketplaceCatalog = $marketplaceCatalog ?? ['filters' => [], 'products' => ['all' => []]];
    $filterCategories = collect($marketplaceCatalog['filters'] ?? []);
    $marketplaceCards = $categoryCards
        ->filter(fn ($card) => ! in_array(($card['slug'] ?? ''), ['social-media', 'youtube'], true))
        ->take(5)
        ->values();
    $youtubeCatalog = $youtubeCatalog ?? ['featured' => null, 'others' => []];
    $watchHours = $youtubeCatalog['featured'] ?? null;
    $youtubeOthers = collect($youtubeCatalog['others'] ?? []);
    $watchHoursHref = is_array($watchHours) && filled($watchHours['href'] ?? null)
        ? $watchHours['href']
        : '#youtube-services';
    $youtubeFeatureImage = (is_array($watchHours) && filled($watchHours['hero_url'] ?? null))
        ? $watchHours['hero_url']
        : asset('assets/images/Social_Media.jpg');
    $badgeTones = [
        'text-red-600',
        'text-purple-600',
        'text-teal-600',
        'text-emerald-600',
        'text-sky-600',
    ];
    $badgeIcons = ['smart_display', 'share', 'public', 'task_alt', 'campaign'];
    $agentTaskPreview = is_array($agentTaskPreview ?? null) ? $agentTaskPreview : null;
    /** Accent “YouTube” in large titles (red on light, white on red surfaces). */
    $ytWord = static function (string $text, string $tone = 'red'): string {
        $class = $tone === 'white' ? 'text-white' : 'text-red-600';

        return preg_replace(
            '/\bYouTube\b/u',
            '<span class="'.$class.'">YouTube</span>',
            e($text)
        ) ?? e($text);
    };
@endphp

{{-- 1. White YouTube Watch Hours hero --}}
<section class="home-hero relative flex items-center overflow-hidden bg-white border-b border-slate-100">
    <div
        class="home-hero-white-motif absolute inset-0 z-0 pointer-events-none"
        style="background-image: url('{{ asset('assets/images/home_whitepng.png') }}');"
        aria-hidden="true"
    ></div>
    <div class="absolute inset-0 z-0 pointer-events-none bg-gradient-to-b from-white/55 via-white/40 to-white/65" aria-hidden="true"></div>

    <div class="relative z-10 max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 lg:py-16 w-full">
        <div class="max-w-3xl mx-auto w-full text-center">
            <p class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-3">
                {{ $brandName }}
            </p>
            <h1 class="text-2xl sm:text-4xl md:text-5xl lg:text-[52px] font-extrabold text-slate-900 tracking-tight leading-[1.15] mb-4 sm:mb-5 font-display">
                {!! $ytWord('Grow your YouTube watch hours with ready campaign packages.') !!}
            </h1>
            <p class="text-base sm:text-lg lg:text-xl text-slate-600 font-normal leading-relaxed mb-8 sm:mb-10 max-w-xl mx-auto">
                Launch Watch Hours campaigns with upfront pricing, a clear checkout flow, and progress you can track in your account.
            </p>

            <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center justify-center gap-3 sm:gap-4">
                <a
                    class="inline-flex items-center justify-center gap-1.5 sm:gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm sm:text-base px-5 py-3 sm:px-6 sm:py-3.5 rounded-lg shadow-sm hover:shadow transition-all"
                    href="{{ route('register') }}"
                >
                    <span>Pay For Watch Hours</span>
                    <span class="material-symbols-outlined text-base sm:text-lg" aria-hidden="true">arrow_forward</span>
                </a>
                <a
                    class="inline-flex items-center justify-center bg-white hover:bg-slate-50 text-slate-800 font-medium text-sm sm:text-base px-5 py-3 sm:px-6 sm:py-3.5 rounded-lg border border-slate-200 transition-all"
                    href="{{ route('agents') }}"
                >
                    Watch &amp; Earn
                </a>
            </div>
        </div>
    </div>
</section>

{{-- 2. YouTube services: featured Watch Hours + supporting products --}}
<section class="py-20 lg:py-28 bg-slate-50 border-b border-slate-100" id="youtube-services">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-12 lg:mb-16 max-w-2xl">
            <span class="text-slate-500 font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">YouTube services</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display">{!! $ytWord('Built around YouTube growth.') !!}</h2>
            <p class="text-slate-600 text-base sm:text-lg mt-2">Watch Hours leads the catalog. Views, Likes, and Comments sit alongside as supporting packages.</p>
        </div>

        @if(is_array($watchHours))
            <div class="home-yt-featured mb-12 lg:mb-16 rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                <div class="grid grid-cols-1 lg:grid-cols-12">
                    <div class="lg:col-span-6 p-8 sm:p-10 lg:p-12 flex flex-col justify-center order-2 lg:order-1">
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                            <span class="material-symbols-outlined text-base text-red-600" aria-hidden="true">smart_display</span>
                            Featured
                        </span>
                        <h3 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-slate-900 tracking-tight font-display mb-4">
                            {!! $ytWord($watchHours['title'] ?? 'YouTube Watch Hours') !!}
                        </h3>
                        <p class="text-slate-600 text-base sm:text-lg leading-relaxed mb-6 max-w-lg">
                            {{ $watchHours['short_description'] }}
                        </p>
                        <p class="text-sm text-slate-500 mb-6">
                            At checkout you’ll provide a public video URL where you want watch sessions delivered.
                        </p>
                        <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 sm:gap-4">
                            @if(! empty($watchHours['from_price']))
                                <span class="text-sm font-semibold text-slate-700 sm:mr-1">
                                    From ₦{{ number_format((float) $watchHours['from_price'], 0) }}
                                </span>
                            @endif
                            <a
                                class="inline-flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm sm:text-base px-5 py-3 rounded-lg shadow-sm transition-all w-full sm:w-auto"
                                href="{{ route('register') }}"
                            >
                                <span>Pay For Watch Hours</span>
                                <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                    <div class="lg:col-span-6 relative min-h-[240px] sm:min-h-[320px] lg:min-h-full order-1 lg:order-2 bg-slate-100">
                        <img
                            src="{{ $youtubeFeatureImage }}"
                            alt="{{ $watchHours['title'] ?? 'YouTube Watch Hours' }}"
                            class="absolute inset-0 w-full h-full object-cover"
                            loading="lazy"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-red-950/35 via-transparent to-transparent" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        @endif

        @if($youtubeOthers->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-lg sm:text-xl font-bold text-slate-900 font-display">More YouTube packages</h3>
                <p class="text-slate-500 text-sm mt-1">Views, Likes, and Comments with the same upfront pricing model.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                @foreach($youtubeOthers as $index => $product)
                    @php
                        $tone = $badgeTones[$index % count($badgeTones)];
                        $icon = $badgeIcons[$index % count($badgeIcons)];
                    @endphp
                    <div class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between">
                        <div>
                            <div class="relative h-48 w-full overflow-hidden bg-slate-100">
                                @if(! empty($product['hero_url']))
                                    <img src="{{ $product['hero_url'] }}" alt="{{ $product['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                @else
                                    <img src="{{ asset('assets/images/Social_Media.jpg') }}" alt="{{ $product['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                                <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/95 backdrop-blur-md {{ $tone }} text-xs font-bold shadow-sm">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">{{ $icon }}</span>
                                    {{ $product['category_label'] ?? 'YouTube' }}
                                </span>
                            </div>
                            <div class="p-5">
                                <h3 class="text-xl font-bold text-slate-900 mb-2 group-hover:text-primary transition-colors font-display">
                                    <a href="{{ $product['href'] }}">{{ $product['title'] }}</a>
                                </h3>
                                <p class="text-slate-600 text-sm leading-relaxed mb-4">{{ $product['short_description'] }}</p>
                            </div>
                        </div>
                        <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
                            <span class="text-xs font-medium text-slate-500">
                                @if(! empty($product['from_price']))
                                    From ₦{{ number_format((float) $product['from_price'], 0) }}
                                @else
                                    From predefined packages
                                @endif
                            </span>
                            <a class="inline-flex items-center gap-1 text-primary font-semibold text-sm hover:underline group-hover:translate-x-0.5 transition-transform" href="{{ $product['href'] }}">View package →</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif(! is_array($watchHours))
            <p class="text-slate-500 text-center py-12">YouTube packages will appear here once published in the catalog.</p>
        @endif
    </div>
</section>

{{-- 3. How it works --}}
<section class="py-20 lg:py-24 bg-white border-b border-slate-100" id="how-it-works">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <span class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">How {{ $brandName }} works</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display">Launch in three simple steps.</h2>
            <p class="text-slate-600 text-base sm:text-lg mt-2">The same flow for Watch Hours and every other campaign package.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
            @foreach([
                ['n' => '01', 'icon' => 'category', 'title' => 'Choose a service', 'body' => 'Pick YouTube Watch Hours or another package and select a predefined quantity with upfront fixed pricing.', 'foot' => 'Step 1 · No haggling'],
                ['n' => '02', 'icon' => 'link', 'title' => 'Provide campaign details', 'body' => 'Add the required target URL and any campaign instructions in the checkout form.', 'foot' => 'Step 2 · Quick setup'],
                ['n' => '03', 'icon' => 'rocket_launch', 'title' => 'Pay and track', 'body' => 'Pay securely, then follow verified task completion and campaign progress in your account.', 'foot' => 'Step 3 · Secure payment'],
            ] as $step)
                <div class="bg-slate-50 p-8 rounded-xl border border-slate-200/80 flex flex-col justify-between">
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
                    <div class="mt-6 pt-4 border-t border-slate-200/80 flex items-center text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        <span>{{ $step['foot'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- 4. Other platforms (non-YouTube filter catalog) --}}
<section
    class="py-20 lg:py-28 bg-[#F8FAFC] border-b border-slate-100"
    id="services"
    x-data="{
        filter: 'all',
        catalog: @js($marketplaceCatalog),
        tones: @js($badgeTones),
        icons: @js($badgeIcons),
        get products() {
            return (this.catalog.products && this.catalog.products[this.filter]) ? this.catalog.products[this.filter] : [];
        },
        tone(i) { return this.tones[i % this.tones.length]; },
        icon(i) { return this.icons[i % this.icons.length]; }
    }"
>
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8 sm:mb-10">
            <span class="text-slate-500 font-bold text-xs sm:text-sm tracking-wider uppercase mb-2 block">Other platforms</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-display">Need Facebook, Instagram, TikTok, or Twitter?</h2>
            <p class="text-slate-600 text-base sm:text-lg mt-2 max-w-xl">Additional platform packages live here. YouTube is covered in the dedicated section above.</p>
        </div>

        @if($filterCategories->isNotEmpty())
            <div class="mb-10 w-full min-w-0">
                <div class="overflow-x-auto scrollbar-hide overscroll-x-contain bg-slate-100 rounded-xl -mx-1 px-1">
                    <div class="flex items-center gap-2 p-1.5 w-max min-w-full sm:min-w-0">
                        <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 font-medium hover:text-slate-900'" class="px-4 py-2 rounded-lg text-sm transition-all shrink-0">All</button>
                        @foreach($filterCategories as $cat)
                            <button
                                type="button"
                                @click="filter = '{{ $cat['slug'] }}'"
                                :class="filter === '{{ $cat['slug'] }}' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 font-medium hover:text-slate-900'"
                                class="px-4 py-2 rounded-lg text-sm transition-all shrink-0"
                            >{{ $cat['label'] ?? $cat['slug'] }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <template x-if="products.length > 0">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                <template x-for="(product, index) in products" :key="filter + '-' + product.id">
                    <div class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between">
                        <div>
                            <div class="relative h-48 w-full overflow-hidden bg-slate-100">
                                <template x-if="product.hero_url">
                                    <img :src="product.hero_url" :alt="product.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                </template>
                                <template x-if="!product.hero_url">
                                    <div class="w-full h-full bg-gradient-to-br from-primary/25 via-slate-200 to-slate-100"></div>
                                </template>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                                <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/95 backdrop-blur-md text-xs font-bold shadow-sm" :class="tone(index)">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true" x-text="icon(index)"></span>
                                    <span x-text="product.category_label"></span>
                                </span>
                            </div>
                            <div class="p-5">
                                <h3 class="text-xl font-bold text-slate-900 mb-2 group-hover:text-primary transition-colors font-display">
                                    <a :href="product.href" x-text="product.title"></a>
                                </h3>
                                <p class="text-slate-600 text-sm leading-relaxed mb-4" x-text="product.short_description"></p>
                            </div>
                        </div>
                        <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
                            <span class="text-xs font-medium text-slate-500" x-text="product.from_price ? ('From ₦' + Number(product.from_price).toLocaleString('en-NG')) : 'From predefined packages'"></span>
                            <a class="inline-flex items-center gap-1 text-primary font-semibold text-sm hover:underline group-hover:translate-x-0.5 transition-transform" :href="product.href">View package →</a>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <div x-show="products.length === 0" x-cloak>
            @if($marketplaceCards->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                    @foreach($marketplaceCards as $card)
                        @php
                            $tone = $badgeTones[$loop->index % count($badgeTones)];
                            $icon = $badgeIcons[$loop->index % count($badgeIcons)];
                            $image = $card['card_image'] ?? $card['banner_image'] ?? null;
                            $href = $card['href'] ?? route('services');
                        @endphp
                        <div class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between">
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
                                    <h3 class="text-xl font-bold text-slate-900 mb-2 font-display">{{ $card['label'] ?? 'Campaigns' }}</h3>
                                    <p class="text-slate-600 text-sm leading-relaxed mb-4">{{ $card['short_description'] ?? $card['hero_subtitle'] ?? 'Predefined packages with upfront pricing and secure payment.' }}</p>
                                </div>
                            </div>
                            <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
                                <span class="text-xs font-medium text-slate-500">From predefined packages</span>
                                <a class="inline-flex items-center gap-1 text-primary font-semibold text-sm hover:underline" href="{{ $href }}">Explore Packages →</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-500 text-center py-12">Campaign packages will appear here once published in the catalog.</p>
            @endif
        </div>
    </div>
</section>

{{-- 5. Creators --}}
<section class="py-20 lg:py-28 bg-white overflow-hidden" id="creators">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
            <div class="lg:col-span-6 relative">
                <div class="relative rounded-2xl overflow-hidden shadow-xl border border-slate-100 min-h-[420px] sm:min-h-[520px] lg:min-h-[600px]">
                    <img
                        src="{{ asset('assets/images/campaign-workspace.jpg') }}"
                        alt="Creator launching campaigns on {{ $brandName }}"
                        class="absolute inset-0 w-full h-full object-cover object-center"
                        loading="lazy"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-transparent to-transparent"></div>
                    <div class="absolute bottom-6 left-6 right-6 text-white">
                        <p class="font-bold text-lg">Built for creators &amp; founders</p>
                        <p class="text-sm text-slate-200">YouTube Watch Hours · Campaign packages · {{ $brandName }}</p>
                    </div>
                </div>
                <div class="absolute -bottom-6 -right-6 -z-10 w-64 h-64 bg-red-100/50 rounded-full blur-3xl pointer-events-none"></div>
            </div>
            <div class="lg:col-span-6 flex flex-col items-start">
                <span class="text-primary font-bold text-xs sm:text-sm tracking-wider uppercase mb-2">For creators &amp; founders</span>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight leading-tight mb-5 font-display">
                    {!! $ytWord('Everything you need to get your YouTube campaign moving.') !!}
                </h2>
                <p class="text-slate-600 text-base sm:text-lg leading-relaxed mb-8">
                    Skip unpredictable freelancers and opaque bots. {{ $brandName }} gives you fixed upfront packages, secure payment, and verified task activity you can review in your account.
                </p>
                <div class="space-y-4 mb-9 w-full">
                    @foreach([
                        ['title' => 'Upfront predefined pricing — zero bidding wars', 'body' => 'Know exactly what you pay and get before committing any budget.'],
                        ['title' => 'Verified human activity with proof validation', 'body' => 'Completions go through submission checks before they count toward your campaign.'],
                        ['title' => 'Real-time progress monitoring in your account', 'body' => 'Track campaign progress, view completed actions, and download reports.'],
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
                    <a class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-base px-6 py-3.5 rounded-lg shadow-sm hover:shadow transition-all" href="{{ route('register') }}">
                        <span>Pay For Watch Hours</span>
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                    </a>
                    <a class="inline-flex items-center gap-1.5 text-slate-700 hover:text-primary font-semibold text-base transition-colors" href="{{ route('agents') }}">
                        <span>Watch &amp; Earn</span>
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
                <div class="{{ $agentTaskPreview ? 'lg:col-span-7' : 'lg:col-span-12' }}">
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
                        <a class="inline-flex items-center gap-1 text-sm sm:text-base font-semibold text-slate-600 hover:text-slate-900 transition-colors" href="{{ route('register.agent') }}">
                            <span>See open tasks</span>
                            <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
                        </a>
                    </div>
                </div>
                @if($agentTaskPreview)
                    <div class="lg:col-span-5 w-full">
                        <div class="bg-slate-50 rounded-xl p-5 border border-slate-200">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200/80 text-xs font-semibold text-slate-500">
                                <span class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    Live task available
                                </span>
                                <span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded border border-emerald-200 font-bold">{{ $agentTaskPreview['badge'] ?? 'Available' }}</span>
                            </div>
                            <div class="py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">{{ $agentTaskPreview['label'] ?? 'Campaign' }}</p>
                                <h4 class="font-bold text-slate-900 text-base mb-1">{{ $agentTaskPreview['title'] }}</h4>
                                <div class="flex items-center justify-between bg-white px-3 py-2 rounded-lg border border-slate-200/70 mb-3 mt-3">
                                    <span class="text-xs text-slate-600 font-medium">Verified reward</span>
                                    <span class="text-sm font-extrabold text-emerald-600">
                                        {{ $agentTaskPreview['reward'] }}
                                        @if(! empty($agentTaskPreview['time']) && $agentTaskPreview['time'] !== 'Flexible')
                                            <span class="font-semibold text-slate-400">· {{ $agentTaskPreview['time'] }}</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <a href="{{ $agentTaskPreview['href'] }}" class="w-full py-2 bg-slate-200/70 hover:bg-slate-900 hover:text-white text-slate-700 font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-1">
                                <span>Preview task requirements</span>
                                <span class="material-symbols-outlined text-sm" aria-hidden="true">open_in_new</span>
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- 7. Final CTA --}}
<section class="py-20 lg:py-24 text-white relative overflow-hidden">
    <div class="absolute inset-0 z-0">
        <img
            src="{{ asset('assets/images/home-cta.jpg') }}"
            alt=""
            class="w-full h-full object-cover object-center"
            loading="lazy"
        >
        <div class="absolute inset-0 bg-gradient-to-br from-red-950/90 via-red-900/85 to-slate-950/90" aria-hidden="true"></div>
    </div>
    <div class="absolute -top-24 -right-24 w-96 h-96 bg-red-500/25 rounded-full blur-3xl pointer-events-none z-[1]"></div>
    <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-red-700/20 rounded-full blur-3xl pointer-events-none z-[1]"></div>
    <div class="relative z-10 max-w-site mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="max-w-2xl mx-auto">
            <div class="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-white mx-auto mb-6 overflow-hidden">
                <img src="{{ asset('assets/images/Social_Media.jpg') }}" alt="" class="w-full h-full object-cover" loading="lazy">
            </div>
            <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight mb-4 text-white font-display">
                {!! $ytWord('Ready to grow YouTube Watch Hours?', 'white') !!}
            </h2>
            <p class="text-red-50/90 text-base sm:text-lg mb-8 max-w-xl mx-auto">
                Choose a package, add your video URL, and launch in minutes.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                <a class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-500 text-white font-semibold text-base px-8 py-4 rounded-lg shadow-lg hover:shadow-xl transition-all" href="{{ route('register') }}">
                    <span>Pay For Watch Hours</span>
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                </a>
                <a class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white font-medium text-base px-6 py-4 rounded-lg border border-white/25 transition-all" href="{{ route('agents') }}">
                    Watch &amp; Earn
                </a>
            </div>
        </div>
    </div>
</section>

{{-- 8. FAQ strip --}}
<section class="py-12 bg-white" id="faq">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-slate-500">
        Have questions? View our comprehensive
        <a class="text-primary font-semibold hover:underline" href="{{ route('help') }}">FAQ guide</a>
        or reach out to
        <a class="text-primary font-semibold hover:underline" href="{{ route('contact') }}">24/7 campaign support</a>.
    </div>
</section>
@endsection
