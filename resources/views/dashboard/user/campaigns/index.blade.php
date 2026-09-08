@extends('layouts.dashboard-user')

@section('title', 'My Campaigns')

@section('content')
<x-layout.page
    title="My Campaigns"
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Campaigns', null],
    ]"
>
    <x-slot:actions>
        <x-dashboard.button :href="route('dashboard.services')" variant="secondary" size="sm">Buy package</x-dashboard.button>
    </x-slot:actions>

    <x-dashboard.table
        :empty="$campaigns->isEmpty()"
        empty-title="No campaigns yet"
        empty-description="Purchase a campaign package to create your first campaign."
        empty-icon="listings"
        :empty-action="['href' => route('dashboard.services'), 'label' => 'Browse packages']"
        striped
    >
        <x-slot:head>
            <x-dashboard.th>Title</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Progress</x-dashboard.th>
            <x-dashboard.th>Agent reward</x-dashboard.th>
            <x-dashboard.th></x-dashboard.th>
        </x-slot:head>
        @foreach ($campaigns as $campaign)
            <tr class="hover:bg-muted/50">
                <x-dashboard.td class="font-medium">{{ $campaign->title }}</x-dashboard.td>
                <x-dashboard.td><x-dashboard.badge :status="$campaign->status" /></x-dashboard.td>
                <x-dashboard.td>{{ $campaign->completed_count }} / {{ $campaign->quantity }}</x-dashboard.td>
                <x-dashboard.td>₦{{ number_format((float) $campaign->locked_agent_reward, 2) }}</x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.button :href="route('dashboard.campaigns.show', $campaign)" variant="link" size="xs">View</x-dashboard.button>
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <div class="mt-4">{{ $campaigns->links() }}</div>
</x-layout.page>
@endsection
