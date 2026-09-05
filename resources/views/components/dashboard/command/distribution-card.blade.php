@props([
    'title' => 'Distribution',
    'centerValue' => '—',
    'centerLabel' => null,
    'slices' => [],
    'id' => null,
])

@php
    $chartId = $id ?: 'command-donut-'.uniqid();
    $labels = collect($slices)->pluck('label')->all();
    $values = collect($slices)->pluck('value')->all();
    $colors = collect($slices)->map(fn ($s) => $s['color'] ?? '#3b82f6')->all();
    $datasets = [[
        'data' => $values,
        'backgroundColor' => $colors,
        'borderWidth' => 0,
        'hoverOffset' => 6,
    ]];
@endphp

<div {{ $attributes->class(['flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-border-default dark:bg-elevated']) }}>
    <h3 class="mb-6 text-sm font-bold text-slate-800 dark:text-text-primary">{{ $title }}</h3>
    <div class="flex flex-1 flex-col items-center justify-center">
        <div class="relative h-44 w-44">
            <canvas id="{{ $chartId }}" class="command-chart" data-chart-theme="donut"
                data-labels='@json($labels)' data-datasets='@json($datasets)'></canvas>
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                <span class="text-2xl font-bold leading-none text-slate-900 dark:text-text-primary">{{ $centerValue }}</span>
                @if ($centerLabel)
                    <span class="mt-1 text-[9px] font-black uppercase tracking-tighter text-slate-400">{{ $centerLabel }}</span>
                @endif
            </div>
        </div>
        <div class="mt-8 w-full space-y-3">
            @foreach ($slices as $slice)
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full" style="background: {{ $slice['color'] ?? '#94a3b8' }}"></div>
                        <span class="text-[11px] font-semibold text-slate-600 dark:text-text-secondary">{{ $slice['label'] ?? '' }}</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-[11px] font-bold text-slate-900 dark:text-text-primary">{{ $slice['percent'] ?? $slice['value'] ?? '' }}</span>
                        @if (isset($slice['delta']))
                            <span class="text-[9px] font-bold {{ (float) $slice['delta'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ (float) $slice['delta'] >= 0 ? '↑' : '↓' }} {{ abs((float) $slice['delta']) }}%
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
            @if (count($slices) === 0)
                <p class="text-center text-xs text-slate-400">No distribution data</p>
            @endif
        </div>
    </div>
</div>
