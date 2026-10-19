@props(['count' => 1])

@php
  $gridClass = match (true) {
      $count <= 1 => 'grid-cols-1 lg:grid-cols-3',
      $count === 2 => 'grid-cols-1 sm:grid-cols-2',
      default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
  };
@endphp

<div {{ $attributes->merge(['class' => "grid gap-3 sm:gap-4 {$gridClass}"]) }}>
    {{ $slot }}
</div>
