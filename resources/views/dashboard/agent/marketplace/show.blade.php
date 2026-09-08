@extends('layouts.dashboard-agent')

@section('title', $campaign->title)

@section('content')
<x-layout.page
    title="{{ $campaign->title }}"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Marketplace', route('agent.marketplace')],
        [$campaign->title, null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <x-dashboard.card class="space-y-3">
        <p class="text-sm text-text-secondary">{{ $campaign->product?->short_description }}</p>
        <dl class="grid gap-3 sm:grid-cols-3 text-sm">
            <div>
                <dt class="text-text-muted">Reward</dt>
                <dd class="font-semibold text-text-primary">₦{{ number_format((float) $campaign->locked_agent_reward, 2) }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Slots left</dt>
                <dd class="font-semibold text-text-primary">{{ $campaign->remainingSlots() }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Estimated time</dt>
                <dd class="font-semibold text-text-primary">{{ $campaign->estimated_minutes ? $campaign->estimated_minutes.' min' : '—' }}</dd>
            </div>
        </dl>
        @if ($campaign->target_url)
            <p class="text-sm">Target: <a href="{{ $campaign->target_url }}" class="text-accent underline" target="_blank" rel="noopener">{{ $campaign->target_url }}</a></p>
        @endif

        @if ($participation)
            <x-dashboard.button :href="route('agent.tasks.show', $participation)">Continue task</x-dashboard.button>
        @elseif ($campaign->isOpenForAgents())
            <form method="POST" action="{{ route('agent.marketplace.start', $campaign) }}">
                @csrf
                <x-dashboard.button type="submit" icon="plus">Start task</x-dashboard.button>
            </form>
        @else
            <x-dashboard.alert type="warning">This campaign is no longer open.</x-dashboard.alert>
        @endif
    </x-dashboard.card>
</x-layout.page>
@endsection
