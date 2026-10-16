@extends('layouts.dashboard-agent')

@section('title', 'Get started')

@section('content')
<x-layout.page
    title="Welcome, {{ auth()->user()->name ?? 'Agent' }}"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Get started', null],
    ]"
>
    <div class="max-w-2xl space-y-4">
        <x-dashboard.card>
            <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">One quick step</p>
            <h2 class="mt-1 text-lg font-semibold text-text-primary">Connect your YouTube channel</h2>
            <p class="mt-1 text-sm text-text-secondary">
                YouTube tasks are completed from your own channel. You can connect it now or later, but you'll need a connected channel before you apply for a YouTube task.
            </p>

            @if ($channelVerified)
                <x-dashboard.alert type="success" class="mt-4">
                    Connected: {{ $identity->title ?: $identity->handle }}
                </x-dashboard.alert>
                <div class="mt-4">
                    <x-dashboard.button :href="route('agent.marketplace')" icon="listings">Continue</x-dashboard.button>
                </div>
            @else
                @if ($identity?->isPending())
                    <x-dashboard.alert type="info" class="mt-4">
                        Your channel check is in progress. You can finish it from the YouTube channel page.
                    </x-dashboard.alert>
                @endif
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-dashboard.button :href="route('agent.youtube-channel.show', ['return' => $returnPath])">Connect YouTube channel</x-dashboard.button>
                    <x-dashboard.button :href="route('agent')" variant="secondary">Skip for now</x-dashboard.button>
                </div>
            @endif
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
