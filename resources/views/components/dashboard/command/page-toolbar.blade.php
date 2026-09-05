@props([
    'greeting' => 'Overview',
    'subtitle' => null,
    'breadcrumb' => null,
    'range' => '24h',
    'showExport' => false,
    'exportUrl' => null,
])

<header {{ $attributes->class(['flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between']) }}>
    <div>
        @if ($breadcrumb)
            <nav class="mb-1.5 flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-text-muted">
                @foreach ($breadcrumb as $i => $crumb)
                    @if ($i > 0)
                        <span aria-hidden="true" class="text-slate-300">/</span>
                    @endif
                    <span class="{{ $loop->last ? 'text-primary' : '' }}">{{ is_array($crumb) ? ($crumb[0] ?? '') : $crumb }}</span>
                @endforeach
            </nav>
        @endif
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-text-primary">{{ $greeting }}</h1>
        @if ($subtitle)
            <p class="mt-1.5 text-sm text-slate-500 dark:text-text-secondary">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <x-dashboard.command.range-pills :value="$range" />
        @if ($showExport && $exportUrl)
            <a href="{{ $exportUrl }}" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-bold text-white hover:bg-slate-800 dark:bg-elevated dark:border dark:border-border-default dark:text-text-primary">
                Export
            </a>
        @endif
    </div>
</header>
