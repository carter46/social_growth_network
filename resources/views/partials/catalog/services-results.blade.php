@php
    $productCount = $products?->total() ?? 0;
    $activeCategory = $activeCategory ?? '';
    $q = $q ?? '';
    $activeCategoryLabel = $activeCategory === ''
        ? 'All Services'
        : (collect($groups ?? [])->firstWhere('slug', $activeCategory)['label'] ?? $activeCategory);
@endphp

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
</div>

@if(! $products || $products->isEmpty())
    <div class="bg-white rounded-xl border border-slate-200 p-10 text-center">
        <p class="text-slate-600 mb-4">No campaign packages match your filters.</p>
        <button
            type="button"
            data-services-action="reset"
            class="inline-flex text-sm font-semibold text-primary hover:underline"
        >
            Clear filters
        </button>
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
        @foreach($products as $product)
            @include('partials.catalog.marketplace-product-card', ['product' => $product])
        @endforeach
    </div>
    <div class="mt-2">{{ $products->links() }}</div>
@endif
