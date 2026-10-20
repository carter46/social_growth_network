@php
    $tile = 'rounded-xl bg-muted/40 px-4 py-3';
    $completionMinutes = (int) ($campaign->completion_minutes ?? 0);
    $minutes = $completionMinutes > 0 ? $completionMinutes : (int) ($campaign->estimated_minutes ?? 0);
    $tileProfile = $campaign->taskProfile();
    $status = $status ?? null;
    $embed = $embed ?? null;
    $hasEmbed = ($embed['mode'] ?? '') === 'embed' && ! empty($embed['html']);
    $briefingTasks = $briefingTasks ?? null;
    $briefing = trim((string) ($campaign->agentBriefing(is_array($briefingTasks) ? $briefingTasks : null) ?? ''));
@endphp
<div @class(['grid grid-cols-1 gap-3', 'sm:grid-cols-2 xl:grid-cols-4' => $status, 'sm:grid-cols-3' => ! $status])>
    @if ($status)
        <div class="{{ $tile }}">
            <p class="text-xs text-text-muted">{{ __('Status') }}</p>
            <div class="mt-1"><x-dashboard.badge :status="$status" /></div>
        </div>
    @endif
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Reward') }}</p>
        <p class="mt-0.5 text-lg font-semibold text-text-primary">{{ money($reward, 2, 4) }}</p>
    </div>
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Slots left') }}</p>
        <p class="mt-0.5 text-lg font-semibold text-text-primary">{{ number_format($campaign->availableStartSlots()) }}</p>
    </div>
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ $completionMinutes > 0 ? __('Time to complete') : __('Estimated time') }}</p>
        <p class="mt-0.5 text-lg font-semibold text-text-primary">{{ $minutes > 0 ? trans_choice(':count min|:count mins', $minutes) : '-' }}</p>
    </div>
</div>

@if ($campaign->usesStepFlow())
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Tasks, in order') }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-1.5">
            @foreach ($tileProfile->tasks() as $index => $tileTask)
                <x-dashboard.badge status="info">{{ $index + 1 }}. {{ $tileTask->shortLabel() }}</x-dashboard.badge>
            @endforeach
        </div>
        @if ((int) ($campaign->estimated_minutes ?? 0) > 0 && $tileProfile->needsWatchMinutes())
            <p class="mt-1 text-xs text-text-muted">{{ __('Includes :count minutes of watching.', ['count' => (int) $campaign->estimated_minutes]) }}</p>
        @endif
    </div>
@endif

@if ($hasEmbed || $campaign->target_url)
    <div class="{{ $tile }} space-y-2">
        <p class="text-xs text-text-muted">{{ __('What you will work on') }}</p>
        @if ($hasEmbed)
            <div class="overflow-hidden rounded-lg border border-border-default">{!! $embed['html'] !!}</div>
        @elseif (! empty($embed['open_url']))
            <a href="{{ $embed['open_url'] }}" class="inline-flex text-sm font-medium text-primary hover:underline" target="_blank" rel="noopener">{{ __('Open on YouTube') }}</a>
        @elseif ($campaign->target_url)
            <a href="{{ $campaign->target_url }}" class="inline-flex text-sm font-medium text-primary hover:underline" target="_blank" rel="noopener">{{ __('Open on YouTube') }}</a>
        @endif
    </div>
@endif

@if ($briefing !== '')
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Your task') }}</p>
        <p class="mt-0.5 text-sm text-text-secondary">{{ $briefing }}</p>
    </div>
@endif
