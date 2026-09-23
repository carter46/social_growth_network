@extends('layouts.marketing')

@section('title', 'Services')

@section('content')
@php
    $groups = collect($groups ?? []);
    $products = $products ?? null;
    $totalVisible = (int) ($totalVisible ?? ($products?->total() ?? 0));
    $activeCategory = $activeCategory ?? '';
    $sort = $sort ?? 'popular';
    $budget = $budget ?? '';
    $q = $q ?? '';
    $youtubeCatalog = $youtubeCatalog ?? ['featured' => null, 'others' => []];
    $watchHours = $youtubeCatalog['featured'] ?? null;
    $youtubeOthers = collect($youtubeCatalog['others'] ?? []);
    $watchHoursHref = is_array($watchHours) && filled($watchHours['href'] ?? null)
        ? $watchHours['href']
        : route('register');
    $youtubeFeatureImage = (is_array($watchHours) && filled($watchHours['hero_url'] ?? null))
        ? $watchHours['hero_url']
        : asset('assets/images/Social_Media.jpg');
    $ytWord = static function (string $text): string {
        return preg_replace(
            '/\bYouTube\b/u',
            '<span class="text-red-600">YouTube</span>',
            e($text)
        ) ?? e($text);
    };
    $marketplaceConfig = [
        'endpoint' => route('services'),
        'category' => $activeCategory,
        'sort' => $sort,
        'budget' => $budget,
        'q' => $q,
        'totalVisible' => $totalVisible,
        'groups' => $groups->map(fn ($card) => [
            'slug' => $card['slug'] ?? '',
            'label' => $card['label'] ?? ($card['slug'] ?? ''),
            'count' => (int) ($card['count'] ?? 0),
        ])->values()->all(),
    ];
@endphp

{{-- Compact page intro (no dark hero) --}}
<section class="w-full bg-white border-b border-slate-100">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
        <div class="max-w-2xl">
            <h1 class="font-display text-2xl sm:text-3xl lg:text-4xl font-extrabold text-slate-900 tracking-tight">
                Campaign services
            </h1>
            <p class="text-base text-slate-600 mt-2 leading-relaxed">
                YouTube Watch Hours leads the catalog. Other social packages follow below with the same upfront pricing.
            </p>
        </div>
        <form
            method="GET"
            action="{{ route('services') }}"
            class="mt-6 w-full max-w-xl bg-slate-50 border border-slate-200 rounded-xl p-1.5 flex flex-col sm:flex-row items-stretch sm:items-center gap-1.5"
            x-data
            @submit.prevent="
                const form = $event.target;
                const params = new URLSearchParams(new FormData(form));
                window.location = form.action + (params.toString() ? ('?' + params.toString()) : '');
            "
        >
            <div class="flex items-center gap-2 px-3 py-2 w-full min-w-0">
                <span class="material-symbols-outlined text-slate-500 shrink-0" aria-hidden="true">search</span>
                <label for="marketplace-search" class="sr-only">Search campaign services</label>
                <input
                    id="marketplace-search"
                    type="search"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Search other social services…"
                    class="w-full min-w-0 bg-transparent text-slate-900 text-sm placeholder:text-slate-500 focus:outline-none border-0 focus:ring-0 p-0"
                >
            </div>
            <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-primary hover:bg-primary-hover text-white text-sm font-semibold rounded-lg transition-colors shrink-0">
                Search
            </button>
        </form>
    </div>
</section>

{{-- YouTube Watch Hours feature band --}}
<section class="w-full bg-slate-50 border-b border-slate-100" id="youtube-watch-hours">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        @if(is_array($watchHours))
            <div class="services-yt-featured rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                <div class="grid grid-cols-1 lg:grid-cols-12">
                    <div class="lg:col-span-6 p-8 sm:p-10 lg:p-12 flex flex-col justify-center order-2 lg:order-1">
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                            <span class="material-symbols-outlined text-base text-red-600" aria-hidden="true">smart_display</span>
                            Featured YouTube service
                        </span>
                        <h2 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-slate-900 tracking-tight font-display mb-4">
                            {!! $ytWord($watchHours['title'] ?? 'YouTube Watch Hours') !!}
                        </h2>
                        <p class="text-slate-600 text-base sm:text-lg leading-relaxed mb-6 max-w-lg">
                            {{ $watchHours['short_description'] }}
                        </p>
                        <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 sm:gap-4">
                            @if(! empty($watchHours['from_price']))
                                <span class="text-sm font-semibold text-slate-700">
                                    From ₦{{ number_format((float) $watchHours['from_price'], 0) }}
                                </span>
                            @endif
                            <a
                                class="inline-flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm sm:text-base px-5 py-3 rounded-lg shadow-sm transition-all w-full sm:w-auto"
                                href="{{ $watchHoursHref }}"
                            >
                                <span>Get Watch Hours</span>
                                <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                    <div class="lg:col-span-6 relative min-h-[220px] sm:min-h-[300px] lg:min-h-full order-1 lg:order-2 bg-slate-100">
                        <img
                            src="{{ $youtubeFeatureImage }}"
                            alt="{{ $watchHours['title'] ?? 'YouTube Watch Hours' }}"
                            class="absolute inset-0 w-full h-full object-cover"
                            loading="lazy"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-red-950/30 via-transparent to-transparent" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        @endif

        @if($youtubeOthers->isNotEmpty())
            <div class="mt-10 mb-4">
                <h3 class="text-lg sm:text-xl font-bold text-slate-900 font-display">Other YouTube services</h3>
                <p class="text-slate-500 text-sm mt-1">Views, Likes, and Comments — supporting packages beside Watch Hours.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 lg:gap-6">
                @foreach($youtubeOthers as $product)
                    <div class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col">
                        <div class="relative h-40 w-full overflow-hidden bg-slate-100">
                            @if(! empty($product['hero_url']))
                                <img src="{{ $product['hero_url'] }}" alt="{{ $product['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                            @else
                                <img src="{{ asset('assets/images/Social_Media.jpg') }}" alt="{{ $product['title'] }}" class="w-full h-full object-cover" loading="lazy">
                            @endif
                        </div>
                        <div class="p-4 flex flex-col flex-1">
                            <h4 class="text-base font-bold text-slate-900 font-display">
                                <a href="{{ $product['href'] }}">{{ $product['title'] }}</a>
                            </h4>
                            <p class="text-slate-600 text-sm mt-1 flex-1">{{ $product['short_description'] }}</p>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-xs text-slate-500">
                                    @if(! empty($product['from_price']))
                                        From ₦{{ number_format((float) $product['from_price'], 0) }}
                                    @else
                                        From packages
                                    @endif
                                </span>
                                <a class="text-sm font-semibold text-primary hover:underline" href="{{ $product['href'] }}">View →</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- Other social media services --}}
<div
    class="w-full"
    id="other-social-services"
    x-data="servicesMarketplace(@js($marketplaceConfig))"
    @click.capture="onResultsClick($event)"
>
<section class="w-full">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
        <div class="mb-6 max-w-2xl">
            <h2 class="font-display text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                Other social media services
            </h2>
            <p class="text-sm sm:text-base text-slate-600 mt-1">
                Facebook, Instagram, TikTok, and Twitter packages. YouTube is covered in the section above.
            </p>
        </div>

        <div class="flex flex-col lg:flex-row gap-8 items-start">
            <aside class="w-full lg:w-72 xl:w-80 shrink-0 lg:sticky lg:top-20 self-start">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <button
                        type="button"
                        class="lg:hidden w-full flex items-center justify-between gap-3 px-4 py-3 text-left"
                        @click="filtersOpen = !filtersOpen"
                        :aria-expanded="filtersOpen.toString()"
                        aria-controls="services-filters-panel"
                    >
                        <span class="min-w-0">
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Filter</span>
                            <span class="block text-sm font-semibold text-slate-900 truncate" x-text="category === '' ? ('All Services (' + totalVisible + ')') : categoryLabel"></span>
                        </span>
                        <span class="material-symbols-outlined text-slate-500 shrink-0 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                    </button>

                    <div class="hidden lg:flex items-center justify-between px-5 pt-5 pb-3 border-b border-slate-100">
                        <span class="font-display text-lg font-bold text-slate-900">Filters</span>
                        <button type="button" @click="reset()" class="text-sm text-primary hover:underline">Reset</button>
                    </div>

                    <div
                        id="services-filters-panel"
                        class="flex-col gap-6 px-5 pb-5 pt-3 lg:pt-5"
                        :class="filtersOpen ? 'flex' : 'hidden lg:flex'"
                    >
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 lg:hidden">
                            <span class="font-display text-base font-bold text-slate-900">Filters</span>
                            <button type="button" @click="reset()" class="text-sm text-primary hover:underline">Reset</button>
                        </div>

                        <div>
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Categories</h4>
                            <div class="flex flex-col gap-0.5">
                                <label class="flex items-center justify-between p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                                    <span class="flex items-center gap-2 text-sm text-slate-800">
                                        <input
                                            type="radio"
                                            name="category"
                                            value=""
                                            class="accent-primary"
                                            :checked="category === ''"
                                            @change="setCategory('')"
                                        >
                                        All categories
                                    </span>
                                    <span class="text-xs text-slate-400">{{ $totalVisible }}</span>
                                </label>
                                @foreach($groups as $card)
                                    @php
                                        $slug = $card['slug'] ?? '';
                                        $count = (int) ($card['count'] ?? 0);
                                    @endphp
                                    <label class="flex items-center justify-between p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                                        <span class="flex items-center gap-2 text-sm text-slate-800">
                                            <input
                                                type="radio"
                                                name="category"
                                                value="{{ $slug }}"
                                                class="accent-primary"
                                                :checked="category === @js($slug)"
                                                @change="setCategory(@js($slug))"
                                            >
                                            {{ $card['label'] ?? $slug }}
                                        </span>
                                        <span class="text-xs text-slate-400">{{ $count }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Starting budget</h4>
                            <div class="flex flex-col gap-1.5">
                                @foreach([
                                    '' => 'Any budget',
                                    'under_10k' => 'Under ₦10,000',
                                    '10k_25k' => '₦10,000 – ₦25,000',
                                    '25k_plus' => '₦25,000+',
                                ] as $value => $label)
                                    <button
                                        type="button"
                                        @click="setBudget(@js($value))"
                                        :class="budget === @js($value) ? 'bg-primary text-white' : 'bg-slate-50 hover:bg-slate-100 text-slate-700'"
                                        class="block w-full text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Sort by</h4>
                            <select
                                class="w-full bg-slate-50 text-slate-900 text-sm rounded-lg px-3 py-2.5 border-0 focus:ring-2 focus:ring-primary/30"
                                :value="sort"
                                @change="setSort($event.target.value)"
                            >
                                <option value="popular">Most popular</option>
                                <option value="price_asc">Lowest starting price</option>
                                <option value="price_desc">Highest starting price</option>
                            </select>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="w-full min-w-0 flex-1 flex flex-col gap-5">
                <div class="flex items-center justify-end gap-2">
                    <label for="services-sort" class="text-xs font-medium text-slate-400 shrink-0">Sort by:</label>
                    <select
                        id="services-sort"
                        class="bg-white text-slate-900 text-sm rounded-lg px-3 py-2 border border-slate-200 shadow-sm focus:ring-2 focus:ring-primary/30"
                        :value="sort"
                        @change="setSort($event.target.value)"
                    >
                        <option value="popular">Most popular</option>
                        <option value="price_asc">Lowest starting price</option>
                        <option value="price_desc">Highest starting price</option>
                    </select>
                </div>

                <div
                    x-ref="results"
                    id="services-results"
                    class="flex flex-col gap-5 transition-opacity"
                    :class="loading ? 'opacity-60 pointer-events-none' : ''"
                >
                    @include('partials.catalog.services-results', [
                        'products' => $products,
                        'groups' => $groups,
                        'q' => $q,
                        'activeCategory' => $activeCategory,
                    ])
                </div>
            </div>
        </div>
    </div>
</section>
</div>

{{-- How packages work --}}
<section class="w-full bg-slate-50 py-12 sm:py-16 border-t border-slate-100">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl p-6 sm:p-8 border border-slate-200 shadow-sm">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 pb-6 border-b border-slate-100">
                <div>
                    <span class="text-primary text-[11px] font-bold uppercase tracking-wider">Predictable execution</span>
                    <h3 class="font-display text-xl sm:text-2xl font-bold text-slate-900 mt-1">How predefined packages work</h3>
                </div>
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-sm">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">verified</span>
                    No price negotiation. Transparent pricing.
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-6">
                @foreach([
                    ['Pick a package', 'Select quantity and clear fixed upfront pricing from the product page.'],
                    ['Provide your details', 'Enter your destination link or campaign details when you pay.'],
                    ['Secure payment', 'Pay with wallet, card, or bank transfer when those options are enabled.'],
                    ['Track progress', 'Follow your order and tools from your dashboard after purchase.'],
                ] as $i => $step)
                    <div class="flex flex-col gap-2">
                        <div class="w-8 h-8 rounded-full bg-blue-50 text-primary font-display text-sm font-bold flex items-center justify-center">{{ $i + 1 }}</div>
                        <span class="text-sm font-semibold text-slate-900">{{ $step[0] }}</span>
                        <p class="text-sm text-slate-500 leading-relaxed">{{ $step[1] }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('help') }}" class="inline-flex text-sm font-semibold text-primary hover:underline">Learn more →</a>
            </div>
        </div>
    </div>
</section>
@endsection
