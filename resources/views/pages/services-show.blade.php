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
    $crumbs[] = ['label' => $product->title];

    $heroUrl = $product->heroMedia?->url('medium')
        ?? ($product->hero_image ? asset($product->hero_image) : null);
    if (! $heroUrl) {
        $fallbackImage = ($product->images ?? collect())->first();
        if ($fallbackImage) {
            $heroUrl = asset(ltrim($fallbackImage->path, '/'));
        }
    }

    $subtitle = null;
    $metric = \App\Enums\EngagementMetric::fromProductSlug($product->slug);
    $pricingVariant = $product->pricingVariant();
    $pricing = $product->isPurchasable() ? $pricingVariant->storefrontPayload($metric) : null;

    $showAbout = filled($product->description);

    $heroBg = $product->hero_image ?: null;
@endphp

{{-- Compact hero: breadcrumbs only --}}
<header class="relative isolate overflow-hidden border-b border-white/10 pt-32 sm:pt-36 pb-10 sm:pb-12">
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

{{-- Buy box: single image + selectable variants + live price --}}
<section class="bg-white text-slate-900">
    <div class="max-w-marketing mx-auto px-5 sm:px-6 py-10 sm:py-14">
        <div
            class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-start"
            x-data="{
                pricing: @js($pricing),
                units: {{ (int) ($pricing['min_units'] ?? 0) }},
                get unitsValid() {
                    if (! this.pricing) return false;
                    const u = Number(this.units);
                    return Number.isInteger(u) && u >= Number(this.pricing.min_units) && u <= Number(this.pricing.max_units);
                },
                get estimatedTotal() {
                    if (! this.pricing) return 0;
                    return Math.round((Number(this.pricing.unit_price) || 0) * (Number(this.units) || 0) * 100) / 100;
                },
                checkoutUrl() {
                    const base = @js(route('dashboard.services.product', $product->slug));
                    return base + (base.includes('?') ? '&' : '?') + 'units=' + encodeURIComponent(String(this.units || ''));
                }
            }"
        >
            <div class="space-y-3">
                <div class="aspect-[4/3] sm:aspect-square rounded-xl overflow-hidden bg-slate-100 border border-slate-200">
                    @if($heroUrl)
                        <img
                            src="{{ $heroUrl }}"
                            alt="{{ $product->title }}"
                            class="w-full h-full object-cover"
                        >
                    @else
                        <div class="w-full h-full bg-gradient-to-br from-primary/20 via-slate-200 to-slate-100" aria-hidden="true"></div>
                    @endif
                </div>
            </div>

            <div class="flex flex-col gap-4 sm:gap-5 lg:pt-1">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary mb-2">
                        {{ $product->serviceCategory?->name
                            ?? $product->product_type?->label()
                            ?? ($product->productType?->name ?? 'Service') }}
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

                <div class="border-t border-b border-slate-200 py-4 space-y-4">
                    @if($pricing)
                        <div>
                            <p class="text-xl font-semibold text-slate-900">{{ $pricing['pricing_label'] }}</p>
                            <p class="mt-1 text-xs text-slate-600">
                                Minimum: {{ number_format($pricing['min_units']) }}
                                · Maximum: {{ number_format($pricing['max_units']) }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label for="public-units-input" class="block text-sm font-medium text-slate-700">
                                How many {{ $pricing['unit_label_plural'] }} do you need?
                            </label>
                            <input
                                id="public-units-input"
                                type="number"
                                step="1"
                                class="w-full max-w-xs rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900"
                                x-model.number="units"
                                min="{{ $pricing['min_units'] }}"
                                max="{{ $pricing['max_units'] }}"
                            >
                            <p class="text-xs text-red-600" x-show="!unitsValid" x-cloak>
                                Enter a whole number from {{ number_format($pricing['min_units']) }} to {{ number_format($pricing['max_units']) }}.
                            </p>
                        </div>

                        <div>
                            <span class="text-[11px] font-medium uppercase tracking-widest text-slate-500 block">Total</span>
                            <p
                                class="mt-1 text-2xl sm:text-3xl font-display font-bold text-primary"
                                x-text="'₦' + Number(unitsValid ? estimatedTotal : 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"
                            ></p>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Pricing for this product is not available yet.</p>
                    @endif
                </div>

                @if($showAbout)
                    <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line">{{ $product->description }}</p>
                @endif

                <div class="flex flex-col sm:flex-row gap-3 pt-1">
                    @if(auth()->user()?->isAgent())
                        <p class="text-sm text-slate-600 leading-relaxed">You're signed in as an Agent. Buying services needs a Creator account.</p>
                    @else
                    <x-ui.button
                        :href="route('dashboard.services.checkout', $product->slug)"
                        variant="primary"
                        size="lg"
                        class="!px-8 hover:!bg-primary-hover"
                        x-bind:href="checkoutUrl()"
                        x-bind:disabled="!unitsValid"
                        @click="if (!unitsValid) { $event.preventDefault() }"
                    >
                        {{ auth()->check() ? 'Buy Now' : 'Log in to buy' }}
                    </x-ui.button>
                    @endif
                    @include('partials.catalog.view-demo-modal', ['product' => $product])
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
