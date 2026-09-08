@extends('layouts.marketing')

@section('title', 'Services')

@section('content')
@php
    $heroImage = asset('assets/images/services_1.jpg');
    $groups = collect($groups ?? []);
    $products = $products ?? null;
    $productCount = $products?->total() ?? 0;
    $totalVisible = (int) ($totalVisible ?? $productCount);
    $activeCategory = $activeCategory ?? '';
    $sort = $sort ?? 'popular';
    $budget = $budget ?? '';
    $q = $q ?? '';
    $categoryIcons = [
        'youtube' => 'smart_display',
        'facebook' => 'public',
        'instagram' => 'photo_camera',
        'tiktok' => 'music_note',
        'twitter' => 'chat',
        'social-media' => 'share',
    ];
    $categoryDots = [
        'youtube' => 'bg-red-500',
        'facebook' => 'bg-blue-600',
        'instagram' => 'bg-pink-500',
        'tiktok' => 'bg-slate-900',
        'twitter' => 'bg-sky-500',
        'social-media' => 'bg-violet-500',
    ];
    $filterBase = array_filter([
        'q' => $q !== '' ? $q : null,
        'sort' => $sort !== 'popular' ? $sort : null,
        'budget' => $budget !== '' ? $budget : null,
    ]);
    $activeCategoryLabel = $activeCategory === ''
        ? 'All Services'
        : ($groups->firstWhere('slug', $activeCategory)['label'] ?? $activeCategory);
@endphp

{{-- Marketplace hero --}}
<section class="relative w-full overflow-hidden bg-slate-900 text-white py-14 md:py-20">
    <div class="absolute inset-0 bg-cover bg-center opacity-35" style="background-image: url('{{ $heroImage }}');" aria-hidden="true"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/90 to-slate-900/70" aria-hidden="true"></div>

    <div class="relative max-w-site mx-auto px-4 sm:px-6 lg:px-8 z-10">
        <div class="max-w-3xl flex flex-col items-start">
            <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white mb-3 tracking-tight leading-tight">
                Find the campaign service you need.
            </h1>
            <p class="text-base sm:text-lg text-slate-300 max-w-2xl mb-8 leading-relaxed">
                Browse predefined campaign packages, choose your quantity, and launch with transparent upfront pricing.
            </p>

            <form method="GET" action="{{ route('services') }}" class="w-full bg-white rounded-xl p-1.5 shadow-xl flex flex-col sm:flex-row items-stretch sm:items-center gap-1.5">
                @if($activeCategory !== '')
                    <input type="hidden" name="category" value="{{ $activeCategory }}">
                @endif
                @if($sort !== 'popular')
                    <input type="hidden" name="sort" value="{{ $sort }}">
                @endif
                @if($budget !== '')
                    <input type="hidden" name="budget" value="{{ $budget }}">
                @endif
                <div class="flex items-center gap-2 px-3 py-2 w-full min-w-0">
                    <span class="material-symbols-outlined text-slate-400 shrink-0" aria-hidden="true">search</span>
                    <label for="marketplace-search" class="sr-only">Search campaign services</label>
                    <input
                        id="marketplace-search"
                        type="search"
                        name="q"
                        value="{{ $q }}"
                        placeholder="Search campaign services (e.g. YouTube views, Instagram)…"
                        class="w-full min-w-0 bg-transparent text-slate-900 text-sm placeholder:text-slate-400 focus:outline-none border-0 focus:ring-0 p-0"
                    >
                </div>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-hover transition-colors shrink-0">
                    Search
                </button>
            </form>
        </div>
    </div>
</section>

{{-- Desktop category strip (unchanged position) --}}
<section class="hidden md:block w-full bg-white border-b border-slate-200 shadow-sm sticky top-24 z-30">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide py-0.5">
            <a
                href="{{ route('services', $filterBase) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-sm font-semibold shrink-0 transition-all {{ $activeCategory === '' ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">grid_view</span>
                All Services ({{ $totalVisible }})
            </a>
            @foreach($groups as $card)
                @php
                    $slug = $card['slug'] ?? '';
                    $label = $card['label'] ?? $slug;
                    $count = (int) ($card['count'] ?? 0);
                    $icon = $categoryIcons[$slug] ?? ($card['icon'] ?? 'category');
                    $isActive = $activeCategory === $slug;
                    $href = route('services', array_filter(array_merge($filterBase, ['category' => $slug])));
                @endphp
                <a
                    href="{{ $href }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-sm font-semibold shrink-0 transition-all {{ $isActive ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
                >
                    <span class="w-2.5 h-2.5 rounded-full {{ $categoryDots[$slug] ?? 'bg-primary' }} {{ $isActive ? 'ring-2 ring-white/40' : '' }}"></span>
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $icon }}</span>
                    {{ $label }}@if($count > 0) ({{ $count }})@endif
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Mobile category filter accordion (collapsed by default) --}}
<section class="md:hidden w-full bg-white border-b border-slate-200" x-data="{ open: false }">
    <div class="max-w-site mx-auto px-4 sm:px-6 py-3">
        <button
            type="button"
            class="w-full flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-controls="services-mobile-category-filter"
        >
            <span class="min-w-0">
                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Filter</span>
                <span class="block text-sm font-semibold text-slate-900 truncate">{{ $activeCategoryLabel }}@if($activeCategory === '') ({{ $totalVisible }})@endif</span>
            </span>
            <span class="material-symbols-outlined text-slate-500 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
        </button>

        <div
            id="services-mobile-category-filter"
            x-show="open"
            x-cloak
            class="mt-2 rounded-xl border border-slate-200 bg-white overflow-hidden"
        >
            <div class="flex flex-col p-1.5">
                <a
                    href="{{ route('services', $filterBase) }}"
                    class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium {{ $activeCategory === '' ? 'bg-primary text-white' : 'text-slate-700 hover:bg-slate-50' }}"
                >
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">grid_view</span>
                    All Services ({{ $totalVisible }})
                </a>
                @foreach($groups as $card)
                    @php
                        $slug = $card['slug'] ?? '';
                        $label = $card['label'] ?? $slug;
                        $count = (int) ($card['count'] ?? 0);
                        $icon = $categoryIcons[$slug] ?? ($card['icon'] ?? 'category');
                        $isActive = $activeCategory === $slug;
                        $href = route('services', array_filter(array_merge($filterBase, ['category' => $slug])));
                    @endphp
                    <a
                        href="{{ $href }}"
                        class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium {{ $isActive ? 'bg-primary text-white' : 'text-slate-700 hover:bg-slate-50' }}"
                    >
                        <span class="w-2.5 h-2.5 rounded-full {{ $categoryDots[$slug] ?? 'bg-primary' }}"></span>
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $icon }}</span>
                        {{ $label }}@if($count > 0) ({{ $count }})@endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- Main browsing area: flex avoids missing lg:col-span-* purge issues --}}
<section class="w-full">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
        <div class="flex flex-col lg:flex-row gap-8 items-start">
            {{-- Filters sidebar --}}
            <aside class="w-full lg:w-72 xl:w-80 shrink-0 flex flex-col gap-6">
                <form method="GET" action="{{ route('services') }}" class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm flex flex-col gap-6">
                    @if($q !== '')
                        <input type="hidden" name="q" value="{{ $q }}">
                    @endif

                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <span class="font-display text-lg font-bold text-slate-900">Filters</span>
                        <a href="{{ route('services') }}" class="text-sm text-primary hover:underline">Reset</a>
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
                                        @checked($activeCategory === '')
                                        onchange="this.form.submit()"
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
                                            @checked($activeCategory === $slug)
                                            onchange="this.form.submit()"
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
                                <label class="block">
                                    <input type="radio" name="budget" value="{{ $value }}" class="sr-only peer" @checked($budget === $value) onchange="this.form.submit()">
                                    <span class="block w-full text-left px-3 py-2 text-sm rounded-lg cursor-pointer transition-colors peer-checked:bg-primary peer-checked:text-white bg-slate-50 hover:bg-slate-100 text-slate-700">
                                        {{ $label }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Sort by</h4>
                        <select
                            name="sort"
                            class="w-full bg-slate-50 text-slate-900 text-sm rounded-lg px-3 py-2.5 border-0 focus:ring-2 focus:ring-primary/30"
                            onchange="this.form.submit()"
                        >
                            <option value="popular" @selected($sort === 'popular')>Most popular</option>
                            <option value="price_asc" @selected($sort === 'price_asc')>Lowest starting price</option>
                            <option value="price_desc" @selected($sort === 'price_desc')>Highest starting price</option>
                        </select>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-lg flex items-start gap-3">
                        <span class="material-symbols-outlined text-emerald-600 shrink-0" aria-hidden="true">verified_user</span>
                        <div>
                            <span class="block text-sm font-semibold text-slate-900">Verified campaign packages</span>
                            <span class="block text-xs text-slate-500 mt-1 leading-relaxed">Clear deliverables and upfront pricing before you checkout.</span>
                        </div>
                    </div>
                </form>
            </aside>

            {{-- Listings --}}
            <div class="w-full min-w-0 flex-1 flex flex-col gap-5">
                <div class="bg-white rounded-xl p-4 sm:p-5 border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-display text-xl sm:text-2xl font-bold text-slate-900">Available campaign services</h2>
                        <p class="text-sm text-slate-500 mt-0.5">
                            @if($q !== '')
                                Showing {{ $productCount }} {{ \Illuminate\Support\Str::plural('result', $productCount) }} for “{{ $q }}”
                            @elseif($activeCategory !== '')
                                Showing {{ $productCount }} {{ \Illuminate\Support\Str::plural('package', $productCount) }} in {{ $activeCategoryLabel }}
                            @else
                                Showing {{ $productCount }} active predefined {{ \Illuminate\Support\Str::plural('campaign', $productCount) }}
                            @endif
                        </p>
                    </div>
                    <form method="GET" action="{{ route('services') }}" class="flex items-center gap-2 shrink-0">
                        @if($q !== '')
                            <input type="hidden" name="q" value="{{ $q }}">
                        @endif
                        @if($activeCategory !== '')
                            <input type="hidden" name="category" value="{{ $activeCategory }}">
                        @endif
                        @if($budget !== '')
                            <input type="hidden" name="budget" value="{{ $budget }}">
                        @endif
                        <label for="services-sort" class="text-xs font-medium text-slate-400 shrink-0">Sort by:</label>
                        <select
                            id="services-sort"
                            name="sort"
                            class="bg-slate-50 text-slate-900 text-sm rounded-lg px-3 py-2 border-0 focus:ring-2 focus:ring-primary/30"
                            onchange="this.form.submit()"
                        >
                            <option value="popular" @selected($sort === 'popular')>Most popular</option>
                            <option value="price_asc" @selected($sort === 'price_asc')>Lowest starting price</option>
                            <option value="price_desc" @selected($sort === 'price_desc')>Highest starting price</option>
                        </select>
                    </form>
                </div>

                @if(! $products || $products->isEmpty())
                    <div class="bg-white rounded-xl border border-slate-200 p-10 text-center">
                        <p class="text-slate-600 mb-4">No campaign packages match your filters.</p>
                        <a href="{{ route('services') }}" class="inline-flex text-sm font-semibold text-primary hover:underline">Clear filters</a>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
                        @foreach($products as $product)
                            @include('partials.catalog.marketplace-product-card', ['product' => $product])
                        @endforeach
                    </div>
                    <div class="mt-2">{{ $products->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</section>

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
                    No price negotiation. Transparent checkout.
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-6">
                @foreach([
                    ['Pick a package', 'Select quantity and clear fixed upfront pricing from the product page.'],
                    ['Provide your details', 'Enter your destination link or campaign details at checkout.'],
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
                <a href="{{ route('how-it-works') }}" class="inline-flex text-sm font-semibold text-primary hover:underline">Learn more →</a>
            </div>
        </div>
    </div>
</section>
@endsection
