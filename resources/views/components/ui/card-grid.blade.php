@props(['count' => 1])

@php
  // Mobile: 1 col when alone (vertical), 2 cols when multiple.
  // Desktop: keep grid width - 1 item still sits in a 3-col grid; 2 items use 2 cols.
  $gridClass = match (true) {
      $count <= 1 => 'grid-cols-1 lg:grid-cols-3',
      $count === 2 => 'grid-cols-2 lg:grid-cols-2',
      default => 'grid-cols-2 lg:grid-cols-3',
  };
@endphp

<div {{ $attributes->merge(['class' => "grid gap-5 sm:gap-6 {$gridClass}"]) }}>
    {{ $slot }}
</div>
