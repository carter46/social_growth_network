@props([
    'title',
    'subtitle' => null,
    'icon' => 'plus',
    'href' => '#',
    'accent' => 'primary',
])

@php
    $iconWrap = [
        'primary' => 'bg-primary/10 text-primary group-hover:bg-primary',
        'emerald' => 'bg-primary/10 text-primary group-hover:bg-primary',
        'blue' => 'bg-primary/10 text-primary group-hover:bg-primary',
        'amber' => 'bg-amber-50 text-amber-600 group-hover:bg-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
        'indigo' => 'bg-muted text-text-secondary group-hover:bg-text-secondary',
        'orange' => 'bg-orange-50 text-orange-600 group-hover:bg-orange-600 dark:bg-orange-500/15 dark:text-orange-400',
        'success' => 'bg-success/10 text-success group-hover:bg-success',
    ];
    $wrap = $iconWrap[$accent] ?? $iconWrap['primary'];
@endphp

<a href="{{ $href }}" {{ $attributes->class([
    'group flex flex-col items-start rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md dark:border-border-default dark:bg-elevated',
]) }}>
    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-lg {{ $wrap }} group-hover:text-white transition-colors">
        <x-dashboard.icon :name="$icon" class="h-5 w-5" />
    </div>
    <span class="text-sm font-bold text-slate-900 dark:text-text-primary">{{ $title }}</span>
    @if ($subtitle)
        <span class="mt-1.5 text-[11px] leading-relaxed text-slate-500 dark:text-text-muted">{{ $subtitle }}</span>
    @endif
</a>
