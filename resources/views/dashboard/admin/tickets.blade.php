@extends('layouts.dashboard-admin')

@section('title', 'Support Tickets')

@section('content')
@php
    $status = $status ?? 'open';
    $search = $search ?? '';
    $filterQuery = array_filter(['q' => $search ?: null], fn ($v) => filled($v));
@endphp
<x-layout.page
    title="Support Tickets"
    subtitle="Handle support requests across all statuses."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Support Tickets', null],
    ]"
>
    <x-slot:actions>
        <x-dashboard.button :href="route('admin.tickets.create')" size="sm">Open ticket</x-dashboard.button>
    </x-slot:actions>

    <x-dashboard.card class="mb-4">
        <form method="GET" action="{{ route('admin.tickets') }}" class="flex flex-wrap gap-3 items-end">
            <div class="min-w-[16rem] flex-1">
                <x-dashboard.input name="q" label="Search" :value="$search" placeholder="Subject, username, or ticket ID..." />
            </div>
            <x-dashboard.button type="submit" variant="secondary">Search</x-dashboard.button>
        </form>
    </x-dashboard.card>

    @if ($search === '')
        <x-dashboard.ajax-tabs
            :active="$status"
            :tabs="[
                ['id' => 'open', 'label' => 'Open', 'href' => route('admin.tickets', ['status' => 'open']), 'count' => $counts['open'] ?? 0],
                ['id' => 'pending', 'label' => 'Pending', 'href' => route('admin.tickets', ['status' => 'pending']), 'count' => $counts['pending'] ?? 0],
                ['id' => 'awaiting_user', 'label' => 'Awaiting user', 'href' => route('admin.tickets', ['status' => 'awaiting_user']), 'count' => $counts['awaiting_user'] ?? 0],
                ['id' => 'resolved', 'label' => 'Resolved', 'href' => route('admin.tickets', ['status' => 'resolved']), 'count' => $counts['resolved'] ?? 0],
                ['id' => 'closed', 'label' => 'Closed', 'href' => route('admin.tickets', ['status' => 'closed']), 'count' => $counts['closed'] ?? 0],
            ]"
            class="mb-4"
        />
    @endif

    <div id="dashboard-tab-panel">
        @include('dashboard.admin.tickets._panel')
    </div>
</x-layout.page>
@endsection
