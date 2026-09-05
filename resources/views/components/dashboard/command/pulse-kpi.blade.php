@props([
    'label',
    'value',
    'accent' => 'primary',
    'delta' => null,
    'deltaLabel' => null,
    'hint' => null,
    'description' => null,
    'badge' => null,
    'sparkline' => null,
    'href' => null,
])

@php
    $tops = [
        'primary' => 'border-t-primary',
        'emerald' => 'border-t-primary',
        'blue' => 'border-t-primary',
        'amber' => 'border-t-amber-500',
        'indigo' => 'border-t-text-secondary',
        'orange' => 'border-t-orange-500',
        'red' => 'border-t-danger',
        'success' => 'border-t-success',
    ];
    $deltaColors = [
        'primary' => 'text-primary',
        'emerald' => 'text-success',
        'blue' => 'text-primary',
        'amber' => 'text-amber-600',
        'indigo' => 'text-text-secondary',
        'orange' => 'text-orange-600',
        'red' => 'text-danger',
        'success' => 'text-success',
    ];
    $sparkColors = [
        'primary' => '#004AC6',
        'emerald' => '#004AC6',
        'blue' => '#2563EB',
        'amber' => '#f59e0b',
        'indigo' => '#565E74',
        'orange' => '#f97316',
        'red' => '#BA1A1A',
        'success' => '#006243',
    ];
    $glow = [
        'primary' => 'from-primary/10',
        'emerald' => 'from-primary/10',
        'blue' => 'from-primary/10',
        'amber' => 'from-amber-500/10',
        'indigo' => 'from-text-secondary/10',
        'orange' => 'from-orange-500/10',
        'red' => 'from-danger/10',
        'success' => 'from-success/10',
    ];
    $top = $tops[$accent] ?? $tops['primary'];
    $deltaClass = $deltaColors[$accent] ?? $deltaColors['primary'];
    if (is_numeric($delta) && (float) $delta < 0) {
        $deltaClass = 'text-danger';
    }
    $spark = is_array($sparkline) ? array_values($sparkline) : [];
    $sparkColor = $sparkColors[$accent] ?? '#004AC6';
    $sparkLabels = array_map('strval', array_keys($spark));
    $sparkDatasets = [[
        'data' => $spark,
        'borderColor' => $sparkColor,
        'backgroundColor' => 'transparent',
        'fill' => false,
        'tension' => 0.4,
        'pointRadius' => 0,
        'borderWidth' => 2,
    ]];
    $sparkId = 'spark-'.uniqid();
    $tag = $href ? 'a' : 'div';
    $badgeClass = is_array($badge) ? ($badge['class'] ?? 'bg-slate-100 text-slate-600') : 'bg-slate-100 text-slate-600';
    $badgeLabel = is_array($badge) ? ($badge['label'] ?? '') : (string) $badge;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([
        'group relative flex min-h-[9.5rem] flex-col overflow-hidden rounded-xl border border-slate-200 border-t-2 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md dark:border-border-default dark:bg-elevated',
        $top,
    ]) }}>
@else
    <div {{ $attributes->class([
        'group relative flex min-h-[9.5rem] flex-col overflow-hidden rounded-xl border border-slate-200 border-t-2 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md dark:border-border-default dark:bg-elevated',
        $top,
    ]) }}>
@endif
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-br {{ $glow[$accent] ?? $glow['emerald'] }} via-transparent to-transparent opacity-80"></div>
    <div class="relative flex items-start justify-between gap-2">
        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-text-muted">{{ $label }}</span>
        @if ($badge)
            <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide {{ $badgeClass }}">{{ $badgeLabel }}</span>
        @endif
    </div>
    <div class="relative mt-3 flex flex-col gap-3">
        <div class="min-w-0">
            <span class="block text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-text-primary">{{ $value }}</span>
            @if ($description)
                <p class="mt-1 text-[10px] font-medium text-slate-400 dark:text-text-muted">{{ $description }}</p>
            @endif
        </div>
        @if (count($spark) > 1)
            <div class="h-9 w-full max-w-[9rem]">
                <canvas
                    id="{{ $sparkId }}"
                    class="command-chart h-full w-full"
                    data-chart-theme="sparkline"
                    data-spark-color="{{ $sparkColor }}"
                    data-labels='@json($sparkLabels)'
                    data-datasets='@json($sparkDatasets)'
                ></canvas>
            </div>
        @endif
    </div>
    <div class="relative mt-auto flex items-center justify-between border-t border-slate-100 pt-3 dark:border-border-subtle">
        @if ($delta !== null)
            <span class="flex items-center gap-0.5 text-[10px] font-bold {{ $deltaClass }}">
                {{ (float) $delta >= 0 ? '↑' : '↓' }} {{ number_format(abs((float) $delta), 1) }}%
            </span>
            <span class="whitespace-nowrap text-[9px] font-medium text-slate-400">{{ $deltaLabel ?? 'vs prior period' }}</span>
        @elseif ($hint)
            <span class="text-[10px] font-medium text-slate-400">{{ $hint }}</span>
        @else
            <span></span>
        @endif
    </div>
@if ($href)
    </a>
@else
    </div>
@endif
