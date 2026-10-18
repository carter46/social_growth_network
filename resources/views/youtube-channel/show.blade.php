@extends($layout)

@section('title', 'YouTube channel')

@section('content')
@php
    $verified = $identity?->isVerified();
    $nextUrl = $returnTo ?: ($isAgent ? route('agent.marketplace') : route('dashboard'));
    $nextLabel = $returnTo ? 'Continue' : ($isAgent ? 'Browse open tasks' : 'Back to dashboard');
@endphp
<x-layout.page
    title="YouTube channel"
    width="full"
    :breadcrumb="[
        [$isAgent ? 'Agent' : 'Dashboard', route($prefix)],
        ['YouTube channel', null],
    ]"
>
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
            ])
            @if ($verified && ! $justConnected && $returnTo)
                <x-dashboard.button :href="$returnTo" class="mt-4">Continue</x-dashboard.button>
            @endif
        </x-dashboard.card>

        @if ($verified)
            <x-dashboard.card>
                <h2 class="mb-3 text-sm font-semibold">Manage</h2>
                @if ($isAgent)
                    <p class="mb-3 text-sm text-text-secondary">
                        Your tasks are checked against this channel, so it can't be removed on your own. To switch to another channel you own, send a change request. Your current channel stays connected until an admin approves it.
                    </p>
                    @if ($changeRequest)
                        <p class="text-sm text-text-secondary">Your change request to <span class="font-medium text-text-primary">{{ $changeRequest->title ?: $changeRequest->channel_url }}</span> is waiting for admin review.</p>
                    @else
                        <form method="POST" action="{{ route('agent.youtube-channel.change') }}" class="flex gap-2">
                            @csrf
                            <input type="text" inputmode="url" name="channel_url" required placeholder="https://www.youtube.com/@newchannel"
                                   class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">
                            <x-dashboard.button type="submit" size="sm" variant="secondary">Request change</x-dashboard.button>
                        </form>
                    @endif
                @else
                    <p class="mb-3 text-sm text-text-secondary">Disconnect this channel at any time, for example to connect a different one. Your campaigns are not affected.</p>
                    <form method="POST" action="{{ route('dashboard.youtube-channel.unlink') }}" onsubmit="return confirm('Disconnect this YouTube channel?')">
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
                @endif
            </x-dashboard.card>
        @endif
    </div>
</x-layout.page>
@endsection
