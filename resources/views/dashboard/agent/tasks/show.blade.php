@extends('layouts.dashboard-agent')

@section('title', 'Task')

@section('content')
@php
    $campaign = $participation->campaign;
    $watchToken = session('watch_token');
    $requiredSeconds = max(60, (int) ($campaign?->estimated_minutes ?: 1) * 60);
@endphp
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

        @if ($taskMode === 'watch_session' && $participation->status === 'started')
            <div class="space-y-3" x-data="watchTaskModal({
                requiredSeconds: {{ (int) $requiredSeconds }},
                token: @js($watchToken),
                claimUrl: @js(route('agent.tasks.claim-watch', $participation)),
                csrf: @js(csrf_token()),
            })">
                <p class="text-sm text-text-secondary">{{ $embed['note'] ?? 'Complete the required watch session, then claim your reward. This verifies a TaskPulse task completion — not a guaranteed platform view or watch-hour credit.' }}</p>

                @if (($embed['mode'] ?? '') === 'embed' && !empty($embed['html']))
                    <div class="overflow-hidden rounded-xl border border-border-default">{!! $embed['html'] !!}</div>
                @endif

                @if (!empty($embed['open_url']))
                    <a href="{{ $embed['open_url'] }}" target="_blank" rel="noopener" class="inline-flex text-sm font-medium text-accent underline">Open on platform</a>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    @if (! $watchToken)
                        <form method="POST" action="{{ route('agent.tasks.start-watch', $participation) }}">
                            @csrf
                            <x-dashboard.button type="submit">Start watching</x-dashboard.button>
                        </form>
                    @else
                        <p class="text-sm font-semibold" x-text="done ? 'Session complete' : ('Time left: ' + display)"></p>
                        <template x-if="done">
                            <form method="POST" :action="claimUrl">
                                <input type="hidden" name="_token" :value="csrf">
                                <input type="hidden" name="token" :value="token">
                                <x-dashboard.button type="submit">Claim reward</x-dashboard.button>
                            </form>
                        </template>
                        <p class="text-xs text-text-muted">Closing or leaving early resets progress. Keep this page open.</p>
                    @endif
                </div>
            </div>
        @elseif ($taskMode === 'x_action' && $participation->status === 'started')
            <div class="space-y-3">
                <p class="text-sm text-text-secondary">Open the post on X and complete the required viewing action. Embedded posts do not add to X view counts — do not treat a timer as proof of an X view.</p>
                @if ($campaign?->target_url)
                    <a href="{{ $campaign->target_url }}" target="_blank" rel="noopener" class="inline-flex rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white">Open on X</a>
                @endif
                <form method="POST" action="{{ route('agent.tasks.submit', $participation) }}" class="space-y-4">
                    @csrf
                    <x-dashboard.input name="proof_url" label="Screenshot / proof URL" :value="old('proof_url', $participation->proof_url)" placeholder="https://..." required />
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-secondary" for="proof_notes">Notes</label>
                        <textarea id="proof_notes" name="proof_notes" rows="3" class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">{{ old('proof_notes', $participation->proof_notes) }}</textarea>
                    </div>
                    <x-dashboard.button type="submit">Submit for verification</x-dashboard.button>
                </form>
            </div>
        @elseif ($taskMode === 'count_change_proof' && $participation->status === 'started')
            <div class="space-y-3">
                <p class="text-sm text-text-secondary">Complete the like or comment on the creator’s post, then upload proof. Verification uses <strong>count-change</strong> (aggregate metric increase), not identity of who engaged.</p>
                @if ($campaign?->target_url)
                    <a href="{{ $campaign->target_url }}" target="_blank" rel="noopener" class="inline-flex text-sm font-medium text-accent underline">Open post</a>
                @endif
                <form method="POST" action="{{ route('agent.tasks.submit', $participation) }}" class="space-y-4">
                    @csrf
                    <x-dashboard.input name="proof_url" label="Screenshot proof URL" :value="old('proof_url', $participation->proof_url)" placeholder="https://..." required />
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-secondary" for="proof_notes">Notes</label>
                        <textarea id="proof_notes" name="proof_notes" rows="3" class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">{{ old('proof_notes', $participation->proof_notes) }}</textarea>
                    </div>
                    <x-dashboard.button type="submit">Submit for count-change verification</x-dashboard.button>
                </form>
            </div>
        @elseif ($participation->status === 'started')
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
                @if ($participation->status === 'verifying')
                    <p>Count-change verification in progress…</p>
                @endif
            </div>
        @endif
    </x-dashboard.card>
</x-layout.page>
@endsection
