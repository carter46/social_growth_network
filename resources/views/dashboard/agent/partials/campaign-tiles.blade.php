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
        <p class="text-xs text-text-muted">{{ __('Slot status') }}</p>
        <p class="mt-0.5 text-lg font-semibold text-text-primary">{{ $campaign->availableStartSlots() > 0 ? __('Open') : __('Closed') }}</p>
    </div>
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ $completionMinutes > 0 ? __('Time to complete') : __('Estimated time') }}</p>
        <p class="mt-0.5 text-lg font-semibold text-text-primary">{{ $minutes > 0 ? trans_choice(':count min|:count mins', $minutes) : '-' }}</p>
    </div>
</div>

@if ($campaign->usesStepFlow() && ($status ?? null) !== 'started')
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Steps') }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-sm font-medium text-text-primary">
            @foreach ($tileProfile->tasks() as $tileTask)
                @if (! $loop->first)
                    <span class="text-text-muted" aria-hidden="true">→</span>
                @endif
                <span>{{ $tileTask->shortLabel() }}</span>
            @endforeach
        </div>
    </div>
@endif

@if (($status ?? null) !== 'started' && ! $hasEmbed && filled($campaign->target_url))
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Link') }}</p>
        <a href="{{ $embed['open_url'] ?? $campaign->target_url }}" class="mt-0.5 inline-flex break-all text-sm font-medium text-primary hover:underline" target="_blank" rel="noopener">{{ $embed['open_url'] ?? $campaign->target_url }}</a>
    </div>
@endif

@if ($briefing !== '')
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Your task') }}</p>
        <p class="mt-0.5 text-sm text-text-secondary">{{ $briefing }}</p>
    </div>
@endif
