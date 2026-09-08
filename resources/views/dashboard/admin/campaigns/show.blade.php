@extends('layouts.dashboard-admin')

@section('title', 'Campaign')

@section('content')
<x-layout.page
    title="{{ $campaign->title }}"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Campaigns', route('admin.campaigns')],
        [$campaign->title, null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif

    <x-dashboard.card class="mb-4 space-y-4">
        <dl class="grid gap-3 sm:grid-cols-4 text-sm">
            <div>
                <dt class="text-text-muted">Creator</dt>
                <dd>{{ $campaign->creator?->email }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Progress</dt>
                <dd>{{ $campaign->completed_count }}/{{ $campaign->quantity }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Agent reward</dt>
                <dd>₦{{ number_format((float) $campaign->locked_agent_reward, 2) }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Status</dt>
                <dd><x-dashboard.badge :status="$campaign->status" /></dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('admin.campaigns.status', $campaign) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-medium text-text-secondary" for="status">Update status</label>
                <select id="status" name="status" class="rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">
                    @foreach (\App\Models\Campaign::STATUSES as $status)
                        <option value="{{ $status }}" @selected($campaign->status === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <x-dashboard.button type="submit" size="sm">Save</x-dashboard.button>
        </form>
    </x-dashboard.card>

    <h2 class="mb-2 text-sm font-semibold">Participations</h2>
    <x-dashboard.table :empty="$campaign->participations->isEmpty()" empty-title="None yet" empty-icon="users" striped>
        <x-slot:head>
            <x-dashboard.th>Agent</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th></x-dashboard.th>
        </x-slot:head>
        @foreach ($campaign->participations as $participation)
            <tr>
                <x-dashboard.td>{{ $participation->agent?->email }}</x-dashboard.td>
                <x-dashboard.td><x-dashboard.badge :status="$participation->status" /></x-dashboard.td>
                <x-dashboard.td>
                    @if (in_array($participation->status, ['submitted', 'under_review'], true))
                        <x-dashboard.button :href="route('admin.verifications.show', $participation)" variant="link" size="xs">Review</x-dashboard.button>
                    @endif
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>
</x-layout.page>
@endsection
