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

<section class="max-w-marketing mx-auto px-5 sm:px-6 pb-12 sm:pb-16 space-y-8">
    @if(! empty($typeCards) && $typeCards->isNotEmpty())
        <div class="flex flex-col gap-2">
            <h2 class="text-lg sm:text-xl font-bold font-display">Services in {{ $content['label'] }}</h2>
            <p class="text-sm text-slate-400">Choose a service to browse products and plans.</p>
        </div>
        <x-ui.card-grid :count="$typeCards->count()">
            @foreach($typeCards as $card)
                @include('partials.catalog.explore-card', ['card' => $card])
            @endforeach
        </x-ui.card-grid>
    @else
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <h2 class="text-lg sm:text-xl font-bold font-display">All {{ $content['label'] }}</h2>
            <form method="GET" action="{{ route('services.segment', $groupSlug) }}" class="flex flex-wrap gap-3 items-end">
                @if(count($typeKeys) > 1)
                    <div class="min-w-[140px]">
                        <label class="block text-xs text-slate-400 mb-1">Type</label>
                        <select name="type" class="w-full rounded-xl border border-white/10 bg-slate-900/50 px-3 py-2 text-sm text-white">
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
                        <label class="block text-xs text-slate-400 mb-1">Category</label>
                        <select name="category" class="w-full rounded-xl border border-white/10 bg-slate-900/50 px-3 py-2 text-sm text-white">
                            <option value="">All</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(($filters['category'] ?? null) == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="min-w-[180px] flex-1">
                    <label class="block text-xs text-slate-400 mb-1">Search</label>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Filter services…"
                           class="w-full rounded-xl border border-white/10 bg-slate-900/50 px-3 py-2 text-sm text-white placeholder:text-slate-500">
                </div>
                <button type="submit" class="px-4 py-2 rounded-xl bg-white text-slate-900 hover:bg-primary-hover hover:text-white text-sm font-semibold transition-colors">Apply</button>
            </form>
        </div>

        @if(! $products || $products->isEmpty())
            <p class="text-slate-400">No services match your filters.</p>
        @else
            <x-ui.card-grid :count="$products->count()">
                @foreach($products as $product)
                    @include('partials.catalog.product-card', ['product' => $product])
                @endforeach
            </x-ui.card-grid>
            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    @endif
</section>
@endsection
