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

            <div class="pt-1">
                @if ($participation)
                    <x-dashboard.button :href="route('agent.tasks.show', $participation)">Continue task</x-dashboard.button>
                @elseif ($campaign->isOpenForAgents())
                    @if ($needsChannel)
                        <x-dashboard.button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'youtube-channel')">Start task</x-dashboard.button>
                    @else
                        <form
                            method="POST"
                            action="{{ route('agent.marketplace.start', $campaign) }}"
                            x-data="{ starting: false, error: '' }"
                            @submit.prevent="
                                if (starting) return;
                                starting = true;
                                error = '';
                                fetch($el.action, {
                                    method: 'POST',
                                    headers: {
                                        Accept: 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: new FormData($el),
                                }).then(async (response) => {
                                    const data = await response.json().catch(() => ({}));
                                    if (! response.ok || ! data.redirect) {
                                        error = data.message || 'Could not start this task.';
                                        starting = false;
                                        return;
                                    }
                                    const page = await fetch(data.redirect, {
                                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                        credentials: 'same-origin',
                                    });
                                    const html = await page.text();
                                    const doc = new DOMParser().parseFromString(html, 'text/html');
                                    const next = doc.querySelector('#main-content');
                                    const current = document.querySelector('#main-content');
                                    if (! next || ! current) {
                                        window.location = data.redirect;
                                        return;
                                    }
                                    if (window.Alpine?.destroyTree) window.Alpine.destroyTree(current);
                                    current.innerHTML = next.innerHTML;
                                    if (doc.title) document.title = doc.title;
                                    if (! window.__agentTaskNavBound) {
                                        window.__agentTaskNavBound = true;
                                        window.addEventListener('popstate', () => window.location.reload());
                                    }
                                    history.pushState({}, '', data.redirect);
                                    window.Alpine?.initTree(current);
                                }).catch(() => {
                                    error = 'Could not start this task. Please try again.';
                                    starting = false;
                                })
                            "
                        >
                            @csrf
                            <x-dashboard.button type="submit" icon="plus" x-bind:disabled="starting">
                                <span x-text="starting ? 'Starting...' : 'Start task'">Start task</span>
                            </x-dashboard.button>
                            <p class="mt-2 text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
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
