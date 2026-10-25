@extends('layouts.dashboard-agent')

@section('title', 'Follow us')

@section('content')
@php
    use App\Models\AgentSocialFollowSubmission;

    $status = $submission?->status;
    $timed = $submission?->isTimedRunActive() ?? false;
    $canEdit = $timed && ($submission?->canEditProofs() ?? false);
    $allDone = $submission && $submission->proofs->isNotEmpty() && $submission->proofs->every(fn ($p) => $p->isComplete());
    $needsStart = in_array($status, [
        AgentSocialFollowSubmission::STATUS_PENDING,
        AgentSocialFollowSubmission::STATUS_EXPIRED,
        AgentSocialFollowSubmission::STATUS_REJECTED,
        null,
    ], true) && ! $timed;
@endphp
<x-layout.page
    title="Follow us"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Marketplace', route('agent.marketplace')],
        ['Follow us', null],
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

    @if ($status !== AgentSocialFollowSubmission::STATUS_APPROVED && $status !== AgentSocialFollowSubmission::STATUS_SUBMITTED)
        <x-dashboard.card class="mb-4 space-y-3">
            <div class="flex flex-wrap gap-2 text-sm text-text-secondary">
                <span class="px-2 py-0.5 bg-surface-muted rounded text-xs font-medium">{{ __('Follow') }}</span>
                <span class="px-2 py-0.5 bg-surface-muted rounded text-xs font-medium">
                    {{ __(':time to complete', ['time' => trans_choice(':count min|:count mins', (int) ($completionMinutes ?? 3))]) }}
                </span>
                <span class="px-2 py-0.5 bg-surface-muted rounded text-xs font-medium">{{ __('Reward: Required') }}</span>
            </div>
            <p class="text-sm text-text-secondary">Follow each account, then upload a clear screenshot that shows you followed.</p>

            @if ($needsStart)
                <form method="POST" action="{{ route('agent.social-follow.start') }}">
                    @csrf
                    <x-dashboard.button type="submit" icon="plus">Start task</x-dashboard.button>
                </form>
            @endif
        </x-dashboard.card>
    @endif

    @if ($timed || $status === AgentSocialFollowSubmission::STATUS_SUBMITTED)
        <div class="space-y-4">
            @foreach (($submission?->proofs ?? collect()) as $proof)
                <x-dashboard.card class="space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border-default bg-elevated">
                                @if ($proof->logoUrl())
                                    <img src="{{ $proof->logoUrl() }}" alt="" class="h-full w-full rounded-full object-cover">
                                @else
                                    <span class="text-base font-bold text-text-primary">{{ mb_strtoupper(mb_substr($proof->platform, 0, 1)) }}</span>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-text-primary">{{ $proof->displayName() }}</p>
                                <a href="{{ $proof->url }}" target="_blank" rel="noopener noreferrer" class="text-sm text-primary hover:underline break-all">{{ $proof->url }}</a>
                            </div>
                        </div>
                        <x-dashboard.badge :status="$proof->isComplete() ? 'success' : 'pending'">
                            {{ $proof->isComplete() ? 'Done' : 'Needed' }}
                        </x-dashboard.badge>
                    </div>

                    <x-dashboard.button :href="$proof->url" target="_blank" rel="noopener noreferrer" size="sm">
                        Open {{ $proof->displayName() }}
                    </x-dashboard.button>

                    @if ($proof->proof_url)
                        <a href="{{ $proof->proof_url }}" target="_blank" rel="noopener" class="inline-block overflow-hidden rounded-xl border border-border-default">
                            <img src="{{ $proof->proof_url }}" alt="Proof for {{ $proof->displayName() }}" class="max-h-40 w-auto object-contain">
                        </a>
                    @endif

                    @if ($canEdit)
                        <form method="POST" action="{{ route('agent.social-follow.proof', $proof) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-text-secondary" for="proof-{{ $proof->id }}">Screenshot</label>
                                <input
                                    id="proof-{{ $proof->id }}"
                                    type="file"
                                    name="proof"
                                    accept="image/jpeg,image/png,image/webp"
                                    required
                                    class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-elevated file:px-3 file:py-2 file:text-sm file:text-text-primary"
                                />
                            </div>
                            <x-dashboard.button type="submit" size="sm">
                                {{ $proof->isComplete() ? 'Replace screenshot' : 'Upload screenshot' }}
                            </x-dashboard.button>
                        </form>
                    @endif
                </x-dashboard.card>
            @endforeach
        </div>

        @if ($canEdit && ($submission?->proofs?->isNotEmpty() ?? false))
            @if ($allDone)
                <form method="POST" action="{{ route('agent.social-follow.submit') }}" class="mt-6">
                    @csrf
                    <x-dashboard.button type="submit">Submit for review</x-dashboard.button>
                </form>
            @else
                <p class="mt-6 text-sm text-text-muted">Upload a screenshot for every account before submitting.</p>
            @endif
        @endif
    @endif
</x-layout.page>
@endsection
