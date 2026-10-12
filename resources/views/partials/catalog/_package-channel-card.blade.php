<p class="{{ $c['muted'] }}" x-show="channelLoading" x-cloak>Checking your channel...</p>
<div x-show="channel && channel.channel" x-cloak class="flex items-center gap-3 {{ $c['tile'] }} p-3">
    <img x-show="channel?.channel?.thumbnail" :src="channel?.channel?.thumbnail" alt="" class="h-12 w-12 shrink-0 rounded-full object-cover" referrerpolicy="no-referrer">
    <div class="min-w-0">
        <p class="text-sm font-medium {{ $c['strong'] }} truncate" x-text="channel?.channel?.title"></p>
        <p class="{{ $c['muted'] }} truncate" x-text="channel?.channel?.handle || ''"></p>
    </div>
</div>
<p x-show="channel && channel.message" x-cloak class="text-xs" :class="channel?.allowed === false ? '{{ str_contains($c['error'], 'danger') ? 'text-danger' : 'text-red-600' }}' : '{{ $c['muted'] }}'" x-text="channel?.message"></p>
