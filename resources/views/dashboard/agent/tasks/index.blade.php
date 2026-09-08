@extends('layouts.dashboard-agent')

@section('title', $title)

@section('content')
<x-layout.page
    title="{{ $title }}"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        [$title, null],
    ]"
>
    <x-dashboard.table
        :empty="$participations->isEmpty()"
        empty-title="No tasks here"
        empty-description="Start a campaign from the marketplace."
        empty-icon="orders"
        :empty-action="['href' => route('agent.marketplace'), 'label' => 'Browse marketplace']"
        striped
    >
        <x-slot:head>
            <x-dashboard.th>Campaign</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Reward</x-dashboard.th>
            <x-dashboard.th></x-dashboard.th>
        </x-slot:head>
        @foreach ($participations as $participation)
            <tr class="hover:bg-muted/50">
                <x-dashboard.td class="font-medium">{{ $participation->campaign?->title }}</x-dashboard.td>
                <x-dashboard.td><x-dashboard.badge :status="$participation->status" /></x-dashboard.td>
                <x-dashboard.td>₦{{ number_format((float) $participation->reward_amount, 2) }}</x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.button :href="route('agent.tasks.show', $participation)" variant="link" size="xs">Open</x-dashboard.button>
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <div class="mt-4">{{ $participations->links() }}</div>
</x-layout.page>
@endsection
