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
    $variants = $product->activeVariants->sortBy('price')->values();
    $defaultVariant = $variants->firstWhere('is_default', true)
        ?? $variants->first();
    $variantPayload = $variants->map(fn ($v) => [
        'id' => $v->id,
        'label' => $v->displayLabel(),
        'price' => (float) $v->price,
        'description' => (string) ($v->description ?? ''),
    ])->values();

    $showAbout = filled($product->description)
        && (! $subtitle || trim((string) $product->description) !== trim((string) $subtitle));

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

{{-- Buy box: gallery + selectable variants + live price --}}
<section class="bg-white text-slate-900">
    <div class="max-w-marketing mx-auto px-5 sm:px-6 py-10 sm:py-14">
        <div
            class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-start"
            x-data="{
                active: 0,
                images: {{ Js::from($gallery) }},
                variants: @js($variantPayload),
                variantId: {{ (int) ($defaultVariant?->id ?? 0) }},
                get selected() {
                    return this.variants.find(v => Number(v.id) === Number(this.variantId)) || this.variants[0] || null;
                },
                checkoutUrl() {
                    const base = @js(route('dashboard.services.checkout', $product->slug));
                    if (! this.selected) return base;
                    return base + (base.includes('?') ? '&' : '?') + 'variant=' + this.selected.id;
                }
            }"
        >
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

            <div class="flex flex-col gap-4 sm:gap-5 lg:pt-1">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary mb-2">
                        {{ $product->serviceCategory?->name
                            ?? $product->product_type?->label()
                            ?? ($product->productType?->name ?? 'Campaign') }}
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
                    <div>
                        <span class="text-[11px] font-medium uppercase tracking-widest text-slate-500 block">
                            Selected plan
                        </span>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span
                                class="text-3xl font-display font-bold text-primary"
                                x-text="selected ? ('₦' + Number(selected.price).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) : '—'"
                            ></span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            From ₦{{ number_format($product->displayPrice(), 2) }}
                        </p>
                    </div>

                    @if($variants->isNotEmpty())
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Choose a plan</p>
                            <div class="space-y-2">
                                @foreach($variants as $variant)
                                    <label
                                        class="flex cursor-pointer flex-col gap-1 rounded-xl border px-4 py-3 transition-colors"
                                        :class="Number(variantId) === {{ (int) $variant->id }} ? 'border-primary bg-primary/5' : 'border-slate-200 hover:border-primary/40'"
                                    >
                                        <span class="flex items-center justify-between gap-3">
                                            <span class="flex items-center gap-3">
                                                <input
                                                    type="radio"
                                                    name="preview_variant_id"
                                                    value="{{ $variant->id }}"
                                                    class="accent-primary"
                                                    x-model.number="variantId"
                                                    @checked((int) $defaultVariant?->id === (int) $variant->id)
                                                >
                                                <span class="text-sm font-medium text-slate-900">{{ $variant->displayLabel() }}</span>
                                            </span>
                                            <span class="font-semibold text-slate-900">₦{{ number_format((float) $variant->price, 0) }}</span>
                                        </span>
                                        @if(filled($variant->description))
                                            <span
                                                class="pl-7 text-xs leading-relaxed text-slate-600"
                                                x-show="Number(variantId) === {{ (int) $variant->id }}"
                                            >{{ $variant->description }}</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">No plans are available for this product yet.</p>
                    @endif
                </div>

                @if($showAbout)
                    <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line">{{ $product->description }}</p>
                @endif

                <div class="flex flex-col sm:flex-row gap-3 pt-1">
                    <x-ui.button
                        :href="route('dashboard.services.checkout', $product->slug)"
                        variant="primary"
                        size="lg"
                        class="!px-8 hover:!bg-primary-hover"
                        x-bind:href="checkoutUrl()"
                    >
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
@endsection
