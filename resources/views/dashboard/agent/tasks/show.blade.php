@extends('layouts.dashboard-agent')

@section('title', 'Task')

@section('content')
@php $campaign = $participation->campaign; @endphp
<x-layout.page
    title="{{ $campaign?->title ?? 'Task' }}"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Tasks', route('agent.tasks.active')],
        ['Detail', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <x-dashboard.card class="space-y-4">
        <dl class="grid gap-3 sm:grid-cols-3 text-sm">
            <div>
                <dt class="text-text-muted">Status</dt>
                <dd><x-dashboard.badge :status="$participation->status" /></dd>
            </div>
            <div>
                <dt class="text-text-muted">Reward</dt>
                <dd class="font-semibold">₦{{ number_format((float) $participation->reward_amount, 2) }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Target</dt>
                <dd>
                    @if ($campaign?->target_url)
                        <a href="{{ $campaign->target_url }}" class="text-accent underline" target="_blank" rel="noopener">Open link</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
        </dl>

        @if ($participation->rejection_reason)
            <x-dashboard.alert type="warning" title="Rejection reason">{{ $participation->rejection_reason }}</x-dashboard.alert>
        @endif

        @if (in_array($participation->status, ['started', 'rejected'], true))
            <form method="POST" action="{{ route('agent.tasks.submit', $participation) }}" class="space-y-4">
                @csrf
                <x-dashboard.input name="proof_url" label="Proof URL" :value="old('proof_url', $participation->proof_url)" placeholder="https://..." />
                <div>
                    <label class="mb-1 block text-sm font-medium text-text-secondary" for="proof_notes">Notes</label>
                    <textarea id="proof_notes" name="proof_notes" rows="4" class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm text-text-primary">{{ old('proof_notes', $participation->proof_notes) }}</textarea>
                </div>
                <x-dashboard.button type="submit">Submit for verification</x-dashboard.button>
            </form>
        @else
            <div class="text-sm text-text-secondary space-y-1">
                @if ($participation->proof_url)
                    <p>Proof: <a href="{{ $participation->proof_url }}" class="underline text-accent" target="_blank" rel="noopener">{{ $participation->proof_url }}</a></p>
                @endif
                @if ($participation->proof_notes)
                    <p>{{ $participation->proof_notes }}</p>
                @endif
            </div>
        @endif
    </x-dashboard.card>
</x-layout.page>
@endsection
