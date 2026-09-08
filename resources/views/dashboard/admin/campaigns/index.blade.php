@extends('layouts.dashboard-admin')

@section('title', 'Campaigns')

@section('content')
<x-layout.page
    title="Campaigns"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Campaigns', null],
    ]"
>
    <x-dashboard.table
        :empty="$campaigns->isEmpty()"
        empty-title="No campaigns"
        empty-description="Campaigns appear after creators purchase campaign packages."
        empty-icon="listings"
        striped
    >
        <x-slot:head>
            <x-dashboard.th>Title</x-dashboard.th>
            <x-dashboard.th>Creator</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Progress</x-dashboard.th>
            <x-dashboard.th></x-dashboard.th>
        </x-slot:head>
        @foreach ($campaigns as $campaign)
            <tr class="hover:bg-muted/50">
                <x-dashboard.td class="font-medium">{{ $campaign->title }}</x-dashboard.td>
                <x-dashboard.td>{{ $campaign->creator?->email }}</x-dashboard.td>
                <x-dashboard.td><x-dashboard.badge :status="$campaign->status" /></x-dashboard.td>
                <x-dashboard.td>{{ $campaign->completed_count }}/{{ $campaign->quantity }}</x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.button :href="route('admin.campaigns.show', $campaign)" variant="link" size="xs">Open</x-dashboard.button>
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>
    <div class="mt-4">{{ $campaigns->links() }}</div>
</x-layout.page>
@endsection
