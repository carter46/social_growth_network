@php
    $browse = app(\App\Modules\Catalog\Services\CatalogBrowseService::class);
    $href = $browse->productUrl($product);
    $heroUrl = media_url($product->heroMedia ?? null, $product->hero_image, 'medium')
        ?: $product->listThumbnailUrl();
    $categoryLabel = $product->serviceCategory?->name
        ?? ($product->productType?->serviceCategory?->name ?? 'Campaign');
    $fromPrice = $product->displayPrice();
    $variants = ($product->relationLoaded('activeVariants') ? $product->activeVariants : $product->activeVariants()->get())
        ->sortBy('price')
        ->take(3)
        ->values();
    $dotColors = [
        'youtube' => 'bg-red-500',
        'facebook' => 'bg-blue-600',
        'instagram' => 'bg-pink-500',
        'tiktok' => 'bg-slate-900',
        'twitter' => 'bg-sky-500',
    ];
    $categorySlug = $product->categorySlug() ?? '';
    $dot = $dotColors[$categorySlug] ?? 'bg-primary';
@endphp
<article class="bg-white rounded-xl overflow-hidden border border-slate-200 shadow-sm flex flex-col group hover:shadow-md transition-shadow">
    <div class="relative h-44 w-full overflow-hidden bg-slate-100">
        @if($heroUrl)
            <img
                src="{{ $heroUrl }}"
                alt="{{ $product->title }}"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                loading="lazy"
            >
        @else
            <div class="w-full h-full bg-gradient-to-br from-primary/20 via-slate-200 to-slate-100"></div>
        @endif
        <div class="absolute top-3 left-3 bg-white/90 backdrop-blur-sm px-2.5 py-1 rounded-full flex items-center gap-1.5 shadow-sm">
            <span class="w-2 h-2 rounded-full {{ $dot }}"></span>
            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-800">{{ $categoryLabel }}</span>
        </div>
    </div>
    <div class="p-4 sm:p-5 flex flex-col flex-grow">
        <h3 class="font-display text-lg font-bold text-slate-900 mb-1.5 group-hover:text-primary transition-colors leading-snug">
            <a href="{{ $href }}" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded">{{ $product->title }}</a>
        </h3>
        <p class="text-sm text-slate-600 mb-4 line-clamp-2 leading-relaxed">
            {{ \Illuminate\Support\Str::limit(strip_tags((string) ($product->description ?? '')), 160) ?: 'Predefined package with upfront pricing and secure payment.' }}
        </p>

        @if($variants->isNotEmpty())
            <div class="flex flex-wrap gap-1.5 mb-5">
                @foreach($variants as $variant)
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-xs font-medium">
                        {{ $variant->displayLabel() }}
                    </span>
                @endforeach
            </div>
        @endif

        <div class="mt-auto pt-3 border-t border-slate-100 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">Packages from</span>
                <span class="font-display text-xl font-bold text-slate-900 tracking-tight">
                    ₦{{ number_format((float) $fromPrice, 0) }}
                </span>
            </div>
            <a
                href="{{ $href }}"
                class="shrink-0 inline-flex items-center gap-1 px-3.5 py-2 bg-slate-100 text-primary text-sm font-semibold rounded-lg hover:bg-primary hover:text-white transition-colors"
            >
                View
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
            </a>
        </div>
    </div>
</article>
