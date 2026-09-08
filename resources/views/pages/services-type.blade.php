@extends('layouts.marketing')

@section('title', ($content['label'] ?? 'Services'))

@section('content')
@php
    $crumbs = [
        ['label' => 'Home', 'href' => route('home')],
        ['label' => 'Services', 'href' => route('services')],
    ];
    $parentSlug = $preferGroupSlug ?: $groupSlug;
    if ($parentSlug && $groupContent) {
        $crumbs[] = ['label' => $groupContent['label'], 'href' => route('services.segment', $parentSlug)];
        if (! empty($activeCategory)) {
            $crumbs[] = [
                'label' => $content['label'] ?? config('catalog.types.'.$typeKey.'.label', $typeKey),
                'href' => route('services.type', ['category' => $parentSlug, 'service' => $typeKey]),
            ];
            $crumbs[] = ['label' => $activeCategory->name];
        } else {
            $crumbs[] = ['label' => $content['label'] ?? config('catalog.types.'.$typeKey.'.label', $typeKey)];
        }
    } else {
        $crumbs[] = ['label' => $content['label'] ?? config('catalog.types.'.$typeKey.'.label', $typeKey)];
    }
@endphp

@include('partials.marketing.page-header', [
    'breadcrumbs' => $crumbs,
    'title' => $content['hero_title'] ?? $content['label'],
    'subtitle' => $content['hero_subtitle'] ?? $content['short_description'],
    'image' => $content['banner_image'] ?? null,
])

<section class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 pb-12 sm:pb-16 space-y-8">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <h2 class="text-lg sm:text-xl font-bold font-display text-slate-900">All {{ $content['label'] }}</h2>
        <form method="GET" action="{{ $filterAction }}" class="flex flex-wrap gap-3 items-end">
            @if($categories->isNotEmpty())
                <div class="min-w-[160px]">
                    <label class="block text-xs text-slate-500 mb-1">Category</label>
                    <select name="category" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900">
                        <option value="">All</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(($filters['category'] ?? null) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="min-w-[180px] flex-1">
                <label class="block text-xs text-slate-500 mb-1">Search</label>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Filter products…"
                       class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400">
            </div>
            <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-white hover:bg-primary-hover text-sm font-semibold transition-colors">Apply</button>
        </form>
    </div>

    @if($products->isEmpty())
        <p class="text-slate-500">No products match your filters.</p>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($products as $product)
                @include('partials.catalog.product-card', ['product' => $product])
            @endforeach
        </div>
        <div class="mt-8">{{ $products->links() }}</div>
    @endif
</section>
@endsection
