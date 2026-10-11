@php
    /** @var \App\Models\AgentYoutubeIdentity|null $identity */
    $identity = $identity ?? null;
    $oauthEnabled = $oauthEnabled ?? false;
    $returnTo = $returnTo ?? null;
    $status = $identity?->status;
    $showPasteForm = ! in_array($status, ['verified', 'pending'], true);
@endphp

<div class="space-y-4 text-sm" x-data="{
        url: @js(old('channel_url', '')),
        loading: false,
        error: '',
        channel: null,
        showPaste: @js($showPasteForm),
        async preview() {
            this.error = '';
            this.channel = null;
            if (! this.url.trim()) { this.error = 'Paste your channel link first.'; return; }
            this.loading = true;
            try {
                const res = await fetch(@js(route('agent.youtube-channel.preview')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                    body: JSON.stringify({ channel_url: this.url }),
                });
                const data = await res.json().catch(() => ({}));
                if (! res.ok) { this.error = data.message || 'We could not check that link.'; return; }
                this.channel = data.channel;
            } catch (e) {
                this.error = 'We could not check that link. Please try again.';
            } finally {
                this.loading = false;
            }
        },
    }">
    <p class="text-text-secondary">
        Linking your channel lets us check your YouTube tasks automatically. It does not give us access to your account.
    </p>

    @if ($status === 'verified')
        <div class="flex items-center gap-3 rounded-xl border border-success/30 bg-success/10 px-4 py-3">
            @if ($identity->thumbnail_url)
                <img src="{{ $identity->thumbnail_url }}" alt="" class="h-10 w-10 rounded-full">
            @endif
            <div class="min-w-0">
                <p class="font-semibold text-text-primary">{{ $identity->title ?: 'Your channel' }}</p>
                <p class="text-xs text-text-muted">{{ $identity->handle }} · Verified {{ $identity->verified_at?->diffForHumans() }}</p>
            </div>
        </div>
    @elseif ($status === 'pending')
        <div class="space-y-3 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3">
            <p class="font-semibold text-text-primary">Pending verification{{ $identity->title ? ': '.$identity->title : '' }}</p>
            @if ($identity->method === 'admin')
                <p class="text-text-secondary">Automatic checks are not available right now, so an admin will review your channel. You'll get a notification when it's approved.</p>
            @else
                <ol class="list-decimal space-y-1 pl-5 text-text-secondary">
                    <li>Make sure your channel is public.</li>
                    <li>In YouTube settings, turn off "Keep all my subscriptions private". This lets your subscribe steps be checked automatically; otherwise they need screenshot review.</li>
                    <li>Paste this code anywhere in your channel description and save:</li>
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
                <form method="POST" action="{{ route('agent.youtube-channel.check') }}">
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
        <form method="POST" action="{{ route('agent.youtube-channel.retry') }}">
            @csrf
            @if ($returnTo)<input type="hidden" name="return" value="{{ $returnTo }}">@endif
            <x-dashboard.button type="submit" size="sm">Retry with a new code</x-dashboard.button>
        </form>
    @endif

    @if ($status !== 'verified')
        <div x-show="showPaste" x-cloak class="space-y-3">
            @if ($oauthEnabled)
                <x-dashboard.button :href="route('agent.youtube-channel.oauth', $returnTo ? ['return' => $returnTo] : [])">Connect YouTube channel</x-dashboard.button>
                <p class="text-xs text-text-muted">Or link it manually:</p>
            @endif

            <div>
                <label for="yt-channel-url" class="mb-1 block text-xs font-medium text-text-secondary">Your YouTube channel link</label>
                <div class="flex gap-2">
                    <input id="yt-channel-url" type="url" x-model="url" placeholder="https://www.youtube.com/@yourchannel"
                           class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">
                    <x-dashboard.button type="button" size="sm" variant="secondary" x-on:click="preview()" x-bind:disabled="loading">Preview</x-dashboard.button>
                </div>
                <p class="mt-1 text-xs text-text-muted">Use your @handle link or your youtube.com/channel/UC... link.</p>
                <p class="mt-1 text-xs text-danger" x-show="error" x-text="error"></p>
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
                    <form method="POST" action="{{ route('agent.youtube-channel.store') }}">
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
