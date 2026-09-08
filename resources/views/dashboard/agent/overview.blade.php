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
        <x-dashboard.stats-card
            label="Available Balance"
            :value="'₦' . number_format($balanceNgn ?? 0, 2)"
            :hint="'Locked: ₦' . number_format($lockedNgn ?? 0, 2)"
            icon="wallet"
            :href="route('agent.wallet')"
        />

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
