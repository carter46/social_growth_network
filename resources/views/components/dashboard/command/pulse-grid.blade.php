@props(['items' => []])

<div {{ $attributes->class(['grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3']) }}>
    @foreach ($items as $item)
        <x-dashboard.command.pulse-kpi
            :label="$item['label'] ?? ''"
            :value="$item['value'] ?? '-'"
            :accent="$item['accent'] ?? 'emerald'"
            :delta="$item['delta'] ?? null"
            :delta-label="$item['delta_label'] ?? null"
            :hint="$item['hint'] ?? null"
            :description="$item['description'] ?? null"
            :badge="$item['badge'] ?? null"
            :sparkline="$item['sparkline'] ?? null"
            :href="$item['href'] ?? null"
        />
    @endforeach
</div>
