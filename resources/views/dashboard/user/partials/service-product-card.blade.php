@php
    $heroUrl = media_url($product->heroMedia ?? null, $product->hero_image ?? null, 'medium');
    $href = route('dashboard.services.product', $product->slug);
    $fullTitle = (bool) ($fullTitle ?? false);
@endphp
@include('dashboard.user.partials.catalog-grid-card', [
    'href' => $href,
    'label' => $product->title,
    'description' => \Illuminate\Support\Str::limit(strip_tags((string) ($product->description ?? '')), 160) ?: null,
    'imageSrc' => $heroUrl,
    'ctaLabel' => 'View',
    'price' => $product->displayPrice(),
    'titleClass' => $fullTitle
        ? 'text-sm font-semibold text-text-primary hover:text-primary'
        : 'text-sm sm:text-base font-semibold text-text-primary line-clamp-2 hover:text-primary',
])
