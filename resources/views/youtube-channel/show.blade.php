@extends($layout)

@section('title', 'YouTube channel')

@section('content')
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

    <div class="grid gap-4 lg:grid-cols-2">
        <x-dashboard.card>
            <h2 class="mb-3 text-sm font-semibold">{{ $isAgent ? 'Linked channel' : 'Your channel' }}</h2>
            @include('partials.youtube-channel.link', [
                'identity' => $identity,
                'prefix' => $prefix,
                'oauthEnabled' => $oauthEnabled,
                'returnTo' => $returnTo,
                'channelMissing' => $channelMissing,
            ])
            @if ($returnTo && $identity?->isVerified())
                <x-dashboard.button :href="$returnTo" class="mt-4">Continue</x-dashboard.button>
            @endif
        </x-dashboard.card>

        @if ($identity?->isVerified())
            <x-dashboard.card>
                <h2 class="mb-3 text-sm font-semibold">Use a different channel</h2>
                @if ($isAgent)
                    @if ($changeRequest)
                        <p class="text-sm text-text-secondary">Your change request to {{ $changeRequest->title ?: $changeRequest->channel_url }} is waiting for admin review.</p>
                    @else
                        <p class="mb-3 text-sm text-text-secondary">Changing channels needs admin approval. Your current channel stays linked until then.</p>
                        <form method="POST" action="{{ route('agent.youtube-channel.change') }}" class="flex gap-2">
                            @csrf
                            <input type="text" inputmode="url" name="channel_url" required placeholder="https://www.youtube.com/@newchannel"
                                   class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">
                            <x-dashboard.button type="submit" size="sm" variant="secondary">Request change</x-dashboard.button>
                        </form>
                    @endif
                @else
                    <p class="mb-3 text-sm text-text-secondary">Disconnect this channel to connect a different one. Your campaigns are not affected.</p>
                    <form method="POST" action="{{ route('dashboard.youtube-channel.unlink') }}" onsubmit="return confirm('Disconnect this YouTube channel?')">
                        @csrf
                        <x-dashboard.button type="submit" size="sm" variant="secondary">Disconnect channel</x-dashboard.button>
                    </form>
                @endif
                @if ($identity->method === 'oauth')
                    <form method="POST" action="{{ route($prefix.'.youtube-channel.oauth.disconnect') }}" class="mt-4">
                        @csrf
                        <x-dashboard.button type="submit" size="sm" variant="link">Remove Google access</x-dashboard.button>
                    </form>
                @endif
            </x-dashboard.card>
        @endif
    </div>
</x-layout.page>
@endsection
