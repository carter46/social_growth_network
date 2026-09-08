@extends('layouts.dashboard-admin')

@section('title', 'Review submission')

@section('content')
@php $campaign = $participation->campaign; @endphp
<x-layout.page
    title="Review submission"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Verification', route('admin.verifications')],
        ['Review', null],
    ]"
>
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <x-dashboard.card class="mb-4 space-y-3 text-sm">
        <p><span class="text-text-muted">Campaign:</span> {{ $campaign?->title }}</p>
        <p><span class="text-text-muted">Agent:</span> {{ $participation->agent?->email }}</p>
        <p><span class="text-text-muted">Reward:</span> ₦{{ number_format((float) $participation->reward_amount, 2) }}</p>
        @if ($participation->proof_url)
            <p>Proof: <a href="{{ $participation->proof_url }}" class="text-accent underline" target="_blank" rel="noopener">{{ $participation->proof_url }}</a></p>
        @endif
        @if ($participation->proof_notes)
            <p>{{ $participation->proof_notes }}</p>
        @endif
    </x-dashboard.card>

    <div class="flex flex-wrap gap-3">
        <form method="POST" action="{{ route('admin.verifications.approve', $participation) }}">
            @csrf
            <x-dashboard.button type="submit">Approve & pay</x-dashboard.button>
        </form>
    </div>

    <x-dashboard.card class="mt-4">
        <form method="POST" action="{{ route('admin.verifications.reject', $participation) }}" class="space-y-3">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium" for="rejection_reason">Rejection reason</label>
                <textarea id="rejection_reason" name="rejection_reason" rows="3" required class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">{{ old('rejection_reason') }}</textarea>
            </div>
            <x-dashboard.button type="submit" variant="secondary">Reject</x-dashboard.button>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
