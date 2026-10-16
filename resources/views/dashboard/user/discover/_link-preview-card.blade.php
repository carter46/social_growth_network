{{--
    YouTube video or channel card on checkout. Params: $url, $video (array|null), $channel (array|null).
    Falls back to the plain link when YouTube could not be checked.
--}}
@php
    $video = $video ?? null;
    $channel = $channel ?? null;
@endphp
<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 rounded-lg border border-border-default bg-elevated p-2 transition hover:border-primary/40">
    @if($video)
        @if(filled($video['thumbnail'] ?? null))
            <img src="{{ $video['thumbnail'] }}" alt="" class="w-28 shrink-0 rounded object-cover aspect-video sm:w-36" referrerpolicy="no-referrer">
        @endif
        <span class="min-w-0 space-y-0.5">
            <span class="block text-sm font-medium text-text-primary line-clamp-2">{{ filled($video['title'] ?? null) ? $video['title'] : 'YouTube video' }}</span>
            @if(filled($video['channel_title'] ?? null))
                <span class="block text-xs text-text-muted truncate">{{ $video['channel_title'] }}</span>
            @endif
            @if(filled($video['duration'] ?? null))
                <span class="block text-xs text-text-muted">{{ $video['duration'] }}</span>
            @endif
        </span>
    @elseif($channel)
        @if(filled($channel['thumbnail'] ?? null))
            <img src="{{ $channel['thumbnail'] }}" alt="" class="h-12 w-12 shrink-0 rounded-full object-cover" referrerpolicy="no-referrer">
        @endif
        <span class="min-w-0">
            <span class="block text-sm font-medium text-text-primary truncate">{{ filled($channel['title'] ?? null) ? $channel['title'] : 'YouTube channel' }}</span>
            @if(filled($channel['handle'] ?? null))
                <span class="block text-xs text-text-muted truncate">{{ $channel['handle'] }}</span>
            @endif
        </span>
    @else
        <span class="block min-w-0 text-sm font-medium text-text-primary break-all">{{ $url }}</span>
    @endif
</a>
