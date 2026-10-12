@extends('layouts.dashboard-user')

@section('title', $product->title)

@section('content')
@php
    $crumbs = [
        ['Dashboard', route('dashboard')],
        ['Services', route('dashboard.services')],
    ];
    if ($groupSlug && $groupLabel) {
        $crumbs[] = [$groupLabel, route('dashboard.services.browse', $groupSlug)];
    }
    $crumbs[] = [$product->title, null];
    $heroUrl = media_url($product->heroMedia, $product->hero_image, 'large')
        ?? media_url($product->heroMedia, $product->hero_image, 'medium');
    $isDomainProduct = $isDomainProduct ?? false;
@endphp
<x-layout.page
    :title="$product->title"
    width="full"
    :breadcrumb="$crumbs"
>
    <div class="space-y-6">
        @if(session('error'))
            <x-dashboard.alert type="danger">{{ session('error') }}</x-dashboard.alert>
        @endif

        <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
            <x-dashboard.card class="space-y-4 overflow-hidden !p-0">
                @if($heroUrl)
                    <div class="w-full bg-muted">
                        <img
                            src="{{ $heroUrl }}"
                            alt="{{ $product->title }}"
                            class="block w-full h-auto max-h-[28rem] object-contain"
                        >
                    </div>
                @endif
                <div class="space-y-4 p-5 sm:p-6">
                    @if(filled($product->description))
                        <div class="prose prose-sm max-w-none text-text-secondary whitespace-pre-line">{{ $product->description }}</div>
                    @endif
                    <div class="pt-1">
                        @include('partials.catalog.product-demo-actions', [
                            'product' => $product,
                            'modalName' => 'view-demo-dash-'.$product->id,
                        ])
                    </div>
                    @if (filled($product->tutorial_description))
                        <p class="text-sm text-text-secondary whitespace-pre-line">{{ $product->tutorial_description }}</p>
                    @endif
                </div>
            </x-dashboard.card>

            <div class="space-y-6">
                @if($isDomainProduct)
                    @include('dashboard.user.discover._domain-product-search', [
                        'product' => $product,
                        'domainTlds' => $domainTlds ?? [],
                        'domainTldsAdvanced' => $domainTldsAdvanced ?? [],
                    ])
                @else
                    @include('partials.catalog.package-configurator', ['product' => $product, 'theme' => 'dashboard'])
                @endif
                @if($groupSlug)
                    <a href="{{ route('dashboard.services.browse', $groupSlug) }}" class="inline-flex text-sm text-text-secondary hover:text-primary">← Back to {{ $groupLabel ?? 'services' }}</a>
                @endif
            </div>
        </div>
    </div>
</x-layout.page>
@endsection
