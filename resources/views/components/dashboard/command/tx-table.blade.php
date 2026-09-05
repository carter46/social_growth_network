@props([
    'rows' => [],
    'viewAllUrl' => null,
    'title' => 'Recent Transactions',
])

@php
    $statusClass = [
        'completed' => 'bg-success/10 text-success',
        'pending' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
        'failed' => 'bg-danger/10 text-danger',
        'processing' => 'bg-primary/10 text-primary',
    ];
    $avatarClass = [
        'emerald' => 'bg-primary/10 text-primary',
        'primary' => 'bg-primary/10 text-primary',
        'blue' => 'bg-primary/10 text-primary',
        'indigo' => 'bg-muted text-text-secondary',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
        'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-400',
        'success' => 'bg-success/10 text-success',
    ];
@endphp

<div {{ $attributes->class(['overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-border-default dark:bg-elevated']) }}>
    <div class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-border-subtle">
        <h3 class="text-sm font-bold text-slate-800 dark:text-text-primary">{{ $title }}</h3>
        <div class="flex items-center gap-3">
            @if ($viewAllUrl)
                <a href="{{ $viewAllUrl }}" class="text-[11px] font-bold text-primary hover:underline">View All</a>
            @endif
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="border-b border-slate-100 bg-slate-50/50 dark:border-border-subtle dark:bg-muted/20">
                <tr>
                    <th class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">Reference</th>
                    <th class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">Client / Actor</th>
                    <th class="px-6 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Amount (NGN)</th>
                    <th class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">Status</th>
                    <th class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">When</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="text-sm">
                @forelse ($rows as $i => $row)
                    @php
                        $status = strtolower((string) ($row['status'] ?? ''));
                        $tone = $row['avatar_tone'] ?? 'emerald';
                    @endphp
                    <tr class="group cursor-pointer border-b border-slate-50 transition-colors hover:bg-slate-50/80 dark:border-border-subtle dark:hover:bg-muted/30 {{ $i % 2 === 1 ? 'bg-slate-50/40 dark:bg-muted/10' : '' }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-muted">
                                    <x-dashboard.icon name="paid" class="h-3.5 w-3.5" />
                                </span>
                                <span class="font-mono text-xs font-bold text-slate-900 dark:text-text-primary">#{{ $row['reference'] ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-7 w-7 items-center justify-center rounded-full text-[10px] font-black {{ $avatarClass[$tone] ?? $avatarClass['emerald'] }}">
                                    {{ $row['user_initials'] ?? '?' }}
                                </div>
                                <div>
                                    <span class="block font-bold text-slate-700 dark:text-text-secondary">{{ $row['user_name'] ?? '—' }}</span>
                                    @if (! empty($row['type']))
                                        <span class="text-[10px] font-medium text-slate-400">{{ $row['type'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-base font-bold text-slate-900 dark:text-text-primary">{{ number_format((float) ($row['amount'] ?? 0), 2) }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase {{ $statusClass[$status] ?? 'bg-slate-100 text-slate-600' }}">{{ $row['status'] ?? '—' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[11px] font-medium text-slate-400">{{ $row['created_at'] ?? '' }}</span>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <x-dashboard.icon name="chevron-right" class="ml-auto h-4 w-4 text-slate-300 transition-colors group-hover:text-primary" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-400">No transactions yet</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
