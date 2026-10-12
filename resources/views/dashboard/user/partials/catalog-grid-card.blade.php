@php
    $href = $href ?? '#';
    $label = $label ?? '';
    $description = $description ?? null;
    $imageSrc = $imageSrc ?? null;
    $initials = $initials ?? strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $label) ?: 'S', 0, 2));
    $ctaLabel = $ctaLabel ?? 'Browse';
    $meta = $meta ?? null;
    $price = $price ?? null;
@endphp
<x-dashboard.card :padding="false" class="flex h-full flex-col overflow-hidden">
    <div class="p-3">
        <a href="{{ $href }}" class="relative block aspect-[4/3] overflow-hidden rounded-lg bg-muted">
            @if($imageSrc)
                <img src="{{ $imageSrc }}" alt="" class="h-full w-full object-cover">
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
        <a href="{{ $href }}" class="font-semibold text-text-primary line-clamp-2 hover:text-primary">{{ $label }}</a>
        @if(filled($description))
            <p class="text-sm text-text-secondary line-clamp-2">{{ $description }}</p>
        @endif
        @if($meta)
            <p class="text-xs text-text-muted">{{ $meta }}</p>
        @endif
        <div class="mt-auto pt-2">
            @if($price !== null)
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <span class="font-semibold text-primary">From {{ money((float) $price, 2) }}</span>
                    <x-dashboard.button :href="$href" size="xs" class="w-full sm:w-auto">{{ $ctaLabel }}</x-dashboard.button>
                </div>
            @else
                <x-dashboard.button :href="$href" size="xs" class="w-full sm:w-auto">{{ $ctaLabel }}</x-dashboard.button>
            @endif
        </div>
    </div>
</x-dashboard.card>
