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
                <dt class="text-text-muted">Verified task completions</dt>
                <dd class="font-semibold">{{ $campaign->completed_count }} / {{ $campaign->quantity }}</dd>
            </div>
            @if ($campaign->engagement_metric && in_array($campaign->engagement_metric, ['likes', 'comments'], true))
            <div>
                <dt class="text-text-muted">Observed {{ $campaign->engagement_metric }} on post</dt>
                <dd class="font-semibold">
                    {{ $campaign->last_verified_count ?? '—' }}
                    @if ($campaign->baseline_count !== null)
                        <span class="text-text-muted font-normal">(baseline {{ $campaign->baseline_count }})</span>
                    @endif
                </dd>
                <p class="text-xs text-text-muted mt-1">Observed external metric — not individual agent identity proof.</p>
            </div>
            @endif
            <div>
                <dt class="text-text-muted">Your package price</dt>
                <dd class="font-semibold">₦{{ number_format((float) $campaign->locked_creator_price, 2) }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Agent reward (locked)</dt>
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
