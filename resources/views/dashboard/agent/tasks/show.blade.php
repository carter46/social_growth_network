@extends('layouts.dashboard-agent')

@section('title', 'Task')

@section('content')
@php
    $campaign = $participation->campaign;
    $watchToken = session('watch_token');
    $requiredSeconds = (int) ($requiredSeconds ?? max(60, (int) ($campaign?->estimated_minutes ?: 1) * 60));
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

    @if ($participation->status === 'expired')
        <x-dashboard.alert type="danger" class="mb-4" title="Time ran out">
            This task was not finished in time, so it was cancelled and you were not paid.
        </x-dashboard.alert>
        <x-dashboard.button :href="$campaign ? route('agent.marketplace.show', $campaign) : route('agent.marketplace')">Done</x-dashboard.button>
    @endif

    @if ($participation->status !== 'expired' && $participation->status === 'started')
        <div
            class="mb-4"
            x-data="taskCountdown({
                secondsRemaining: @js($secondsRemaining),
                totalSeconds: @js($totalSeconds),
                heartbeatUrl: @js(route('agent.tasks.heartbeat', $participation)),
                csrf: @js(csrf_token()),
            })"
        >
            @if ($secondsRemaining !== null)
                <div
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-4 py-3"
                    :class="warning ? 'border-danger/40 bg-danger/5' : 'border-border-default bg-muted/30'"
                    aria-live="polite"
                >
                    <p class="text-sm text-text-secondary">Time left to finish this task</p>
                    <p class="text-lg font-semibold" :class="warning ? 'text-danger' : 'text-text-primary'" x-text="display">{{ gmdate($secondsRemaining >= 3600 ? 'G:i:s' : 'i:s', $secondsRemaining) }}</p>
                    <p class="w-full text-xs text-text-muted" x-show="warning" x-cloak>Less than 20% of your time is left. If time runs out, the task is cancelled and you are not paid.</p>
                </div>
            @endif
        </div>
    @endif

    @if (in_array($participation->status, ['approved', 'paid'], true))
        <x-dashboard.card class="mb-4 space-y-4" data-task-complete>
            <x-dashboard.alert type="success" title="Thank you for completing the task">
                {{ $participation->status === 'paid'
                    ? 'Your reward has been paid to your wallet.'
                    : 'Your reward is approved and will be paid shortly.' }}
            </x-dashboard.alert>
            <div class="flex flex-wrap gap-3">
                <x-dashboard.button :href="route('agent')" icon="home">Back to dashboard</x-dashboard.button>
                <x-dashboard.button :href="route('agent.marketplace')" variant="secondary">Find more tasks</x-dashboard.button>
            </div>
        </x-dashboard.card>
    @endif

    @if ($participation->status !== 'expired' && $campaign && $taskMode !== 'steps')
        <x-dashboard.card class="mb-4 space-y-3">
            @include('dashboard.agent.partials.campaign-tiles', [
                'campaign' => $campaign,
                'reward' => (float) $participation->reward_amount,
                'status' => $participation->status,
                'embed' => in_array($participation->status, ['started'], true) ? null : ($embed ?? null),
                'briefingTasks' => ($taskMode === 'steps' && isset($steps) && $steps->isNotEmpty())
                    ? $steps->map(fn ($step) => $step->taskEnum())->filter()->values()->all()
                    : $campaign->taskProfile()->tasks(),
            ])
        </x-dashboard.card>
    @endif

    @if ($participation->status !== 'expired')
    <x-dashboard.card class="space-y-4">
        @if ($participation->rejection_reason)
            <x-dashboard.alert type="warning" title="Rejection reason">{{ $participation->rejection_reason }}</x-dashboard.alert>
        @endif

        @if ($taskMode === 'steps')
            @include('dashboard.agent.tasks._steps', [
                'campaign' => $campaign,
                'steps' => $steps,
                'embed' => $embed,
                'watchToken' => $watchToken,
                'requiredSeconds' => $requiredSeconds,
            ])
        @elseif ($taskMode === 'watch_session' && $participation->status === 'started')
            @php $hasEmbed = ($embed['mode'] ?? '') === 'embed' && ! empty($embed['html']); @endphp
            <div class="space-y-4" x-data="watchTaskModal({
                requiredSeconds: {{ (int) $requiredSeconds }},
                token: @js($watchToken),
                startUrl: @js(route('agent.tasks.start-watch', $participation)),
                claimUrl: @js(route('agent.tasks.claim-watch', $participation)),
                csrf: @js(csrf_token()),
            })">
                <p class="text-sm text-text-secondary" x-show="! running && ! done">Press play on the video. The timer starts when it plays.</p>

                @if ($hasEmbed)
                    <div class="overflow-hidden rounded-xl border border-border-default">{!! $embed['html'] !!}</div>
                @elseif (! empty($embed['open_url']))
                    <a href="{{ $embed['open_url'] }}" target="_blank" rel="noopener" class="inline-flex text-sm font-medium text-primary hover:underline" x-on:click="start()">Open the content</a>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    <template x-if="token && ! done">
                        <p class="rounded-lg bg-muted/40 px-3 py-2 text-sm font-semibold text-text-primary" aria-live="polite">
                            Watch time left: <span x-text="display"></span>
                        </p>
                    </template>
                    <template x-if="done">
                        <form method="POST" :action="claimUrl" data-ajax-form @submit.prevent="claim($event)">
                            <input type="hidden" name="_token" :value="csrf">
                            <input type="hidden" name="token" :value="token">
                            <x-dashboard.button type="submit" icon="verified" x-bind:disabled="claiming">Claim reward</x-dashboard.button>
                            <p class="mt-2 text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
                        </form>
                    </template>
                </div>
                <p class="text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
                <p class="text-xs text-text-muted" x-show="token && ! done" x-cloak>Keep this page open while the video plays.</p>
            </div>
        @elseif ($taskMode === 'subscribe' && $participation->status === 'started')
            <div class="space-y-3">
                <p class="text-sm text-text-secondary">Open the channel on YouTube, subscribe, then upload a screenshot that shows you are subscribed. An admin reviews every subscriber task before payment.</p>
                @if ($campaign?->target_url)
                    <a href="{{ $campaign->target_url }}" target="_blank" rel="noopener" class="inline-flex rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white">Open channel on YouTube</a>
                @endif
                <form method="POST" action="{{ route('agent.tasks.submit', $participation) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-text-secondary" for="subscribe-proof-file">Screenshot</label>
                        <input id="subscribe-proof-file" type="file" name="proof" accept="image/jpeg,image/png,image/webp" required class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-elevated file:px-3 file:py-2 file:text-sm file:text-text-primary" />
                        <p class="text-xs text-text-muted">JPG, PNG, or WEBP, up to 2 MB. Images over 1 MB are reduced so they stay clear and under 1 MB.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-secondary" for="proof_notes">Notes</label>
                        <textarea id="proof_notes" name="proof_notes" rows="3" class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">{{ old('proof_notes', $participation->proof_notes) }}</textarea>
                    </div>
                    <x-dashboard.button type="submit">Submit for verification</x-dashboard.button>
                </form>
            </div>
        @elseif ($taskMode === 'count_change_proof' && $participation->status === 'started')
            <div class="space-y-3">
                <p class="text-sm text-text-secondary">Like or comment on the creator's YouTube video, then upload a screenshot.</p>
                @if ($campaign?->target_url)
                    <a href="{{ $campaign->target_url }}" target="_blank" rel="noopener" class="inline-flex text-sm font-medium text-accent underline">Open video on YouTube</a>
                @endif
                <form method="POST" action="{{ route('agent.tasks.submit', $participation) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-text-secondary" for="count-proof-file">Screenshot</label>
                        <input id="count-proof-file" type="file" name="proof" accept="image/jpeg,image/png,image/webp" required class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-elevated file:px-3 file:py-2 file:text-sm file:text-text-primary" />
                        <p class="text-xs text-text-muted">JPG, PNG, or WEBP, up to 2 MB. Images over 1 MB are reduced so they stay clear and under 1 MB.</p>
                    </div>
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
    @endif
</x-layout.page>
@endsection
