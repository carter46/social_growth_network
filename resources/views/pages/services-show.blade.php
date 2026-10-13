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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-start">
            <div class="space-y-3 lg:sticky lg:top-24 lg:self-start">
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

                @if($showAbout)
                    <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line">{{ $product->description }}</p>
                @endif

                @if(session('error'))
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ session('error') }}</div>
                @endif

                @include('partials.catalog.package-configurator', ['product' => $product, 'theme' => 'marketing'])

                <div class="flex flex-col sm:flex-row gap-3">
                    @include('partials.catalog.view-demo-modal', ['product' => $product])
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
