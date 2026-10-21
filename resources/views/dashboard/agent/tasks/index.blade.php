@extends('layouts.dashboard-agent')

@section('title', $title)

@section('content')
@php
    $timeLeft = function ($participation): ?string {
        if ($participation->status !== 'started' || ! $participation->expires_at) {
            return null;
        }
        $left = $participation->secondsRemaining();
        if ($left === null) {
            return null;
        }

        return $left > 0
            ? __(':time left', ['time' => $left >= 3600 ? floor($left / 3600).'h '.floor(($left % 3600) / 60).'m' : max(1, (int) ceil($left / 60)).'m'])
            : __('Time is up');
    };
@endphp
<x-layout.page
    title="{{ $title }}"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        [$title, null],
    ]"
>
    @if ($participations->isEmpty())
        <x-dashboard.empty-state
            icon="orders"
            title="No tasks here"
            description="Start a campaign from the marketplace."
            :action="['href' => route('agent.marketplace'), 'label' => 'Browse marketplace']"
        />
    @else
        <div class="space-y-2 lg:hidden">
            @foreach ($participations as $participation)
                @php $leftLabel = $timeLeft($participation); @endphp
                <details class="task-acc overflow-hidden rounded-xl border border-border-default bg-elevated">
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3">
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold text-text-primary">{{ $participation->campaign?->title }}</span>
                        <x-dashboard.badge :status="$participation->status" />
                        <span class="task-acc-chevron inline-flex text-text-muted" aria-hidden="true">
                            <x-ui.icon name="chevron-down" class="h-4 w-4" />
                        </span>
                    </summary>
                    <div class="space-y-3 border-t border-border-default px-4 py-3 text-sm">
                        <p class="text-text-secondary">Reward <span class="font-medium text-text-primary">{{ money($participation->reward_amount, 2, 4) }}</span></p>
                        @if ($leftLabel)
                            <p class="text-text-muted">{{ $leftLabel }}</p>
                        @endif
                        <x-dashboard.button :href="route('agent.tasks.show', $participation)" variant="secondary" size="sm">Open</x-dashboard.button>
                    </div>
                </details>
            @endforeach
        </div>

        <div class="hidden lg:block">
            <x-dashboard.table striped>
                <x-slot:head>
                    <x-dashboard.th>Campaign</x-dashboard.th>
                    <x-dashboard.th>Status</x-dashboard.th>
                    <x-dashboard.th>Reward</x-dashboard.th>
                    <x-dashboard.th></x-dashboard.th>
                </x-slot:head>
                @foreach ($participations as $participation)
                    @php $leftLabel = $timeLeft($participation); @endphp
                    <tr class="hover:bg-muted/50">
                        <x-dashboard.td class="font-medium">{{ $participation->campaign?->title }}</x-dashboard.td>
                        <x-dashboard.td>
                            <x-dashboard.badge :status="$participation->status" />
                            @if ($leftLabel)
                                <span class="ml-1 text-xs text-text-muted">{{ $leftLabel }}</span>
                            @endif
                        </x-dashboard.td>
                        <x-dashboard.td>{{ money($participation->reward_amount, 2, 4) }}</x-dashboard.td>
                        <x-dashboard.td>
                            <x-dashboard.button :href="route('agent.tasks.show', $participation)" variant="link" size="xs">Open</x-dashboard.button>
                        </x-dashboard.td>
                    </tr>
                @endforeach
            </x-dashboard.table>
        </div>
    @endif

    <div class="mt-4">{{ $participations->links() }}</div>
</x-layout.page>
<style>
    .task-acc > summary { list-style: none; }
    .task-acc > summary::-webkit-details-marker { display: none; }
    .task-acc[open] .task-acc-chevron { transform: rotate(180deg); }
    .task-acc-chevron { transition: transform 150ms ease; }
</style>
@endsection
