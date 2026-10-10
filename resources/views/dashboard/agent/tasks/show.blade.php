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

    @if ($campaign)
        <x-dashboard.card class="mb-4 space-y-3">
            @include('dashboard.agent.partials.campaign-tiles', [
                'campaign' => $campaign,
                'reward' => (float) $participation->reward_amount,
                'status' => $participation->status,
            ])
        </x-dashboard.card>
    @endif

    <x-dashboard.card class="space-y-4">
        @if ($participation->rejection_reason)
            <x-dashboard.alert type="warning" title="Rejection reason">{{ $participation->rejection_reason }}</x-dashboard.alert>
        @endif

        @if ($taskMode === 'watch_session' && $participation->status === 'started')
            @php $hasEmbed = ($embed['mode'] ?? '') === 'embed' && ! empty($embed['html']); @endphp
            <div class="space-y-4" x-data="watchTaskModal({
                requiredSeconds: {{ (int) $requiredSeconds }},
                token: @js($watchToken),
                startUrl: @js(route('agent.tasks.start-watch', $participation)),
                claimUrl: @js(route('agent.tasks.claim-watch', $participation)),
                csrf: @js(csrf_token()),
            })">
                <div class="rounded-xl bg-muted/40 px-4 py-3 text-sm text-text-secondary">
                    <p class="font-medium text-text-primary">How to complete this task</p>
                    <ol class="mt-1 list-decimal space-y-0.5 pl-5">
                        <li>Tap <strong>Start watching</strong> to start the timer.</li>
                        @if ($hasEmbed)
                            <li>Press the play button on the video below. It does not play on its own.</li>
                        @else
                            <li>Open the content and keep it playing.</li>
                        @endif
                        <li>Keep this page open until the timer finishes, then tap <strong>Claim reward</strong>.</li>
                    </ol>
                </div>

                @if ($hasEmbed)
                    <div class="overflow-hidden rounded-xl border border-border-default">{!! $embed['html'] !!}</div>
                @elseif (! empty($embed['open_url']))
                    <a href="{{ $embed['open_url'] }}" target="_blank" rel="noopener" class="inline-flex text-sm font-medium text-primary hover:underline">Open the content</a>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    <template x-if="! token">
                        <x-dashboard.button type="button" x-on:click="start()" x-bind:disabled="starting">
                            <span x-text="starting ? 'Starting...' : 'Start watching'">Start watching</span>
                        </x-dashboard.button>
                    </template>
                    <template x-if="token && ! done">
                        <p class="rounded-lg bg-muted/40 px-3 py-2 text-sm font-semibold text-text-primary" aria-live="polite">
                            Time left: <span x-text="display"></span>
                        </p>
                    </template>
                    <template x-if="done">
                        <form method="POST" :action="claimUrl" x-on:submit="claim()">
                            <input type="hidden" name="_token" :value="csrf">
                            <input type="hidden" name="token" :value="token">
                            <x-dashboard.button type="submit" icon="verified" x-bind:disabled="claiming">Claim reward</x-dashboard.button>
                        </form>
                    </template>
                </div>
                <p class="text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
                <p class="text-xs text-text-muted" x-show="token && ! done" x-cloak>Closing or leaving this page before the timer finishes resets your progress.</p>
            </div>
        @elseif ($taskMode === 'subscribe' && $participation->status === 'started')
            <div class="space-y-3">
                <p class="text-sm text-text-secondary">Open the channel on YouTube, subscribe, then submit a screenshot link that shows you are subscribed. An admin reviews every subscriber task before payment.</p>
                @if ($campaign?->target_url)
                    <a href="{{ $campaign->target_url }}" target="_blank" rel="noopener" class="inline-flex rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white">Open channel on YouTube</a>
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
                <p class="text-sm text-text-secondary">Like or comment on the creator's YouTube video, then upload proof. Verification uses <strong>count-change</strong> (aggregate metric increase), not identity of who engaged.</p>
                @if ($campaign?->target_url)
                    <a href="{{ $campaign->target_url }}" target="_blank" rel="noopener" class="inline-flex text-sm font-medium text-accent underline">Open video on YouTube</a>
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
