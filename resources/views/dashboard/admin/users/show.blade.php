@extends('layouts.dashboard-admin')

@section('title', $user->name)

@section('content')
@php
    $tabs = [
        ['id' => 'overview', 'label' => 'Overview', 'href' => route('admin.users.show', $user)],
        ['id' => 'wallet', 'label' => 'Wallet', 'href' => route('admin.users.wallet', $user)],
        ['id' => 'transactions', 'label' => 'Transactions', 'href' => route('admin.users.transactions', $user)],
        ['id' => 'orders', 'label' => 'Orders', 'href' => route('admin.users.orders', $user)],
        ['id' => 'tools', 'label' => 'Tools', 'href' => route('admin.users.tools', $user)],
        ['id' => 'tickets', 'label' => 'Support', 'href' => route('admin.users.tickets', $user)],
        ['id' => 'activity', 'label' => 'Activity', 'href' => route('admin.users.activity', $user)],
        ['id' => 'security', 'label' => 'Security', 'href' => route('admin.users.security', $user)],
    ];
@endphp
<x-layout.page
    title="{{ $user->name }}"
    subtitle="{{ $user->email }}"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Users', route('admin.users')],
        [$user->name, null],
    ]"
>
    <x-slot:actions>
        <span class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border-default bg-primary/15 text-sm font-semibold text-primary">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
            @else
                <span aria-hidden="true">{{ $user->initials() }}</span>
            @endif
        </span>
        @if (! $user->anonymized_at)
            <x-dashboard.button :href="route('admin.users.edit', $user)" variant="secondary" size="sm">Edit</x-dashboard.button>
            @if (! $user->is_suspended)
                <x-dashboard.button
                    type="button"
                    variant="secondary"
                    size="sm"
                    x-on:click="$dispatch('admin-manual-purchase', {
                        id: {{ (int) $user->id }},
                        label: {{ \Illuminate\Support\Js::from($user->name.' ('.$user->email.')') }},
                        action: {{ \Illuminate\Support\Js::from(route('admin.users.manual-purchase', $user)) }},
                    })"
                >
                    Manual purchase
                </x-dashboard.button>
                <form method="POST" action="{{ route('admin.users.impersonate', $user) }}">
                    @csrf
                    <x-dashboard.button type="submit" variant="secondary" size="sm">Login as user</x-dashboard.button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.users.password-reset', $user) }}">
                @csrf
                <x-dashboard.button type="submit" variant="secondary" size="sm">Send password reset</x-dashboard.button>
            </form>
            @if ($user->email_verified_at)
                <form method="POST" action="{{ route('admin.users.unverify-email', $user) }}">
                    @csrf
                    <x-dashboard.button type="submit" variant="secondary" size="sm">Unverify email</x-dashboard.button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.users.verify-email', $user) }}">
                    @csrf
                    <x-dashboard.button type="submit" variant="secondary" size="sm">Verify email</x-dashboard.button>
                </form>
            @endif
            @if (! ($wallet ?? $user->wallet) && $user->kyc_level >= 1)
                <form method="POST" action="{{ route('admin.users.provision-wallet', $user) }}">
                    @csrf
                    <x-dashboard.button type="submit" variant="secondary" size="sm">Provision wallet</x-dashboard.button>
                </form>
            @endif
            @if ($user->is_suspended)
                <form method="POST" action="{{ route('admin.users.restore', $user) }}">
                    @csrf
                    <x-dashboard.button type="submit" variant="success" size="sm">Restore</x-dashboard.button>
                </form>
                <x-dashboard.button
                    type="button"
                    variant="danger"
                    size="sm"
                    x-on:click="$dispatch('open-modal', 'delete-user-{{ $user->id }}')"
                >
                    Permanently Delete
                </x-dashboard.button>
                <x-dashboard.modal
                    name="delete-user-{{ $user->id }}"
                    title="Permanently delete this user?"
                    variant="danger"
                    confirm-label="Permanently Delete"
                    :form-action="route('admin.users.destroy', $user)"
                    method="DELETE"
                >
                    This anonymizes personal data and cannot be undone.
                </x-dashboard.modal>
            @else
                <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                    @csrf
                    <x-dashboard.button type="submit" variant="danger" size="sm">Suspend</x-dashboard.button>
                </form>
            @endif
        @endif
    </x-slot:actions>

    <div class="flex flex-wrap items-center gap-3 text-sm text-text-secondary">
        <x-dashboard.badge :status="$user->anonymized_at ? 'neutral' : ($user->is_suspended ? 'suspended' : 'active')" />
        <span>Joined {{ $user->created_at->format('j M Y') }}</span>
        @if ($user->username)
            <span>@{{ $user->username }}</span>
        @endif
    </div>

    @if (session('status'))
        <x-dashboard.alert type="success" class="mt-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mt-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <x-dashboard.ajax-tabs :tabs="$tabs" :active="$activeTab" class="mt-2" />

    <div id="dashboard-tab-panel" class="mt-6">
        @include('dashboard.admin.users.show-panel')
    </div>

    @include('dashboard.admin.users._manual-purchase-modal')
</x-layout.page>
@endsection
