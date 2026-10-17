@php
    /** @var array $card */
    $href = $card['href'] ?? '#';
    $label = $card['label'] ?? '';
    $image = $card['card_image'] ?? null;
    $imageSrc = null;
    if (is_string($image) && $image !== '') {
        $trimmed = trim($image);
        if (preg_match('#^(https?:)?//#i', $trimmed) || str_starts_with($trimmed, '/')) {
            $imageSrc = $trimmed;
        } else {
            $imageSrc = asset(ltrim(str_replace('\\', '/', $trimmed), '/'));
        }
    }
@endphp
@include('partials.catalog.grid-card', [
    'href' => $href,
    'label' => $label,
    'description' => $card['short_description'] ?? null,
    'imageSrc' => $imageSrc,
    'icon' => $card['icon'] ?? 'grid',
    'meta' => $card['meta'] ?? null,
    'ctaLabel' => $card['cta'] ?? 'Explore',
])
