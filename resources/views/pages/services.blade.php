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

<div
    class="w-full"
    id="services-catalog"
    x-data="servicesMarketplace(@js($marketplaceConfig))"
    @click.capture="onResultsClick($event)"
>
<section class="w-full bg-slate-50 border-b border-slate-100">
    <h1 class="sr-only">Services</h1>
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
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

            <div
                x-ref="results"
                id="services-results"
                class="w-full min-w-0 flex-1 flex flex-col gap-8 transition-opacity"
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
</section>
</div>

{{-- How packages work --}}
<section class="w-full bg-slate-50 py-12 sm:py-16 border-t border-slate-100">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl p-6 sm:p-8 border border-slate-200 shadow-sm">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 pb-6 border-b border-slate-100">
                <div>
                    <span class="text-primary text-[11px] font-bold uppercase tracking-wider">How it works</span>
                    <h3 class="font-display text-xl sm:text-2xl font-bold text-slate-900 mt-1">How ordering works</h3>
                </div>
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-sm">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">verified</span>
                    No price negotiation. The price is shown before you pay.
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-6">
                @foreach([
                    ['Pick a package', 'Choose the package you want on the service page. The price is fixed.'],
                    ['Provide your details', 'Add the link to your post or video, and any campaign details, at checkout.'],
                    ['Secure payment', 'Pay with wallet, card, or bank transfer, depending on what\'s available.'],
                    ['Track progress', 'Follow your order and campaign progress from your dashboard.'],
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
