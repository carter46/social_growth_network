@extends('layouts.dashboard-agent')

@section('title', 'Priority task')

@section('content')
@php
    use App\Models\AgentSocialFollowSubmission;

    $status = $submission?->status;
    $proofs = $submission?->proofs ?? collect();
    $timed = $submission?->isTimedRunActive() ?? false;
    $allDone = $proofs->isNotEmpty() && $proofs->every(fn ($p) => $p->isComplete());
    $activeProof = $timed ? $proofs->first(fn ($p) => ! $p->isComplete()) : null;
    $needsStart = in_array($status, [
        AgentSocialFollowSubmission::STATUS_PENDING,
        AgentSocialFollowSubmission::STATUS_EXPIRED,
        AgentSocialFollowSubmission::STATUS_REJECTED,
        null,
    ], true) && ! $timed;
@endphp
<x-layout.page
    title="Priority task"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Marketplace', route('agent.marketplace')],
        ['Priority task', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    @if ($status === AgentSocialFollowSubmission::STATUS_EXPIRED)
        <x-dashboard.alert type="danger" class="mb-4" title="Time ran out">
            This task was not finished in time. Start again to continue.
        </x-dashboard.alert>
    @endif

    @if ($status === AgentSocialFollowSubmission::STATUS_APPROVED)
        <x-dashboard.card class="mb-4 space-y-4">
            <x-dashboard.alert type="success" title="Task approved">
                You can start earning on the Task Marketplace.
            </x-dashboard.alert>
            <x-dashboard.button :href="route('agent.marketplace')">Find more tasks</x-dashboard.button>
        </x-dashboard.card>
    @endif

    @if ($status === AgentSocialFollowSubmission::STATUS_SUBMITTED)
        <x-dashboard.alert type="info" class="mb-4">Task submitted. We are checking it now.</x-dashboard.alert>
        <div class="space-y-3 mb-4">
            @foreach ($proofs as $proof)
                <div class="rounded-xl border border-border-default px-4 py-3 flex flex-wrap items-center gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border-default bg-elevated">
                        @if ($proof->logoUrl())
                            <img src="{{ $proof->logoUrl() }}" alt="" class="h-full w-full rounded-full object-cover">
                        @else
                            <span class="text-sm font-bold text-text-primary">{{ mb_strtoupper(mb_substr($proof->platform, 0, 1)) }}</span>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-text-primary">{{ $proof->shortLabel() }}</p>
                    </div>
                    <x-dashboard.badge status="pending">Under review</x-dashboard.badge>
                    @if ($proof->proof_url)
                        <a href="{{ $proof->proof_url }}" target="_blank" rel="noopener" class="inline-block">
                            <img src="{{ $proof->proof_url }}" alt="" class="max-h-16 rounded-lg border border-border-default">
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
        <x-dashboard.button :href="route('agent.marketplace')">Done</x-dashboard.button>
    @endif

    @if ($status === AgentSocialFollowSubmission::STATUS_REJECTED)
        <x-dashboard.alert type="warning" class="mb-4" title="Rejection reason">
            {{ $submission->rejection_reason ?: 'Please upload new screenshots and submit again.' }}
        </x-dashboard.alert>
    @endif

    @if ($timed && $secondsRemaining !== null)
        <div
            class="mb-4"
            x-data="taskCountdown({
                secondsRemaining: @js($secondsRemaining),
                totalSeconds: @js($totalSeconds),
                heartbeatUrl: @js(route('agent.social-follow.heartbeat')),
                csrf: @js(csrf_token()),
            })"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-4 py-3"
                :class="warning ? 'border-danger/40 bg-danger/5' : 'border-border-default bg-muted/30'"
                aria-live="polite"
            >
                <p class="text-sm text-text-secondary">Time left to finish this task</p>
                <p class="text-lg font-semibold" :class="warning ? 'text-danger' : 'text-text-primary'" x-text="display">{{ gmdate($secondsRemaining >= 3600 ? 'G:i:s' : 'i:s', $secondsRemaining) }}</p>
                <p class="w-full text-xs text-text-muted" x-show="warning" x-cloak>Less than 20% of your time is left. If time runs out, you will need to start again.</p>
            </div>
        </div>
    @endif

    {{-- Briefing + start (before timer) --}}
    @if ($needsStart)
        <x-dashboard.card class="mb-4 space-y-4">
            <div class="flex flex-wrap gap-2">
                <span class="px-2 py-0.5 bg-surface-muted rounded text-xs font-medium text-text-secondary">{{ __('Priority') }}</span>
                <span class="px-2 py-0.5 bg-surface-muted rounded text-xs font-medium text-text-secondary">
                    {{ __(':time to complete', ['time' => trans_choice(':count min|:count mins', (int) ($completionMinutes ?? 3))]) }}
                </span>
                <span class="px-2 py-0.5 bg-surface-muted rounded text-xs font-medium text-text-secondary">{{ __('Reward: Required') }}</span>
            </div>
            <p class="text-sm text-text-secondary">{{ __('Complete these tasks to start earning real money.') }}</p>

            @if ($proofs->isNotEmpty())
                <ol class="flex flex-wrap items-center gap-2" aria-label="Steps">
                    @foreach ($proofs as $timelineProof)
                        <li class="flex items-center gap-2 text-sm">
                            @if (! $loop->first)
                                <span class="text-text-muted" aria-hidden="true">→</span>
                            @endif
                            <span class="rounded-full px-3 py-1 font-medium text-text-muted">{{ $timelineProof->shortLabel() }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif

            <form method="POST" action="{{ route('agent.social-follow.start') }}" data-ajax-form x-data="taskAction" @submit.prevent="submit($event)">
                @csrf
                <x-dashboard.button type="submit" icon="plus" x-bind:disabled="busy">
                    <span x-text="busy ? 'Starting...' : 'Start task'">Start task</span>
                </x-dashboard.button>
                <p class="mt-2 text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
            </form>
        </x-dashboard.card>
    @endif

    {{-- Multi-step run --}}
    @if ($timed)
        <div class="space-y-4">
            @if ($proofs->isNotEmpty())
                <ol class="flex flex-wrap items-center gap-2" aria-label="Steps">
                    @foreach ($proofs as $timelineProof)
                        <li class="flex items-center gap-2 text-sm">
                            @if (! $loop->first)
                                <span class="text-text-muted" aria-hidden="true">→</span>
                            @endif
                            <span @class([
                                'rounded-full px-3 py-1 font-medium',
                                'bg-primary/10 text-primary' => $activeProof && (int) $activeProof->id === (int) $timelineProof->id,
                                'text-text-primary' => $timelineProof->isComplete(),
                                'text-text-muted' => ! $timelineProof->isComplete() && (! $activeProof || (int) $activeProof->id !== (int) $timelineProof->id),
                            ])>{{ $timelineProof->shortLabel() }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($activeProof)
                @php $step = $activeProof; @endphp
                <x-dashboard.card class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border-default bg-elevated">
                                @if ($step->logoUrl())
                                    <img src="{{ $step->logoUrl() }}" alt="" class="h-full w-full rounded-full object-cover">
                                @else
                                    <span class="text-base font-bold text-text-primary">{{ mb_strtoupper(mb_substr($step->platform, 0, 1)) }}</span>
                                @endif
                            </span>
                            <p class="text-sm font-semibold text-text-primary">{{ $step->shortLabel() }}</p>
                        </div>
                        <x-dashboard.badge status="info">In progress</x-dashboard.badge>
                    </div>

                    <p class="text-sm text-text-secondary">{{ $step->instruction() }}</p>

                    <div
                        class="space-y-4"
                        x-data="{ ready: false }"
                    >
                        <x-dashboard.button :href="$step->url" target="_blank" rel="noopener noreferrer" size="sm">
                            {{ $step->openButtonLabel() }}
                        </x-dashboard.button>

                        <div x-show="!ready">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-border-default bg-elevated px-3.5 py-2 text-sm font-semibold text-text-primary hover:bg-muted/40 transition-colors"
                                x-on:click="ready = true"
                            >
                                Done
                            </button>
                        </div>

                        <div x-show="ready" x-cloak>
                            <form
                                method="POST"
                                action="{{ route('agent.social-follow.proof', $step) }}"
                                enctype="multipart/form-data"
                                class="space-y-3"
                                data-ajax-form
                                x-data="taskAction"
                                @submit.prevent="submit($event)"
                            >
                                @csrf
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-medium text-text-secondary" for="proof-{{ $step->id }}">Screenshot</label>
                                    <input
                                        id="proof-{{ $step->id }}"
                                        type="file"
                                        name="proof"
                                        accept="image/jpeg,image/png,image/webp"
                                        required
                                        class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-elevated file:px-3 file:py-2 file:text-sm file:text-text-primary"
                                    />
                                    <p class="text-xs text-text-muted">JPG, PNG, or WEBP, up to 2 MB.</p>
                                </div>
                                <x-dashboard.button type="submit" size="sm" x-bind:disabled="busy">
                                    <span x-text="busy ? 'Saving...' : @js($proofs->filter(fn ($p) => ! $p->isComplete())->count() > 1 ? 'Next' : 'Finish step')">Next</span>
                                </x-dashboard.button>
                                <p class="text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
                            </form>
                        </div>
                    </div>
                </x-dashboard.card>
            @elseif ($allDone)
                <x-dashboard.card class="space-y-4">
                    <p class="text-sm text-text-secondary">Every step is done. Review your screenshots, then submit the task.</p>
                    <ol class="space-y-3">
                        @foreach ($proofs as $proof)
                            <li class="rounded-xl border border-border-default px-4 py-3 space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border-default bg-elevated">
                                            @if ($proof->logoUrl())
                                                <img src="{{ $proof->logoUrl() }}" alt="" class="h-full w-full rounded-full object-cover">
                                            @else
                                                <span class="text-sm font-bold text-text-primary">{{ mb_strtoupper(mb_substr($proof->platform, 0, 1)) }}</span>
                                            @endif
                                        </span>
                                        <p class="text-sm font-semibold text-text-primary">{{ $proof->shortLabel() }}</p>
                                    </div>
                                    <x-dashboard.badge status="success">Done</x-dashboard.badge>
                                </div>
                                @if ($proof->proof_url)
                                    <a href="{{ $proof->proof_url }}" target="_blank" rel="noopener" class="inline-block">
                                        <img src="{{ $proof->proof_url }}" alt="Screenshot" class="max-h-40 rounded-lg border border-border-default">
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                    <form method="POST" action="{{ route('agent.social-follow.submit') }}" data-ajax-form x-data="taskAction" @submit.prevent="submit($event)" class="space-y-2">
                        @csrf
                        <x-dashboard.button type="submit" icon="verified" x-bind:disabled="busy">Submit task</x-dashboard.button>
                        <p class="text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
                    </form>
                </x-dashboard.card>
            @endif
        </div>
    @endif
</x-layout.page>
@endsection
