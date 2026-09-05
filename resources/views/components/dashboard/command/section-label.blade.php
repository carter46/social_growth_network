{{--
  Command-center accents (TaskPulse semantic roles):
  primary = brand / revenue · success = positive · amber = warning
  secondary = supporting · orange = support · danger = alerts
--}}
@props([
    'title',
    'accent' => 'primary',
    'action' => null,
    'actionHref' => null,
])

@php
    $accents = [
        'primary' => 'bg-primary',
        'emerald' => 'bg-primary',
        'blue' => 'bg-primary',
        'success' => 'bg-success',
        'amber' => 'bg-amber-500',
        'indigo' => 'bg-text-secondary',
        'orange' => 'bg-orange-500',
        'red' => 'bg-danger',
    ];
    $bar = $accents[$accent] ?? $accents['primary'];
@endphp

<div {{ $attributes->class(['flex items-center justify-between mb-4']) }}>
    <div class="flex items-center gap-2">
        <div class="h-4 w-1 rounded-full {{ $bar }}"></div>
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 dark:text-text-muted">{{ $title }}</h2>
    </div>
    @if ($action && $actionHref)
        <a href="{{ $actionHref }}" class="text-[10px] font-bold uppercase tracking-wider text-primary hover:underline">{{ $action }}</a>
    @endif
</div>
