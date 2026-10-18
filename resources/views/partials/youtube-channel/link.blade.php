@php
    /** @var \App\Models\AgentYoutubeIdentity|null $identity */
    $identity = $identity ?? null;
    $prefix = $prefix ?? 'agent';
    $isAgent = $prefix === 'agent';
    $oauthEnabled = $oauthEnabled ?? false;
    $returnTo = $returnTo ?? null;
    $channelMissing = $channelMissing ?? false;
    $status = $identity?->status;
    $showPasteForm = ! in_array($status, ['verified', 'pending'], true) || ($channelMissing && $status !== 'verified');
@endphp

<div class="space-y-4 text-sm" x-data="{
        url: @js(old('channel_url', '')),
        loading: false,
        error: '',
        missingHelp: false,
        channel: null,
        showPaste: @js($showPasteForm),
        async preview() {
            this.error = '';
            this.missingHelp = false;
            this.channel = null;
            if (! this.url.trim()) { this.error = 'Paste your channel link first.'; return; }
            this.loading = true;
            try {
                const res = await fetch(@js(route($prefix.'.youtube-channel.preview')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                    body: JSON.stringify({ channel_url: this.url }),
                });
                const data = await res.json().catch(() => ({}));
                if (! res.ok) { this.error = data.message || 'We could not check that link.'; this.missingHelp = !! data.missing_channel_help; return; }
                this.channel = data.channel;
            } catch (e) {
                this.error = 'We could not check that link. Please try again.';
            } finally {
                this.loading = false;
            }
        },
    }" x-on:youtube-show-paste.window="showPaste = true; $nextTick(() => $refs.channelUrl?.focus())">
    <p class="text-text-secondary">
        @if ($isAgent)
            Linking your channel lets us check your YouTube tasks automatically. It does not give us access to your account.
        @else
            Connecting your channel helps us identify your channel and videos when you set up campaigns. It's optional and does not give us access to your account.
        @endif
    </p>

    @if ($channelMissing && $status !== 'verified')
        @include('partials.youtube-channel.missing-channel', ['prefix' => $prefix, 'oauthEnabled' => $oauthEnabled, 'returnTo' => $returnTo, 'source' => $channelMissing === 'link' ? 'link' : 'oauth'])
    @endif

    @if ($status === 'verified')
        @include('partials.youtube-channel.connected-card', ['identity' => $identity])
    @elseif ($status === 'pending')
        <div class="space-y-3 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3">
            <p class="font-semibold text-text-primary">Pending verification{{ $identity->title ? ': '.$identity->title : '' }}</p>
            @if ($identity->method === 'admin')
                <p class="text-text-secondary">Automatic checks are not available right now, so an admin will review your channel. You'll get a notification when it's approved.</p>
            @else
                <ol class="list-decimal space-y-1 pl-5 text-text-secondary">
                    <li>Make sure your channel is public.</li>
                    @if ($isAgent)
                        <li>In YouTube settings, turn off "Keep all my subscriptions private". This lets your subscribe steps be checked automatically; otherwise they need screenshot review.</li>
                    @endif
                    <li>On YouTube, open your channel, choose Customise channel, then add this code anywhere in your channel description and publish:</li>
                </ol>
                <div class="flex items-center gap-2">
                    <code class="rounded-lg bg-surface px-3 py-2 font-mono text-base font-semibold tracking-wider text-text-primary">{{ $identity->verification_code }}</code>
                    <button type="button" class="text-xs text-primary hover:underline" x-on:click="navigator.clipboard?.writeText(@js($identity->verification_code))">Copy</button>
                </div>
                <p class="text-xs text-text-muted"
                   x-data="{ ends: @js($identity->code_expires_at?->toIso8601String()), left: '' }"
                   x-init="const tick = () => { const s = Math.max(0, Math.floor((new Date(ends) - new Date()) / 1000)); left = Math.floor(s / 3600) + 'h ' + Math.floor((s % 3600) / 60) + 'm'; }; tick(); setInterval(tick, 30000);">
                    We check automatically every 30 minutes. Time left: <span x-text="left"></span>
                </p>
                <form method="POST" action="{{ route($prefix.'.youtube-channel.check') }}">
                    @csrf
                    @if ($returnTo)<input type="hidden" name="return" value="{{ $returnTo }}">@endif
                    <x-dashboard.button type="submit" size="sm">I've added the code</x-dashboard.button>
                </form>
            @endif
            <button type="button" class="text-xs text-text-muted hover:underline" x-on:click="showPaste = ! showPaste">Use a different channel</button>
        </div>
    @elseif ($status === 'failed')
        <x-dashboard.alert type="danger">
            YouTube channel verification failed. {{ $identity->failure_reason }}
        </x-dashboard.alert>
        <form method="POST" action="{{ route($prefix.'.youtube-channel.retry') }}">
            @csrf
            @if ($returnTo)<input type="hidden" name="return" value="{{ $returnTo }}">@endif
            <x-dashboard.button type="submit" size="sm">Retry with a new code</x-dashboard.button>
        </form>
    @endif

    @if ($status !== 'verified')
        <div x-show="showPaste" x-cloak class="space-y-3">
            @if ($oauthEnabled)
                <x-dashboard.button :href="route($prefix.'.youtube-channel.oauth', $returnTo ? ['return' => $returnTo] : [])">Connect YouTube channel</x-dashboard.button>
                <p class="text-xs text-text-muted">You'll sign in with Google and pick the account that owns your channel. Or link it manually:</p>
            @endif

            <div>
                <label for="yt-channel-url" class="mb-1 block text-xs font-medium text-text-secondary">Your YouTube channel link</label>
                <div class="flex gap-2">
                    <input id="yt-channel-url" type="text" inputmode="url" autocomplete="off" x-ref="channelUrl" x-model="url" placeholder="https://www.youtube.com/@yourchannel"
                           x-on:keydown.enter.prevent="preview()"
                           class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">
                    <x-dashboard.button type="button" size="sm" variant="secondary" x-on:click="preview()" x-bind:disabled="loading">Preview</x-dashboard.button>
                </div>
                <p class="mt-1 text-xs text-text-muted">Paste your channel link or your @handle.</p>
                <p class="mt-1 text-xs text-danger" x-show="error" x-text="error"></p>
                <p class="mt-1 text-xs text-text-secondary" x-show="missingHelp" x-cloak data-youtube-missing-hint>
                    Don't have a channel yet?
                    <a href="{{ \App\Http\Controllers\YoutubeChannelController::CREATE_CHANNEL_URL }}" target="_blank" rel="noopener" class="text-primary hover:underline">Create a YouTube channel</a>,
                    then come back and paste its link.
                </p>
                <div class="mt-2">
                    @include('partials.youtube-channel.find-link-help')
                </div>
            </div>

            <template x-if="channel">
                <div class="space-y-3 rounded-xl border border-border-default px-4 py-3">
                    <div class="flex items-center gap-3">
                        <template x-if="channel.thumbnail"><img :src="channel.thumbnail" alt="" class="h-10 w-10 rounded-full"></template>
                        <div class="min-w-0">
                            <p class="font-semibold text-text-primary" x-text="channel.title || 'Channel found'"></p>
                            <p class="text-xs text-text-muted" x-text="channel.handle || channel.id || ''"></p>
                            <p class="text-xs text-warning" x-show="! channel.resolved">We can't preview channels right now, so an admin will confirm it.</p>
                        </div>
                    </div>
                    <p class="text-xs text-text-muted">Next, you'll add a short code to this channel's description to prove it's yours.</p>
                    <form method="POST" action="{{ route($prefix.'.youtube-channel.store') }}">
                        @csrf
                        <input type="hidden" name="channel_url" :value="url">
                        @if ($returnTo)<input type="hidden" name="return" value="{{ $returnTo }}">@endif
                        <x-dashboard.button type="submit" size="sm">This is my channel</x-dashboard.button>
                    </form>
                </div>
            </template>
        </div>
    @endif
</div>
