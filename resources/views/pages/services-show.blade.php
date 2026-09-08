@extends('layouts.marketing')

@section('title', $product->title)

@section('content')
@php
    $crumbs = [
        ['label' => 'Home', 'href' => route('home')],
        ['label' => 'Services', 'href' => route('services')],
    ];
    if (! empty($groupSlug) && ! empty($groupContent)) {
        $crumbs[] = ['label' => $groupContent['label'], 'href' => route('services.segment', $groupSlug)];
    }
    // Category → Product breadcrumbs (ProductType mid-layer is not required in the trail).
    $crumbs[] = ['label' => $product->title];

    $heroUrl = $product->heroMedia?->url('medium') ?? ($product->hero_image ? asset($product->hero_image) : null);

    $gallery = collect();
    if ($heroUrl) {
        $gallery->push(['src' => $heroUrl, 'alt' => $product->title]);
    }
    foreach ($product->images ?? [] as $img) {
        $gallery->push([
            'src' => asset(ltrim($img->path, '/')),
            'alt' => $img->alt ?: $product->title,
        ]);
    }
    if ($gallery->isEmpty()) {
        $gallery->push([
            'src' => asset('assets/images/Image_ro410gro410gro41.png'),
            'alt' => $product->title,
        ]);
    }
    $gallery = $gallery->unique('src')->values();

    $subtitle = $product->short_description ?: null;
    $startPrice = $product->displayPrice();
    $checkoutUrl = route('dashboard.services.checkout', $product->slug);

    $variants = $product->activeVariants;
    $monthlyVariant = $variants->first(fn ($v) => (int) $v->duration_months === 1);
    $defaultVariant = $variants->firstWhere('is_default', true)
        ?? $variants->sortBy('price')->first();
    $showPerMonthSuffix = $defaultVariant && (int) $defaultVariant->duration_months === 1;
    $longestMonths = (int) ($variants->max('duration_months') ?: 0);
    $popularVariantId = optional(
        $variants->first(fn ($v) => (int) $v->duration_months === 3)
            ?? $variants->firstWhere('is_default', true)
    )->id;

    $featureIcons = ['rocket', 'wallet', 'support', 'lock', 'verified', 'grid'];
    $includeIcons = ['check', 'listings', 'support'];

    $features = collect($product->features ?? [])->map(function ($item) {
        if (is_array($item)) {
            return [
                'title' => $item['title'] ?? $item['label'] ?? '',
                'blurb' => $item['blurb'] ?? $item['description'] ?? $item['a'] ?? null,
            ];
        }

        return ['title' => (string) $item, 'blurb' => null];
    })->filter(fn ($f) => $f['title'] !== '');

    $requirements = collect($product->requirements ?? [])->map(fn ($item) => is_array($item)
        ? (string) ($item['title'] ?? $item['label'] ?? $item['text'] ?? '')
        : (string) $item
    )->filter();

    $included = collect($product->whats_included ?? [])->map(fn ($item) => is_array($item)
        ? (string) ($item['title'] ?? $item['label'] ?? $item['text'] ?? '')
        : (string) $item
    )->filter();

    $faqs = collect($product->faqs ?? [])->filter(fn ($f) => is_array($f) && ! empty($f['q']));

    $supportHref = auth()->check()
        ? route('dashboard.support.index')
        : route('login');

    $heroBg = $product->hero_image ?: null;
@endphp

{{-- Compact hero: breadcrumbs only, same height/alignment as /services/* pages --}}
<header class="relative isolate overflow-hidden border-b border-white/10 pt-32 sm:pt-36 pb-10 sm:pb-12">
    {{-- Decorative gradient base first, photo above it — .marketing-page-hero-bg is opaque and must not cover the image --}}
    <div class="pointer-events-none absolute inset-0 z-0 marketing-page-hero-bg" aria-hidden="true"></div>
    <div
        class="pointer-events-none absolute inset-0 z-0 bg-cover bg-center bg-no-repeat"
        @if($heroBg)
            style="background-image: url('{{ asset($heroBg) }}')"
        @else
            style="background-image: url('{{ asset('assets/images/Image_ro410gro410gro41.png') }}')"
        @endif
        aria-hidden="true"
    ></div>
    <div
        class="pointer-events-none absolute inset-0 z-[1]"
        style="background: linear-gradient(180deg, rgba(15, 23, 42, 0.78) 0%, rgba(15, 23, 42, 0.72) 45%, rgba(15, 23, 42, 0.88) 100%);"
        aria-hidden="true"
    ></div>
    <div class="pointer-events-none absolute top-0 right-0 z-[1] w-[420px] h-[420px] bg-primary/20 blur-[120px] rounded-full" aria-hidden="true"></div>
    <div class="pointer-events-none absolute bottom-0 left-0 z-[1] w-[320px] h-[320px] bg-accent/10 blur-[100px] rounded-full" aria-hidden="true"></div>

    <div class="relative z-10 max-w-marketing mx-auto px-5 sm:px-6">
        <nav class="text-sm text-slate-300" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-1.5">
                @foreach($crumbs as $i => $crumb)
                    <li class="inline-flex items-center gap-1.5">
                        @if($i > 0)
                            <span class="text-slate-500" aria-hidden="true">/</span>
                        @endif
                        @if(! empty($crumb['href']) && ! $loop->last)
                            <a href="{{ $crumb['href'] }}" class="hover:text-white transition-colors">{{ $crumb['label'] }}</a>
                        @else
                            <span class="{{ $loop->last ? 'text-white/90' : '' }}">{{ $crumb['label'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    </div>
</header>

{{-- Ecommerce buy box (white) --}}
<section class="bg-white text-slate-900">
    <div class="max-w-marketing mx-auto px-5 sm:px-6 py-10 sm:py-14">
        <div
            class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-start"
            x-data="{ active: 0, images: {{ Js::from($gallery) }} }"
        >
            {{-- Left: gallery --}}
            <div class="space-y-3">
                <div class="aspect-[4/3] sm:aspect-square rounded-xl overflow-hidden bg-slate-100 border border-slate-200">
                    <img
                        :src="images[active].src"
                        :alt="images[active].alt"
                        class="w-full h-full object-cover"
                    >
                </div>
                @if($gallery->count() > 1)
                    <div class="grid grid-cols-4 sm:grid-cols-5 gap-2">
                        @foreach($gallery as $i => $shot)
                            <button
                                type="button"
                                @click="active = {{ $i }}"
                                :class="active === {{ $i }} ? 'ring-2 ring-primary border-primary' : 'border-slate-200 hover:border-slate-300'"
                                class="aspect-square rounded-lg overflow-hidden border bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                            >
                                <img src="{{ $shot['src'] }}" alt="" class="w-full h-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Right: title, description, price, CTA --}}
            <div class="flex flex-col gap-4 sm:gap-5 lg:pt-1">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary mb-2">
                        {{ $product->product_type->label() }}
                        @if($product->serviceCategory)
                            <span class="text-slate-400 font-normal">· {{ $product->serviceCategory->name }}</span>
                        @elseif($product->productType)
                            <span class="text-slate-400 font-normal">· {{ $product->productType->name }}</span>
                        @endif
                    </p>
                    <h1 class="font-display text-xl sm:text-3xl lg:text-4xl font-bold text-slate-900 tracking-tight leading-tight">
                        {{ $product->title }}
                    </h1>
                </div>

                @if($subtitle)
                    <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                        {{ $subtitle }}
                    </p>
                @endif

                <div class="border-t border-b border-slate-200 py-4">
                    <span class="text-[11px] font-medium uppercase tracking-widest text-slate-500 block">
                        {{ $variants->count() > 1 ? 'Pricing starts at' : 'Price' }}
                    </span>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-3xl font-display font-bold text-primary">
                            ₦{{ number_format($startPrice, 2) }}
                        </span>
                        @if($showPerMonthSuffix)
                            <span class="text-sm text-slate-500">/ mo</span>
                        @endif
                    </div>
                </div>

                @if($variants->count() > 1)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Choose a plan</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($variants as $variant)
                                @php
                                    $months = (int) ($variant->duration_months ?? 0);
                                    $isPopular = $variant->id === $popularVariantId;
                                    $isBestValue = $months > 0 && $months === $longestMonths;
                                @endphp
                                <div @class([
                                    'rounded-lg border px-3 py-2.5 text-left',
                                    'border-primary bg-primary/5' => $isPopular || $isBestValue,
                                    'border-slate-200 bg-slate-50' => ! ($isPopular || $isBestValue),
                                ])>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-semibold text-slate-900">{{ $variant->displayLabel() }}</span>
                                        @if($isBestValue)
                                            <span class="text-[9px] font-bold uppercase text-primary">Best</span>
                                        @elseif($isPopular)
                                            <span class="text-[9px] font-bold uppercase text-primary">Popular</span>
                                        @endif
                                    </div>
                                    <div class="text-sm font-bold text-primary mt-0.5">₦{{ number_format($variant->price, 2) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex flex-col sm:flex-row gap-3 pt-1">
                    <x-ui.button :href="$checkoutUrl" variant="primary" size="lg" class="!px-8 hover:!bg-primary-hover">
                        {{ auth()->check() ? 'Buy Now' : 'Log in to buy' }}
                    </x-ui.button>
                    @include('partials.catalog.view-demo-modal', ['product' => $product])
                    @auth
                        <form method="POST" action="{{ route('favorites.toggle') }}">
                            @csrf
                            <input type="hidden" name="type" value="platform_product">
                            <input type="hidden" name="id" value="{{ $product->id }}">
                            <x-ui.button type="submit" variant="secondary" size="lg" class="!bg-slate-100 !text-slate-800 !border-slate-200 hover:!bg-slate-200">
                                {{ ($isFavorited ?? false) ? 'Favorited' : 'Favorite' }}
                            </x-ui.button>
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Extra product information: only sections with admin data, each on its own grey card --}}
@php
    $showAbout = filled($product->description) && (! $subtitle || trim((string) $product->description) !== trim((string) $subtitle));
    $showTiers = $variants->count() > 1;
    $showRequirements = $requirements->isNotEmpty();
    $showFeatures = $features->isNotEmpty();
    $showIncluded = $included->isNotEmpty();
    $showFaqs = $faqs->isNotEmpty();
    $showSupport = filled($product->support_text);
    $hasDetailSections = $showAbout || $showTiers || $showRequirements || $showFeatures || $showIncluded || $showFaqs || $showSupport;
    $cardClass = 'rounded-xl border border-slate-200 bg-slate-100 p-5 sm:p-6';
@endphp

@if($hasDetailSections)
<section class="bg-white text-slate-900 border-t border-slate-200">
    <div class="max-w-marketing mx-auto px-5 sm:px-6 py-10 sm:py-14 space-y-5 sm:space-y-6">
        @if($showAbout)
            <div class="{{ $cardClass }}">
                <h2 class="font-display text-lg sm:text-xl font-semibold text-slate-900 mb-3">About this service</h2>
                <p class="text-sm sm:text-base text-slate-600 leading-relaxed whitespace-pre-line">{{ $product->description }}</p>
            </div>
        @endif

        @if($showTiers)
            <div class="{{ $cardClass }}">
                <h2 class="font-display text-lg sm:text-xl font-semibold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="text-primary"><x-ui.icon name="wallet" class="w-5 h-5" /></span>
                    Pricing tiers
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($variants as $variant)
                        @php
                            $months = (int) ($variant->duration_months ?? 0);
                            $isPopular = $variant->id === $popularVariantId;
                            $isBestValue = $months > 0 && $months === $longestMonths;
                            $perMonth = $months > 0 ? ((float) $variant->price / $months) : null;
                            $savePct = null;
                            if ($monthlyVariant && $months > 1) {
                                $expected = (float) $monthlyVariant->price * $months;
                                if ($expected > 0 && (float) $variant->price < $expected) {
                                    $savePct = (int) round((1 - ((float) $variant->price / $expected)) * 100);
                                }
                            }
                            $tierBadge = $isBestValue ? 'Best Value' : ($isPopular && $savePct ? 'Save '.$savePct.'%' : ($isPopular ? 'Popular' : null));
                        @endphp
                        <div @class([
                            'rounded-xl border p-4 sm:p-5 bg-white',
                            'border-primary/40 ring-1 ring-primary/15' => $isPopular || $isBestValue,
                            'border-slate-200' => ! ($isPopular || $isBestValue),
                        ])>
                            <div class="flex justify-between items-start gap-2">
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wider text-primary mb-1">{{ $variant->name ?: 'Plan' }}</div>
                                    <div class="font-semibold text-slate-900">{{ $variant->displayLabel() }}</div>
                                </div>
                                @if($tierBadge)
                                    <span class="bg-primary/15 text-primary px-2 py-0.5 rounded text-[10px] font-bold uppercase whitespace-nowrap">{{ $tierBadge }}</span>
                                @endif
                            </div>
                            <div class="mt-4 text-lg font-bold text-slate-900">₦{{ number_format($variant->price, 2) }}</div>
                            @if($perMonth !== null)
                                <div class="text-xs text-slate-500">₦{{ number_format($perMonth, 2) }} / mo</div>
                            @elseif($months === 1)
                                <div class="text-xs text-slate-500">Billed monthly</div>
                            @else
                                <div class="text-xs text-slate-500">One-time</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($showRequirements)
            <div class="{{ $cardClass }}">
                <h2 class="font-display text-lg sm:text-xl font-semibold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="text-warning"><x-ui.icon name="warning" class="w-5 h-5" /></span>
                    Requirements
                </h2>
                <ul class="space-y-3">
                    @foreach($requirements as $req)
                        <li class="flex gap-2.5 items-start text-sm text-slate-700">
                            <span class="text-primary mt-0.5 shrink-0"><x-ui.icon name="check" class="w-4 h-4" /></span>
                            {{ $req }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($showFeatures)
            <div class="{{ $cardClass }}">
                <h2 class="font-display text-lg sm:text-xl font-semibold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="text-primary"><x-ui.icon name="rocket" class="w-5 h-5" /></span>
                    Features
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($features as $i => $feature)
                        <div class="rounded-lg border border-slate-200 bg-white p-4 flex items-start gap-3">
                            <span class="text-primary p-2 bg-primary/10 rounded-lg shrink-0">
                                <x-ui.icon :name="$featureIcons[$i % count($featureIcons)]" class="w-4 h-4" />
                            </span>
                            <div>
                                <div class="font-semibold text-sm text-slate-900">{{ $feature['title'] }}</div>
                                @if(! empty($feature['blurb']))
                                    <div class="text-xs text-slate-500 mt-0.5">{{ $feature['blurb'] }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($showIncluded)
            <div class="{{ $cardClass }}">
                <h2 class="font-display text-lg sm:text-xl font-semibold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="text-primary"><x-ui.icon name="inventory" class="w-5 h-5" /></span>
                    What's included
                </h2>
                <div class="space-y-2">
                    @foreach($included as $i => $item)
                        <div class="flex items-center gap-3 p-3 border-l-2 border-primary bg-white rounded-r-lg">
                            <span class="text-primary shrink-0">
                                <x-ui.icon :name="$includeIcons[$i % count($includeIcons)]" class="w-4 h-4" />
                            </span>
                            <span class="font-semibold text-sm text-slate-900">{{ $item }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($showFaqs)
            <div class="{{ $cardClass }}">
                <h2 class="font-display text-lg sm:text-xl font-semibold text-slate-900 mb-2">Frequently asked questions</h2>
                <p class="text-sm text-slate-500 mb-5">About {{ $product->title }}</p>
                <div class="space-y-3">
                    @foreach($faqs as $faq)
                        <details class="group rounded-xl border border-slate-200 bg-white overflow-hidden [&_summary::-webkit-details-marker]:hidden" @if(! empty($faq['open'])) open @endif>
                            <summary class="flex justify-between items-center gap-4 p-4 sm:p-5 cursor-pointer hover:bg-slate-50 transition-colors">
                                <h3 class="font-semibold text-sm sm:text-base text-slate-900 text-left">{{ $faq['q'] }}</h3>
                                <span class="text-slate-400 transition-transform group-open:rotate-180 shrink-0">
                                    <x-ui.icon name="chevron-down" class="w-5 h-5" />
                                </span>
                            </summary>
                            <div class="px-4 sm:px-5 pb-4 sm:pb-5 text-sm text-slate-600 leading-relaxed">
                                {{ $faq['a'] ?? '' }}
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        @endif

        @if($showSupport)
            <div class="{{ $cardClass }} text-center">
                <p class="text-sm text-slate-700 mb-3">{{ $product->support_text }}</p>
                <a href="{{ $supportHref }}" class="inline-flex items-center gap-2 text-primary font-bold hover:text-accent hover:underline text-sm">
                    <x-ui.icon name="support" class="w-5 h-5" />
                    Open a support ticket from your dashboard
                </a>
            </div>
        @endif
    </div>
</section>
@endif
@endsection
