@props([
    'id' => null,
    'title' => null,
    'labels' => [],
    'values' => [],
    'height' => '16rem',
])

@php
    $chartId = $id ?: 'chart-donut-'.uniqid();
    $payload = [
        'type' => 'doughnut',
        'data' => [
            'labels' => $labels,
            'datasets' => [[
                'data' => $values,
                'backgroundColor' => ['#004AC6', '#2563EB', '#006243', '#F59E0B', '#BA1A1A', '#565E74'],
            ]],
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['position' => 'bottom']],
        ],
    ];
@endphp

<x-dashboard.card variant="solid" {{ $attributes }}>
    @if ($title)
        <h3 class="text-base font-semibold text-text-primary mb-4">{{ $title }}</h3>
    @endif
    <div style="height: {{ $height }};">
        <canvas id="{{ $chartId }}" aria-label="{{ $title ?: 'Donut chart' }}"></canvas>
    </div>
</x-dashboard.card>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const el = document.getElementById(@json($chartId));
            if (!el || typeof Chart === 'undefined') return;
            new Chart(el, @json($payload));
        });
    </script>
@endpush
