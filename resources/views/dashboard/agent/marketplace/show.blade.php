@extends('layouts.dashboard-agent')

@section('title', $campaign->title)

@section('content')
<x-layout.page
    title="{{ $campaign->title }}"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Marketplace', route('agent.marketplace')],
        [$campaign->title, null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <div class="grid grid-cols-1 gap-4">
        <x-dashboard.card class="space-y-3">
            @include('dashboard.agent.partials.campaign-tiles', [
                'campaign' => $campaign,
                'reward' => (float) $campaign->locked_agent_reward,
                'embed' => $embed ?? null,
            ])

            @php $profile = $campaign->taskProfile(); @endphp
            @if ($campaign->completion_minutes && ! $participation)
                <p class="text-sm text-text-secondary">You'll have <strong>{{ $campaign->completion_minutes }} minutes</strong> to finish {{ $profile->usesStepFlow() ? 'all steps' : 'this task' }} once you start. That countdown is separate from the watch timer inside the task.</p>
            @endif

            <div class="pt-1">
                @if ($participation)
                    <x-dashboard.button :href="route('agent.tasks.show', $participation)">Continue task</x-dashboard.button>
                @elseif ($campaign->isOpenForAgents())
                    @if ($needsChannel)
                        <x-dashboard.button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'youtube-channel')">Start task</x-dashboard.button>
                    @else
                        <form method="POST" action="{{ route('agent.marketplace.start', $campaign) }}">
                            @csrf
                            <x-dashboard.button type="submit" icon="plus">Start task</x-dashboard.button>
                        </form>
                    @endif
                @else
                    <x-dashboard.alert type="warning">This campaign is no longer open.</x-dashboard.alert>
                @endif
            </div>
        </x-dashboard.card>
    </div>

    @if ($needsChannel)
        <x-ui.modal name="youtube-channel" title="Link your YouTube channel" x-init="{{ session('youtube_channel_required') ? '$nextTick(() => openModal())' : '' }}">
            <p class="mb-3">You need a verified YouTube channel before starting YouTube tasks. This is a one-time step.</p>
            @include('partials.youtube-channel.link', [
                'prefix' => 'agent',
                'identity' => $channelIdentity,
                'oauthEnabled' => $oauthEnabled,
                'returnTo' => '/'.ltrim(parse_url(route('agent.marketplace.show', $campaign), PHP_URL_PATH), '/'),
            ])
            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'youtube-channel')">Close</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</x-layout.page>
@endsection
