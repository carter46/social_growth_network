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
    $proofIsImage = function (?string $url, ?string $method = null): bool {
        if (! is_string($url) || $url === '') {
            return false;
        }
        if ($method === 'screenshot') {
            return true;
        }

        return (bool) preg_match('/\.(jpe?g|png|webp|gif)(\?|#|$)/i', $url);
    };
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
<div x-data="{ preview: null, rejecting: {{ filled(old('rejection_reason')) ? 'true' : 'false' }}, approving: false, rejectingBusy: false }">
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
            @if ($proofIsImage($participation->proof_url))
                <button type="button" class="block" @click="preview = @js($participation->proof_url)">
                    <img src="{{ $participation->proof_url }}" alt="Screenshot" class="max-h-40 rounded-lg border border-border-default" />
                </button>
            @else
                <p class="break-all">Link: <a href="{{ $participation->proof_url }}" class="text-accent underline" target="_blank" rel="noopener">{{ $participation->proof_url }}</a></p>
            @endif
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
                            @if ($proofIsImage($step->proof_url, $step->evidence_method))
                                <button type="button" class="block" @click="preview = @js($step->proof_url)">
                                    <img src="{{ $step->proof_url }}" alt="Screenshot" class="max-h-40 rounded-lg border border-border-default" />
                                </button>
                            @else
                                <p class="break-all">Link: <a href="{{ $step->proof_url }}" class="text-accent underline" target="_blank" rel="noopener">{{ $step->proof_url }}</a></p>
                            @endif
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
        <form method="POST" action="{{ route('admin.verifications.approve', $participation) }}" @submit="if (approving) { $event.preventDefault(); return } approving = true">
            @csrf
            <x-dashboard.button type="submit" x-bind:disabled="approving">
                <span x-show="!approving">Approve and pay</span>
                <span x-show="approving" x-cloak>Approving…</span>
            </x-dashboard.button>
        </form>
        <x-dashboard.button type="button" variant="secondary" x-show="!rejecting" @click="rejecting = true">Reject</x-dashboard.button>
    </div>

    <form method="POST" action="{{ route('admin.verifications.reject', $participation) }}" x-show="rejecting" x-cloak class="mt-4 max-w-lg space-y-3" @submit="if (rejectingBusy) { $event.preventDefault(); return } rejectingBusy = true">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium" for="rejection_reason">Reason</label>
            <textarea id="rejection_reason" name="rejection_reason" rows="3" required class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">{{ old('rejection_reason') }}</textarea>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-dashboard.button type="submit" variant="danger" x-bind:disabled="rejectingBusy">
                <span x-show="!rejectingBusy">Confirm reject</span>
                <span x-show="rejectingBusy" x-cloak>Rejecting…</span>
            </x-dashboard.button>
            <x-dashboard.button type="button" variant="secondary" @click="rejecting = false" x-bind:disabled="rejectingBusy">Cancel</x-dashboard.button>
        </div>
    </form>

    <div
        x-show="preview"
        x-cloak
        class="fixed inset-0 z-[90] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Screenshot preview"
        @keydown.escape.window="preview = null"
    >
        <div class="absolute inset-0 bg-black/60" @click="preview = null"></div>
        <div class="relative max-h-[90vh] max-w-4xl">
            <button type="button" class="absolute -top-10 right-0 text-sm font-medium text-white" @click="preview = null">Close</button>
            <img :src="preview" alt="Screenshot" class="max-h-[85vh] max-w-full rounded-lg bg-white object-contain" />
        </div>
    </div>
</div>
</x-layout.page>
@endsection
