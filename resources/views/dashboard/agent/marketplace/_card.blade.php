@php
    $href = route('agent.marketplace.show', $campaign);
    $channel = $campaign->creator?->verifiedYoutubeIdentity;
    $channelName = trim((string) ($channel?->title ?: $channel?->handle ?: ''));
    $slotsLeft = $campaign->availableStartSlots();
    $minutes = (int) ($campaign->estimated_minutes ?? 0);
    $completionMinutes = (int) ($campaign->completion_minutes ?? 0);
    $tasks = $campaign->usesStepFlow() ? $campaign->openTasks() : [];
@endphp
<article class="rounded-xl border border-border-default bg-elevated px-4 py-4 sm:px-5 sm:py-5 flex flex-col gap-3 hover:border-primary/40 transition-colors">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="mt-0.5 text-sm font-semibold text-text-primary" data-marketplace-channel>
                {{ $channelName !== '' ? 'Channel: '.$channelName : 'Channel: N/A' }}
            </p>
        </div>
        <p class="text-xs text-text-muted shrink-0">{{ $campaign->created_at?->diffForHumans() }}</p>
    </div>

    <div class="min-w-0">
        <h3 class="font-display text-base sm:text-lg font-bold text-text-primary leading-snug">
            <a href="{{ $href }}" class="hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded">
                {{ $campaign->title ?: __('Campaign task') }}
            </a>
        </h3>
        @if ($campaign->product?->title && $campaign->product->title !== $campaign->title)
            <p class="mt-1 text-sm text-text-secondary">{{ $campaign->product->title }}</p>
        @endif
    </div>

    <div class="flex flex-wrap gap-1.5">
        <span class="px-2 py-0.5 bg-surface-muted text-text-secondary rounded text-xs font-medium">
            {{ $slotsLeft > 0 ? __('Slot status: Open') : __('Slot status: Closed') }}
        </span>
        <span class="px-2 py-0.5 bg-surface-muted text-text-secondary rounded text-xs font-medium">
            @if ($completionMinutes > 0)
                {{ __(':time to complete', ['time' => trans_choice(':count min|:count mins', $completionMinutes)]) }}
            @else
                {{ $minutes > 0 ? trans_choice(':count min|:count mins', $minutes) : __('Quick task') }}
            @endif
        </span>
        @if (count($tasks) > 0)
            @foreach ($tasks as $cardTask)
                <span class="px-2 py-0.5 bg-surface-muted text-text-secondary rounded text-xs font-medium">{{ $cardTask->shortLabel() }}</span>
            @endforeach
        @endif
    </div>

    <div class="mt-auto pt-3 border-t border-border-subtle flex items-center justify-between gap-3">
        <div class="min-w-0">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-text-muted">{{ __('Reward') }}</span>
            <span class="font-display text-xl font-bold text-text-primary tracking-tight">
                {{ money($campaign->locked_agent_reward, 2, 4) }}
            </span>
        </div>
        <a
            href="{{ $href }}"
            class="shrink-0 inline-flex items-center gap-1 px-3.5 py-2 bg-surface-muted text-primary text-sm font-semibold rounded-lg hover:bg-primary hover:text-white transition-colors"
        >
            {{ __('View task') }}
            <x-ui.icon name="arrow-right" class="w-4 h-4" />
        </a>
    </div>
</article>
