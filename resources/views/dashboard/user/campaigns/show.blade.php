@extends('layouts.dashboard-user')

@section('title', $campaign->title)

@php
    $imageUrl = $campaign->product?->listThumbnailUrl();
@endphp

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
    <div class="grid gap-4 {{ $imageUrl ? 'lg:grid-cols-3' : '' }}">
        @if ($imageUrl)
            <x-dashboard.card :padding="false" class="overflow-hidden">
                <img
                    src="{{ $imageUrl }}"
                    alt="{{ $campaign->product?->title ?? $campaign->title }}"
                    class="aspect-video w-full object-cover"
                    loading="lazy"
                >
                <div class="px-4 py-3">
                    <p class="text-sm font-semibold text-text-primary">{{ $campaign->product?->title ?? $campaign->title }}</p>
                </div>
            </x-dashboard.card>
        @endif

        <x-dashboard.card class="{{ $imageUrl ? 'lg:col-span-2' : '' }}">
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
    </div>
</x-layout.page>
@endsection
