@php
    /** @var \App\Models\AgentYoutubeIdentity $identity */
    $channelUrl = $identity->channel_id
        ? 'https://www.youtube.com/channel/'.$identity->channel_id
        : $identity->channel_url;
    $methodLabel = match ($identity->method) {
        'oauth' => filled($identity->oauth_token) ? 'Verified with Google (read-only access)' : 'Verified with Google · Google access removed',
        'admin' => 'Approved by our team',
        default => 'Verified with a description code',
    };
@endphp

<div class="flex items-center gap-4 rounded-xl border border-success/30 bg-success/10 px-4 py-4" data-youtube-connected-card>
    @if ($identity->thumbnail_url)
        <img src="{{ $identity->thumbnail_url }}" alt="" class="h-14 w-14 shrink-0 rounded-full object-cover" referrerpolicy="no-referrer">
    @else
        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-success/20 text-success">
            <x-ui.icon name="verified" class="h-7 w-7" />
        </span>
    @endif
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <p class="truncate text-base font-semibold text-text-primary">{{ $identity->title ?: 'Your channel' }}</p>
            <x-dashboard.badge variant="success">Connected</x-dashboard.badge>
        </div>
        @if ($identity->handle)
            <p class="text-sm text-text-secondary">{{ $identity->handle }}</p>
        @endif
        <p class="mt-0.5 text-xs text-text-muted">
            {{ $methodLabel }}@if ($identity->verified_at) · {{ $identity->verified_at->diffForHumans() }}@endif
        </p>
        @if ($channelUrl)
            <a href="{{ $channelUrl }}" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                View on YouTube <x-ui.icon name="arrow-right" class="h-3 w-3" />
            </a>
        @endif
    </div>
</div>
