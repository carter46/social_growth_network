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
    @if ($heldTotals !== null)
        <x-dashboard.card class="mb-4">
            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                <div class="rounded-xl bg-muted/40 px-4 py-3">
                    <dt class="text-text-muted">Currently held for campaigns</dt>
                    <dd class="mt-1 text-base font-semibold text-text-primary">₦{{ number_format((float) $heldTotals['remaining'], 2) }}</dd>
                    <p class="mt-0.5 text-xs text-text-muted">Agent budgets not yet paid out</p>
                </div>
                <div class="rounded-xl bg-muted/40 px-4 py-3">
                    <dt class="text-text-muted">Paid to agents from holds</dt>
                    <dd class="mt-1 font-medium text-text-primary">₦{{ number_format((float) $heldTotals['paid'], 2) }}</dd>
                </div>
                <div class="rounded-xl bg-muted/40 px-4 py-3">
                    <dt class="text-text-muted">Campaigns with a held budget</dt>
                    <dd class="mt-1 font-medium text-text-primary">{{ number_format($heldTotals['count']) }}</dd>
                </div>
            </dl>
        </x-dashboard.card>
    @endif

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
                <x-dashboard.td>
                    <x-dashboard.badge :status="$campaign->status" />
                    @if (\App\Services\Campaigns\CampaignBudgetService::isUnfunded($campaign))
                        <x-dashboard.badge variant="warning" class="ml-1">Budget not held</x-dashboard.badge>
                    @endif
                </x-dashboard.td>
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
