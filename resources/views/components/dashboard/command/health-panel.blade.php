@props([
    'rings' => [],
    'metrics' => [],
    'title' => 'System Health',
    'checkedAt' => null,
    'viewMoreUrl' => null,
    'limit' => 5,
])

@php
    $viewMoreUrl = $viewMoreUrl ?: route('admin.monitoring');
    $visible = array_slice(array_values($metrics), 0, (int) $limit);
@endphp

<div {{ $attributes->class(['rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-xl']) }}>
    <div class="mb-6 flex items-center justify-between">
        <h3 class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-white/40">
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-success"></span> {{ $title }}
        </h3>
        <span class="text-[10px] font-bold text-white/40">{{ $checkedAt ? \Illuminate\Support\Carbon::parse($checkedAt)->diffForHumans() : 'Live' }}</span>
    </div>

    @if (count($rings))
        <div class="mb-6 grid grid-cols-2 gap-4">
            @foreach ($rings as $ring)
                @php
                    $pct = max(0, min(100, (float) ($ring['pct'] ?? 0)));
                    $dash = $pct.' 100';
                @endphp
                <div class="flex flex-col items-center rounded-xl border border-white/10 bg-white/5 p-3 text-center">
                    <div class="relative mb-2 h-12 w-12">
                        <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" fill="transparent" r="16" stroke="rgba(255,255,255,0.05)" stroke-width="3"></circle>
                            <circle cx="18" cy="18" fill="transparent" r="16" stroke="{{ $ring['color'] ?? '#006243' }}" stroke-dasharray="{{ $dash }}" stroke-width="3"></circle>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center text-[10px] font-black text-white">{{ $ring['value'] ?? '—' }}</div>
                    </div>
                    <span class="text-[9px] font-black uppercase tracking-wider text-white/50">{{ $ring['label'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($visible as $metric)
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="h-1.5 w-1.5 shrink-0 rounded-full {{ !empty($metric['alert']) ? 'bg-danger shadow-[0_0_8px_rgba(186,26,26,0.5)]' : (!empty($metric['na']) ? 'bg-white/30' : (($metric['ok'] ?? true) ? 'bg-success shadow-[0_0_8px_rgba(104,219,169,0.45)]' : 'bg-amber-400')) }}"></div>
                    <div class="min-w-0">
                        <span class="block text-[10px] font-bold uppercase text-white/70">{{ $metric['label'] ?? '' }}</span>
                        @if (! empty($metric['checked_at']))
                            <span class="text-[9px] text-white/30">{{ \Illuminate\Support\Carbon::parse($metric['checked_at'])->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
                <span class="shrink-0 font-mono text-[10px] {{ !empty($metric['alert']) ? 'font-black text-red-400' : (!empty($metric['na']) ? 'text-white/35' : 'text-white/45') }}">{{ $metric['value'] ?? '—' }}</span>
            </div>
        @empty
            <p class="text-xs text-white/50">Monitoring not configured for this environment.</p>
        @endforelse
    </div>

    <a href="{{ $viewMoreUrl }}" class="mt-6 block w-full rounded-lg border border-white/10 py-2.5 text-center text-[10px] font-black uppercase tracking-widest text-white/60 transition-colors hover:border-primary/40 hover:text-primary">View more</a>
</div>
