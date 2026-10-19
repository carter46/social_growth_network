@php
    $verified = $identity?->isVerified();
    $nextUrl = $returnTo ?: ($isAgent ? route('agent.marketplace') : route('dashboard'));
    $nextLabel = $returnTo ? 'Continue' : ($isAgent ? 'Browse open tasks' : 'Back to dashboard');
@endphp

@if (session('status'))
    <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
@endif
@if (session('error'))
    <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
@endif

@if ($justConnected && $verified)
    <div class="mb-4 flex flex-col gap-4 rounded-2xl border border-success/30 bg-success/10 p-5 sm:flex-row sm:items-center sm:justify-between" data-youtube-just-connected>
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-success text-white">
                <x-ui.icon name="check" class="h-5 w-5" />
            </span>
            <div>
                <p class="text-base font-semibold text-text-primary">Your YouTube channel is connected</p>
                <p class="text-sm text-text-secondary">
                    {{ $isAgent ? 'You can now take on YouTube tasks.' : 'We\'ll use it to fill in your channel and videos when you set up campaigns.' }}
                </p>
            </div>
        </div>
        <x-dashboard.button :href="$nextUrl" icon="arrow-right">{{ $nextLabel }}</x-dashboard.button>
    </div>
@endif

<div class="grid gap-4 lg:grid-cols-2">
    <x-dashboard.card>
        <h2 class="mb-3 text-sm font-semibold">{{ $verified ? 'Connected channel' : 'Connect your channel' }}</h2>
        @include('partials.youtube-channel.link', [
            'identity' => $identity,
            'prefix' => $prefix,
            'oauthEnabled' => $oauthEnabled,
            'returnTo' => $returnTo,
            'channelMissing' => $channelMissing,
            'channelTaken' => $channelTaken ?? null,
            'reclaimContext' => $reclaimContext ?? null,
        ])
        @if ($verified && ! $justConnected && $returnTo)
            <x-dashboard.button :href="$returnTo" class="mt-4">Continue</x-dashboard.button>
        @endif
    </x-dashboard.card>

    @if ($verified)
        <x-dashboard.card>
            <h2 class="mb-3 text-sm font-semibold">Wrong channel or want to change it?</h2>
            <p class="mb-3 text-sm text-text-secondary">
                Disconnect this channel, then connect the right one.
                {{ $isAgent ? 'Tasks you already completed are not affected.' : 'Your campaigns are not affected.' }}
            </p>
            @if ($isAgent && $openTasks > 0)
                <x-dashboard.alert type="warning" class="mb-3" data-youtube-unlink-blocked>
                    {{ trans_choice('{1} You have 1 YouTube task in progress or awaiting review. It is checked against this channel, so you can disconnect once it is finished.|[2,*] You have :count YouTube tasks in progress or awaiting review. They are checked against this channel, so you can disconnect once they are finished.', $openTasks, ['count' => $openTasks]) }}
                </x-dashboard.alert>
                <x-dashboard.button :href="route('agent.tasks.active')" size="sm" variant="secondary">View my tasks</x-dashboard.button>
            @else
                <form method="POST" action="{{ route($prefix.'.youtube-channel.unlink') }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Disconnect '.($identity->title ?: 'this channel').'? You can connect another channel right after.') }})">
                    @csrf
                    <x-dashboard.button type="submit" size="sm" variant="secondary">Disconnect channel</x-dashboard.button>
                </form>
            @endif
            @if ($identity->method === 'oauth' && filled($identity->oauth_token))
                <div class="mt-5 border-t border-border-default pt-4">
                    <p class="mb-2 text-sm text-text-secondary">We only have read-only access to your YouTube account. You can remove it and your channel stays connected.</p>
                    <form method="POST" action="{{ route($prefix.'.youtube-channel.oauth.disconnect') }}" onsubmit="return confirm('Remove Google access? Your channel stays connected.')">
                        @csrf
                        <x-dashboard.button type="submit" size="sm" variant="link">Remove Google access</x-dashboard.button>
                    </form>
                </div>
            @elseif ($oauthEnabled)
                <div class="mt-5 border-t border-border-default pt-4" data-youtube-grant-access>
                    <p class="mb-2 text-sm text-text-secondary">
                        {{ $identity->method === 'oauth' ? 'Google access is off.' : 'Your channel was verified without Google.' }}
                        Allowing read-only Google access lets us confirm your channel directly with YouTube. When Google asks, pick the account for {{ $identity->title ?: 'this channel' }}.
                    </p>
                    <x-dashboard.button :href="route($prefix.'.youtube-channel.oauth', $returnTo ? ['return' => $returnTo] : [])" size="sm" variant="secondary">
                        {{ $identity->method === 'oauth' ? 'Give Google access again' : 'Connect with Google' }}
                    </x-dashboard.button>
                </div>
            @endif
        </x-dashboard.card>
    @endif
</div>
