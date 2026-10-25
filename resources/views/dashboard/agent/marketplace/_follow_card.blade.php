@php
    use App\Models\AgentSocialFollowSubmission;

    $href = route('agent.social-follow');
    $count = (int) ($followSocialCount ?? ($followSubmission?->proofs?->count() ?? 0));
    $minutes = AgentSocialFollowSubmission::completionMinutesForCount(max(1, $count));
    $status = $followSubmission?->status;
    $siteName = $siteName ?? config('app.name', 'Social Growth Network');
    $cta = __('View task');
    $slotLabel = match ($status) {
        AgentSocialFollowSubmission::STATUS_SUBMITTED => __('Slot status: Under review'),
        AgentSocialFollowSubmission::STATUS_EXPIRED => __('Slot status: Expired'),
        default => __('Slot status: Open'),
    };
@endphp
<article class="rounded-xl border border-border-default bg-elevated px-4 py-4 sm:px-5 sm:py-5 flex flex-col gap-3 hover:border-primary/40 transition-colors">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="mt-0.5 text-sm font-semibold text-text-primary" data-marketplace-channel>
                {{ __('Channel: :name', ['name' => $siteName]) }}
            </p>
        </div>
        <p class="text-xs text-text-muted shrink-0">{{ $followSubmission?->updated_at?->diffForHumans() ?? __('Just now') }}</p>
    </div>

    <div class="min-w-0">
        <h3 class="font-display text-base sm:text-lg font-bold text-text-primary leading-snug">
            <a href="{{ $href }}" class="hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded">
                {{ __('Priority task') }}
            </a>
        </h3>
        <p class="mt-1 text-sm text-text-secondary">
            {{ __('Complete these tasks to start earning real money.') }}
        </p>
    </div>

    <div class="flex flex-wrap gap-1.5">
        <span class="px-2 py-0.5 bg-surface-muted text-text-secondary rounded text-xs font-medium">
            {{ $slotLabel }}
        </span>
        <span class="px-2 py-0.5 bg-surface-muted text-text-secondary rounded text-xs font-medium">
            {{ __(':time to complete', ['time' => trans_choice(':count min|:count mins', $minutes)]) }}
        </span>
        <span class="px-2 py-0.5 bg-surface-muted text-text-secondary rounded text-xs font-medium">{{ __('Priority') }}</span>
    </div>

    <div class="mt-auto pt-3 border-t border-border-subtle flex items-center justify-between gap-3">
        <div class="min-w-0">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-text-muted">{{ __('Reward') }}</span>
            <span class="font-display text-xl font-bold text-text-primary tracking-tight">
                {{ __('Required') }}
            </span>
        </div>
        <a
            href="{{ $href }}"
            class="shrink-0 inline-flex items-center gap-1 px-3.5 py-2 bg-surface-muted text-primary text-sm font-semibold rounded-lg hover:bg-primary hover:text-white transition-colors"
        >
            {{ $cta }}
            <x-ui.icon name="arrow-right" class="w-4 h-4" />
        </a>
    </div>
</article>
