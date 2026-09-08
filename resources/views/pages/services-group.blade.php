@extends('layouts.marketing')

@section('title', ($content['label'] ?? 'Services'))

@section('content')
@include('partials.marketing.page-header', [
    'breadcrumbs' => [
        ['label' => 'Home', 'href' => route('home')],
        ['label' => 'Services', 'href' => route('services')],
    ],
    'title' => $content['hero_title'] ?? $content['label'],
    'subtitle' => $content['hero_subtitle'] ?? $content['short_description'],
    'image' => $content['banner_image'] ?? null,
])

<section class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 pb-12 sm:pb-16 space-y-8">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <h2 class="text-lg sm:text-xl font-bold font-display text-slate-900">Products in {{ $content['label'] }}</h2>
        <form method="GET" action="{{ route('services.segment', $groupSlug) }}" class="flex flex-wrap gap-3 items-end">
            @if(count($typeKeys) > 1)
                <div class="min-w-[140px]">
                    <label class="block text-xs text-slate-500 mb-1">Type</label>
                    <select name="type" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900">
                        <option value="">All types</option>
                        @foreach($typeKeys as $key)
                            <option value="{{ $key }}" @selected(($filters['type'] ?? null) === $key)>
                                {{ config('catalog.types.'.$key.'.label', str_replace('_', ' ', ucfirst($key))) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if(isset($categories) && $categories->isNotEmpty())
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

    @if(! $products || $products->isEmpty())
        <p class="text-slate-500">No products match your filters.</p>
    @else
        <x-ui.card-grid :count="$products->count()">
            @foreach($products as $product)
                @include('partials.catalog.product-card', ['product' => $product])
            @endforeach
        </x-ui.card-grid>
        <div class="mt-8">{{ $products->links() }}</div>
    @endif
</section>
@endsection
