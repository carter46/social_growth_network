@php
    $product = $tool->product;
    $heroUrl = $product
        ? media_url($product->heroMedia ?? null, $product->hero_image ?? null, 'medium')
        : null;
    $title = $tool->resolvedDisplayName();
    $initials = strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $title) ?: 'T', 0, 2));
    $href = route('dashboard.my-tools.show', $tool);
@endphp
<x-dashboard.card :padding="false" class="flex h-full flex-col overflow-hidden">
    <div class="p-3">
        <a href="{{ $href }}" class="relative block aspect-[4/3] overflow-hidden rounded-lg bg-muted">
            @if($heroUrl)
                <img src="{{ $heroUrl }}" alt="" class="h-full w-full object-cover">
            @else
                <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary/40 via-muted to-elevated">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full border border-white/20 bg-white/15 text-xs font-bold text-white" aria-hidden="true">
                        {{ $initials }}
                    </span>
                </div>
            @endif
        </a>
    </div>
    <div class="flex flex-1 flex-col space-y-2 px-4 pb-4">
        <a href="{{ $href }}" class="block font-semibold text-text-primary line-clamp-2 hover:text-primary">{{ $title }}</a>
        @if(filled($product?->description))
            <p class="text-sm text-text-secondary line-clamp-2">{{ \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 160) }}</p>
        @endif
        <div class="mt-auto pt-2">
            <x-dashboard.button :href="$href" size="xs" class="w-full sm:w-auto">Open in My Tools</x-dashboard.button>
        </div>
    </div>
</x-dashboard.card>
