@extends('layouts.dashboard-admin')

@section('title', 'Review submission')

@section('content')
@php
    $campaign = $participation->campaign;
    $usesSteps = (bool) $campaign?->usesStepFlow();
    $stepBadge = [
        'locked' => 'neutral',
        'active' => 'info',
        'done' => 'info',
        'checking' => 'verifying',
        'verified' => 'success',
        'needs_review' => 'pending',
        'accepted' => 'success',
        'rejected' => 'danger',
        'expired' => 'expired',
    ];
@endphp
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
        <p>
            <span class="text-text-muted">Campaign:</span> {{ $campaign?->title }}
            @if ($usesSteps)
                <x-dashboard.badge status="info">{{ $steps->count() }} tasks</x-dashboard.badge>
            @endif
        </p>
        <p><span class="text-text-muted">Agent:</span> {{ $participation->agent?->email }}</p>
        <p><span class="text-text-muted">Reward:</span> {{ money($participation->reward_amount, 2, 4) }}</p>
        <p><span class="text-text-muted">Status:</span> <x-dashboard.badge :status="$participation->status" /></p>
        @if ($usesSteps)
            <p>
                <span class="text-text-muted">Agent YouTube channel:</span>
                @if ($identity)
                    <a href="{{ $identity->channel_url ?: 'https://www.youtube.com/channel/'.$identity->channel_id }}" class="text-accent underline" target="_blank" rel="noopener">{{ $identity->title ?: $identity->channel_id }}</a>
                    <x-dashboard.badge :status="$identity->isVerified() ? 'verified' : 'pending'">{{ $identity->statusLabel() }}</x-dashboard.badge>
                @else
                    <span class="text-danger">No linked channel</span>
                @endif
            </p>
        @endif
        @if ($participation->proof_url)
            <p>Proof: <a href="{{ $participation->proof_url }}" class="text-accent underline" target="_blank" rel="noopener">{{ $participation->proof_url }}</a></p>
        @endif
        @if ($participation->proof_notes)
            <p>{{ $participation->proof_notes }}</p>
        @endif
    </x-dashboard.card>

    @if ($usesSteps)
        <x-dashboard.card class="mb-4 space-y-3 text-sm">
            <p class="font-medium text-text-primary">Steps</p>
            @if ($commentCheck)
                <x-dashboard.alert :type="($commentCheck['state'] ?? '') === 'ok' ? 'success' : 'warning'">
                    Live comment check: {{ $commentCheck['note'] ?? '' }}
                </x-dashboard.alert>
            @endif
            <ol class="space-y-3">
                @foreach ($steps as $step)
                    <li class="rounded-lg border border-border-default px-3 py-2 space-y-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium text-text-primary">{{ $step->position }}. {{ $step->taskEnum()?->label() ?? $step->task }}</span>
                            <x-dashboard.badge :status="$stepBadge[$step->status] ?? 'neutral'">{{ $step->statusLabel() }}</x-dashboard.badge>
                        </div>
                        @if ($step->evidence_method)
                            <p class="text-text-muted">Evidence: {{ str_replace('_', ' ', $step->evidence_method) }}</p>
                        @endif
                        @if ($step->evidence_method === 'timer')
                            <p class="text-text-muted">Timer completed, viewing not verified ({{ (int) ($step->proof_meta['seconds'] ?? 0) }} seconds).</p>
                        @endif
                        @if ($step->proof_url)
                            <p class="break-all">Link: <a href="{{ $step->proof_url }}" class="text-accent underline" target="_blank" rel="noopener">{{ $step->proof_url }}</a></p>
                        @endif
                        @if (! empty($step->proof_meta['check_note']))
                            <p class="text-text-muted">Last check: {{ $step->proof_meta['check_note'] }}</p>
                        @endif
                        @if ($step->proof_notes)
                            <p class="text-text-secondary">{{ $step->proof_notes }}</p>
                        @endif
                        @if ($step->rejection_reason)
                            <p class="text-danger">{{ $step->rejection_reason }}</p>
                        @endif
                        @if ($step->checked_at)
                            <p class="text-xs text-text-muted">Checked {{ $step->checked_at->diffForHumans() }}@if ($step->attempts) ({{ $step->attempts }} {{ \Illuminate\Support\Str::plural('check', (int) $step->attempts) }})@endif</p>
                        @endif
                        @if ($step->task === 'comment' && $step->proof_url)
                            <form method="POST" action="{{ route('admin.verifications.check-comment', $participation) }}">
                                @csrf
                                <x-dashboard.button type="submit" variant="secondary" size="xs">Check the comment now</x-dashboard.button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ol>
            @if (filled($campaign?->comment_instructions))
                <div class="rounded-lg bg-muted/30 px-3 py-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">Creator's comment instructions</p>
                    <p class="mt-1 whitespace-pre-line break-words">{{ $campaign->comment_instructions }}</p>
                </div>
            @endif
        </x-dashboard.card>
    @endif

    <div class="flex flex-wrap gap-3">
        <form method="POST" action="{{ route('admin.verifications.approve', $participation) }}">
            @csrf
            <x-dashboard.button type="submit">Approve and pay</x-dashboard.button>
        </form>
    </div>

    <x-dashboard.card class="mt-4">
        <form method="POST" action="{{ route('admin.verifications.reject', $participation) }}" class="space-y-3">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium" for="rejection_reason">Rejection reason</label>
                <textarea id="rejection_reason" name="rejection_reason" rows="3" required class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">{{ old('rejection_reason') }}</textarea>
                @if ($usesSteps)
                    <p class="mt-1 text-xs text-text-muted">Rejecting rejects the whole task. The agent is not paid and the slot opens again.</p>
                @endif
            </div>
            <x-dashboard.button type="submit" variant="secondary">Reject</x-dashboard.button>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
