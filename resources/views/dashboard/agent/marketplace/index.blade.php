@extends('layouts.dashboard-agent')

@section('title', 'Task Marketplace')

@section('content')
<x-layout.page
    title="Task Marketplace"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Marketplace', null],
    ]"
>
    <x-dashboard.table
        :empty="$campaigns->isEmpty()"
        empty-title="No open campaigns"
        empty-description="Check back soon for new tasks from creators."
        empty-icon="listings"
        striped
    >
        <x-slot:head>
            <x-dashboard.th>Campaign</x-dashboard.th>
            <x-dashboard.th>Reward</x-dashboard.th>
            <x-dashboard.th>Slots left</x-dashboard.th>
            <x-dashboard.th>ETA</x-dashboard.th>
            <x-dashboard.th></x-dashboard.th>
        </x-slot:head>
        @foreach ($campaigns as $campaign)
            <tr class="hover:bg-muted/50">
                <x-dashboard.td class="font-medium">{{ $campaign->title }}</x-dashboard.td>
                <x-dashboard.td>₦{{ number_format((float) $campaign->locked_agent_reward, 2) }}</x-dashboard.td>
                <x-dashboard.td>{{ $campaign->availableStartSlots() }}</x-dashboard.td>
                <x-dashboard.td>{{ $campaign->estimated_minutes ? $campaign->estimated_minutes.' min' : '—' }}</x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.button :href="route('agent.marketplace.show', $campaign)" variant="link" size="xs">View</x-dashboard.button>
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <div class="mt-4">{{ $campaigns->links() }}</div>
</x-layout.page>
@endsection
