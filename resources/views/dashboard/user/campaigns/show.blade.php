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
    <x-dashboard.card>
        <dl class="grid gap-3 text-sm sm:grid-cols-3">
            <div class="rounded-xl bg-muted/40 px-4 py-3">
                <dt class="text-text-muted">Status</dt>
                <dd class="mt-1"><x-dashboard.badge :status="$campaign->status">{{ $campaign->statusLabel() }}</x-dashboard.badge></dd>
            </div>
            <div class="rounded-xl bg-muted/40 px-4 py-3">
                <dt class="text-text-muted">Verified tasks</dt>
                <dd class="mt-1 text-base font-semibold text-text-primary">{{ number_format($campaign->completed_count) }} / {{ number_format($campaign->quantity) }}</dd>
            </div>
            <div class="rounded-xl bg-muted/40 px-4 py-3">
                <dt class="text-text-muted">Campaign cost</dt>
                <dd class="mt-1 text-base font-semibold text-text-primary">₦{{ number_format((float) $campaign->totalCost(), 2) }}</dd>
            </div>
        </dl>
    </x-dashboard.card>
</x-layout.page>
@endsection
