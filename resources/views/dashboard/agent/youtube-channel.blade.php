@extends('layouts.dashboard-agent')

@section('title', 'YouTube channel')

@section('content')
<x-layout.page
    title="YouTube channel"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
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
            <h2 class="mb-3 text-sm font-semibold">Linked channel</h2>
            @include('dashboard.agent.partials.youtube-channel-link', [
                'identity' => $identity,
                'oauthEnabled' => $oauthEnabled,
                'returnTo' => $returnTo,
            ])
            @if ($returnTo && $identity?->isVerified())
                <x-dashboard.button :href="$returnTo" class="mt-4">Back to the task</x-dashboard.button>
            @endif
        </x-dashboard.card>

        @if ($identity?->isVerified())
            <x-dashboard.card>
                <h2 class="mb-3 text-sm font-semibold">Use a different channel</h2>
                @if ($changeRequest)
                    <p class="text-sm text-text-secondary">Your change request to {{ $changeRequest->title ?: $changeRequest->channel_url }} is waiting for admin review.</p>
                @else
                    <p class="mb-3 text-sm text-text-secondary">Changing channels needs admin approval. Your current channel stays linked until then.</p>
                    <form method="POST" action="{{ route('agent.youtube-channel.change') }}" class="flex gap-2">
                        @csrf
                        <input type="url" name="channel_url" required placeholder="https://www.youtube.com/@newchannel"
                               class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm">
                        <x-dashboard.button type="submit" size="sm" variant="secondary">Request change</x-dashboard.button>
                    </form>
                @endif
                @if ($identity->method === 'oauth')
                    <form method="POST" action="{{ route('agent.youtube-channel.oauth.disconnect') }}" class="mt-4">
                        @csrf
                        <x-dashboard.button type="submit" size="sm" variant="link">Remove Google access</x-dashboard.button>
                    </form>
                @endif
            </x-dashboard.card>
        @endif
    </div>
</x-layout.page>
@endsection
