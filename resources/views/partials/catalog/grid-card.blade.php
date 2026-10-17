@php
    $href = $href ?? '#';
    $label = $label ?? '';
    $description = $description ?? null;
    $imageSrc = $imageSrc ?? null;
    $icon = $icon ?? 'grid';
    $meta = $meta ?? null;
    $initials = $initials ?? strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $label) ?: 'S', 0, 2));
    $ctaLabel = $ctaLabel ?? 'Explore';
    $price = $price ?? null;
@endphp
<div class="group flex h-full flex-col overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-sm transition-shadow hover:shadow-md">
    <a href="{{ $href }}" class="relative aspect-[2/1] shrink-0 overflow-hidden bg-slate-800 focus-ring" tabindex="-1" aria-hidden="true">
        @if($imageSrc)
            <img src="{{ $imageSrc }}" alt="" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">
        @else
            <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary/50 via-slate-800 to-slate-900">
                <span class="flex h-12 w-12 items-center justify-center rounded-full border border-white/20 bg-white/15 font-display text-sm font-bold text-white" aria-hidden="true">
                    {{ $initials }}
                </span>
            </div>
            <div class="absolute bottom-2 right-2 opacity-50">
                <x-ui.icon :name="$icon" class="h-5 w-5 text-white" />
            </div>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2 p-4 text-left sm:p-5">
        <h3 class="line-clamp-2 font-display text-sm font-bold leading-snug text-slate-900 sm:text-base">
            <a href="{{ $href }}" class="hover:text-primary focus-ring rounded-sm">{{ $label }}</a>
        </h3>
        @if(filled($description))
            <p class="line-clamp-2 text-xs text-slate-500 sm:text-sm">{{ $description }}</p>
        @endif
        @if($meta)
            <p class="text-xs text-slate-400">{{ $meta }}</p>
        @endif
        <div class="flex-1"></div>
        @if($price !== null)
            <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                <span class="font-bold text-primary text-sm sm:text-base">From {{ money((float) $price, 2) }}</span>
                <x-ui.button :href="$href" size="xs" variant="primary">
                    {{ $ctaLabel }}
                </x-ui.button>
            </div>
        @else
            <div class="mt-1">
                <x-ui.button :href="$href" size="xs" variant="primary">
                    {{ $ctaLabel }}
                </x-ui.button>
            </div>
        @endif
    </div>
</div>
