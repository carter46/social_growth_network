@extends('layouts.dashboard-user')

@section('title', $campaign->title)

@section('content')
<x-layout.page
    title="{{ $campaign->title }}"
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['Campaigns', route('dashboard.campaigns')],
        [$campaign->title, null],
    ]"
>
    <x-dashboard.card class="mb-4 space-y-3">
        <dl class="grid gap-3 sm:grid-cols-4 text-sm">
            <div>
                <dt class="text-text-muted">Status</dt>
                <dd><x-dashboard.badge :status="$campaign->status" /></dd>
            </div>
            <div>
                <dt class="text-text-muted">Progress</dt>
                <dd class="font-semibold">{{ $campaign->completed_count }} / {{ $campaign->quantity }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Your package price</dt>
                <dd class="font-semibold">₦{{ number_format((float) $campaign->locked_creator_price, 2) }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Agent reward</dt>
                <dd class="font-semibold">₦{{ number_format((float) $campaign->locked_agent_reward, 2) }}</dd>
            </div>
        </dl>
    </x-dashboard.card>

    <h2 class="mb-2 text-sm font-semibold text-text-primary">Participations</h2>
    <x-dashboard.table
        :empty="$campaign->participations->isEmpty()"
        empty-title="No agents yet"
        empty-description="Agents will appear here when they start your campaign."
        empty-icon="users"
        striped
    >
        <x-slot:head>
            <x-dashboard.th>Agent</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Submitted</x-dashboard.th>
        </x-slot:head>
        @foreach ($campaign->participations as $participation)
            <tr class="hover:bg-muted/50">
                <x-dashboard.td>{{ $participation->agent?->name ?? '—' }}</x-dashboard.td>
                <x-dashboard.td><x-dashboard.badge :status="$participation->status" /></x-dashboard.td>
                <x-dashboard.td>{{ $participation->submitted_at?->format('Y-m-d H:i') ?? '—' }}</x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>
</x-layout.page>
@endsection
