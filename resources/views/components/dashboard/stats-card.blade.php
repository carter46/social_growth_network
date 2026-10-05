@props([
    'label' => '',
    'value' => '',
    'hint' => null,
    'icon' => null,
    'href' => null,
    'arrow' => false,
])

@php
    $tag = $href ? 'a' : 'div';
    $showArrow = $arrow && $href;
    $baseClass = 'dashboard-card rounded-2xl border border-border-default bg-elevated p-5 shadow-panel '
        . ($showArrow ? 'group flex flex-col' : 'block');
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => $baseClass . ($href ? ' hover:border-primary/40 transition-colors' : '')]) }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ $label }}</p>
            <p class="mt-2 text-2xl font-bold text-text-primary">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 text-xs text-text-secondary">{{ $hint }}</p>
            @endif
        </div>
        @if ($icon)
            <div class="size-10 rounded-xl flex items-center justify-center" style="background: var(--th-icon-bg); color: var(--th-icon-fg);">
                <x-ui.icon :name="$icon" class="w-5 h-5" />
            </div>
        @endif
    </div>
    {{ $slot }}
    @if ($showArrow)
        <div class="mt-auto flex justify-end pt-4 text-text-muted transition-colors group-hover:text-primary">
            <x-ui.icon name="arrow-right" class="w-5 h-5 transition-transform group-hover:translate-x-1" />
        </div>
    @endif
</{{ $tag }}>
