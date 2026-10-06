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

    @php
        $imageUrl = $campaign->product
            ? (media_url($campaign->product->heroMedia ?? null, $campaign->product->hero_image, 'medium') ?: $campaign->product->listThumbnailUrl())
            : null;
    @endphp

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @if ($imageUrl)
            <x-dashboard.card :padding="false" class="overflow-hidden">
                <img src="{{ $imageUrl }}" alt="{{ $campaign->product?->title }}" class="aspect-video h-full w-full object-cover">
            </x-dashboard.card>
        @endif

        <x-dashboard.card class="space-y-3 {{ $imageUrl ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            @include('dashboard.agent.partials.campaign-tiles', ['campaign' => $campaign, 'reward' => (float) $campaign->locked_agent_reward])

            <div class="pt-1">
                @if ($participation)
                    <x-dashboard.button :href="route('agent.tasks.show', $participation)">Continue task</x-dashboard.button>
                @elseif ($campaign->isOpenForAgents())
                    <form method="POST" action="{{ route('agent.marketplace.start', $campaign) }}">
                        @csrf
                        <x-dashboard.button type="submit" icon="plus">Start task</x-dashboard.button>
                    </form>
                @else
                    <x-dashboard.alert type="warning">This campaign is no longer open.</x-dashboard.alert>
                @endif
            </div>
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
