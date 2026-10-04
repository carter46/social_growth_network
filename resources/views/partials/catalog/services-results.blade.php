@php
    $productCount = $products?->total() ?? 0;
    $activeCategory = $activeCategory ?? '';
    $q = $q ?? '';
    $showYouTube = $showYouTube ?? ($activeCategory === '' || $activeCategory === 'youtube');
    $showOtherSocial = $showOtherSocial ?? ($activeCategory !== 'youtube');
    $youtubeCatalog = $youtubeCatalog ?? ['featured' => null, 'others' => collect()];
    $hasYouTube = is_array($youtubeCatalog['featured'] ?? null) || collect($youtubeCatalog['others'] ?? [])->isNotEmpty();
    $activeCategoryLabel = $activeCategory === ''
        ? 'All Services'
        : (collect($groups ?? [])->firstWhere('slug', $activeCategory)['label'] ?? $activeCategory);
    $otherHeading = $activeCategory === '' ? 'Other social media services' : $activeCategoryLabel.' services';
    $budget = $budget ?? '';
    if ($showOtherSocial && $showYouTube && $hasYouTube && $productCount === 0 && $q === '' && $budget === '') {
        $showOtherSocial = false;
    }
@endphp

@if($showYouTube && $hasYouTube)
    @include('partials.catalog.services-youtube', ['youtubeCatalog' => $youtubeCatalog])
@endif

@if($showOtherSocial)
    <div id="other-social-services" class="flex flex-col gap-4 sm:gap-5 {{ $showYouTube && $hasYouTube ? 'pt-2' : '' }}">
        <div>
            <h2 class="font-display text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">{{ $otherHeading }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">
                @if($q !== '')
                    Showing {{ $productCount }} {{ \Illuminate\Support\Str::plural('result', $productCount) }} for “{{ $q }}”
                @else
                    Showing {{ $productCount }} {{ \Illuminate\Support\Str::plural('package', $productCount) }}
                @endif
            </p>
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
    </div>
@elseif(! $hasYouTube)
    <div class="bg-white rounded-xl border border-slate-200 p-10 text-center">
        <p class="text-slate-600 mb-4">No YouTube packages match your filters.</p>
        <button
            type="button"
            data-services-action="reset"
            class="inline-flex text-sm font-semibold text-primary hover:underline"
        >
            Clear filters
        </button>
    </div>
@endif
