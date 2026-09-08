@extends('layouts.dashboard-admin')

@section('title', 'Task verification')

@section('content')
<x-layout.page
    title="Task verification"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Verification', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif

    <x-dashboard.table
        :empty="$queue->isEmpty()"
        empty-title="Queue empty"
        empty-description="Submitted agent tasks will appear here for review."
        empty-icon="verified"
        striped
    >
        <x-slot:head>
            <x-dashboard.th>Campaign</x-dashboard.th>
            <x-dashboard.th>Agent</x-dashboard.th>
            <x-dashboard.th>Submitted</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th></x-dashboard.th>
        </x-slot:head>
        @foreach ($queue as $participation)
            <tr class="hover:bg-muted/50">
                <x-dashboard.td class="font-medium">{{ $participation->campaign?->title }}</x-dashboard.td>
                <x-dashboard.td>{{ $participation->agent?->email }}</x-dashboard.td>
                <x-dashboard.td>{{ $participation->submitted_at?->format('Y-m-d H:i') }}</x-dashboard.td>
                <x-dashboard.td><x-dashboard.badge :status="$participation->status" /></x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.button :href="route('admin.verifications.show', $participation)" variant="link" size="xs">Review</x-dashboard.button>
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>
    <div class="mt-4">{{ $queue->links() }}</div>
</x-layout.page>
@endsection
