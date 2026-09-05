@props([
    'title' => 'Revenue & Transactional Volume',
    'subtitle' => null,
    'labels' => [],
    'values' => [],
    'compareValues' => null,
    'compareLabel' => 'Prior period',
    'height' => '20rem',
    'id' => null,
    'footerLabels' => null,
])

@php
    $chartId = $id ?: 'command-hero-'.uniqid();
    $datasets = [[
        'label' => 'Revenue',
        'data' => $values,
        'borderColor' => '#004AC6',
        'backgroundColor' => 'rgba(0, 74, 198, 0.12)',
        'fill' => true,
        'tension' => 0.4,
        'pointRadius' => 0,
        'pointHoverRadius' => 5,
        'borderWidth' => 3,
    ]];
    if (is_array($compareValues) && count($compareValues) > 0) {
        $datasets[] = [
            'label' => $compareLabel,
            'data' => $compareValues,
            'borderColor' => '#cbd5e1',
            'backgroundColor' => 'transparent',
            'fill' => false,
            'tension' => 0.35,
            'pointRadius' => 0,
            'borderWidth' => 2,
            'borderDash' => [4, 4],
        ];
    }
    $hasData = count(array_filter($values, fn ($v) => $v !== null && (float) $v != 0)) > 0;
@endphp

<div {{ $attributes->class(['flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-border-default dark:bg-elevated']) }}>
    <div class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-border-subtle">
        <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-text-primary">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-0.5 text-[10px] text-slate-500 dark:text-text-muted">{{ $subtitle }}</p>
            @endif
        </div>
        <div class="flex items-center gap-3 text-[10px] font-bold text-slate-500">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary"></span> Revenue</span>
            @if (is_array($compareValues))
                <span class="flex items-center gap-1.5 text-slate-400"><span class="h-2 w-2 rounded-full bg-slate-300"></span> {{ $compareLabel }}</span>
            @endif
        </div>
    </div>
    <div class="relative flex-1 p-6" style="min-height: {{ $height }};">
        @if (! $hasData)
            <div class="absolute inset-0 z-10 flex items-center justify-center text-sm text-slate-400">No data in this range</div>
        @endif
        <canvas id="{{ $chartId }}" aria-label="{{ $title }}" class="command-chart {{ $hasData ? '' : 'opacity-20' }}" data-chart-theme="primary-area"
            data-labels='@json($labels)' data-datasets='@json($datasets)'></canvas>
    </div>
    @if (is_array($footerLabels) && count($footerLabels))
        <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-6 py-3 text-[10px] font-bold text-slate-400 dark:border-border-subtle dark:bg-muted/30">
            @foreach ($footerLabels as $fl)
                <span class="{{ !empty($fl['peak']) ? 'text-primary' : '' }}">{{ $fl['label'] ?? $fl }}</span>
            @endforeach
        </div>
    @endif
</div>
