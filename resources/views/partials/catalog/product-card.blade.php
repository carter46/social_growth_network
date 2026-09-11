@php
    $browse = app(\App\Modules\Catalog\Services\CatalogBrowseService::class);
    $href = $browse->productUrl($product);
    $heroUrl = media_url($product->heroMedia ?? null, $product->hero_image, 'medium');
@endphp
@include('partials.catalog.grid-card', [
    'href' => $href,
    'label' => $product->title,
    'description' => \Illuminate\Support\Str::limit(strip_tags((string) ($product->description ?? '')), 160) ?: null,
    'imageSrc' => $heroUrl,
    'icon' => $product->product_type?->icon() ?? 'grid',
    'ctaLabel' => 'View now',
    'price' => $product->displayPrice(),
])
