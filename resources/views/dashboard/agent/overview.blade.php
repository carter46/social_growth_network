@extends('layouts.dashboard-agent')

@section('title', 'Agent Dashboard')

@section('content')
<x-layout.page
    title="Welcome back, {{ auth()->user()->name ?? 'Agent' }}"
    width="full"
    :breadcrumb="[
        ['Agent', route('agent')],
        ['Overview', null],
    ]"
>
    <div class="space-y-4">
        @php($channelBannerKey = 'agent_channel_banner_dismissed:'.auth()->id())
        @if ($showChannelBanner ?? false)
            <div
                x-data="{ key: @js($channelBannerKey), open: false }"
                x-init="open = localStorage.getItem(key) !== '1'"
                x-show="open"
                x-cloak
                data-channel-banner
            >
                <x-dashboard.alert type="info" title="Connect your YouTube channel">
                    <p>You'll need a connected channel before you apply for a YouTube task.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-dashboard.button :href="route('agent.youtube-channel.show')" size="sm">Connect channel</x-dashboard.button>
                        <x-dashboard.button
                            variant="secondary"
                            size="sm"
                            x-on:click="open = false; localStorage.setItem(key, '1')"
                        >Dismiss</x-dashboard.button>
                    </div>
                </x-dashboard.alert>
            </div>
        @else
            {{-- Linked: forget the dismissal so the reminder returns if the channel is later removed. --}}
            <script data-channel-banner-reset>try { localStorage.removeItem(@js($channelBannerKey)); } catch (e) {}</script>
        @endif

        <x-dashboard.stats-card
            label="Available Balance"
            :value="money($availableBalance ?? 0, 2)"
            :hint="'Locked: '.money($lockedBalance ?? 0, 2)"
            icon="wallet"
        >
            <div class="mt-4 flex flex-wrap gap-2">
                <x-dashboard.button :href="route('agent.withdrawal.create')" size="sm" icon="withdraw">Withdraw</x-dashboard.button>
                <x-dashboard.button :href="route('agent.wallet')" variant="secondary" size="sm">View wallet</x-dashboard.button>
            </div>
        </x-dashboard.stats-card>

        <div class="grid grid-cols-2 gap-4">
            <x-dashboard.stats-card
                label="Active tasks"
                :value="(string) ($activeTasks ?? 0)"
                hint="In progress"
                icon="orders"
                :href="route('agent.tasks.active')"
            />
            <x-dashboard.stats-card
                label="Completed"
                :value="(string) ($completedTasks ?? 0)"
                hint="Approved & paid"
                icon="verified"
                :href="route('agent.tasks.completed')"
            />
        </div>

        <x-dashboard.card>
            <h2 class="text-base font-semibold text-text-primary">Earn with tasks</h2>
            <p class="mt-1 text-sm text-text-secondary">Browse open campaigns, complete requirements, and withdraw earnings after verification.</p>
            <div class="mt-4">
                <x-dashboard.button :href="route('agent.marketplace')" icon="listings">Open marketplace</x-dashboard.button>
            </div>
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
