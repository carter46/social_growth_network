@php
    $heroUrl = media_url($product->heroMedia ?? null, $product->hero_image ?? null, 'medium');
    $href = route('dashboard.services.product', $product->slug);
@endphp
@include('dashboard.user.partials.catalog-grid-card', [
    'href' => $href,
    'label' => $product->title,
    'description' => \Illuminate\Support\Str::limit(strip_tags((string) ($product->description ?? '')), 160) ?: null,
    'imageSrc' => $heroUrl,
    'ctaLabel' => 'View',
    'price' => $product->displayPrice(),
])
