@extends('layouts.dashboard-admin')

@section('title', 'User Management')

@section('content')
@php
    $status = $status ?? 'active';
@endphp
<x-layout.page
    title="User Management"
    subtitle="Member accounts only. Administrators are managed separately."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Users', null],
    ]"
>
    <x-slot:actions>
        <x-dashboard.button :href="route('admin.users.create')" size="sm">Create user</x-dashboard.button>
    </x-slot:actions>

    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <x-dashboard.card class="mb-4">
        <form method="GET" action="{{ route('admin.users') }}" class="flex flex-wrap gap-3 items-end">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="min-w-[10rem]">
                <label class="mb-1 block text-xs font-medium text-text-secondary" for="role-filter">Role</label>
                <select id="role-filter" name="role" class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm text-text-primary">
                    <option value="all" @selected(($roleFilter ?? 'all') === 'all')>All members</option>
                    <option value="creator" @selected(($roleFilter ?? 'all') === 'creator')>Creators</option>
                    <option value="agent" @selected(($roleFilter ?? 'all') === 'agent')>Agents</option>
                </select>
            </div>
            <div class="min-w-[16rem] flex-1">
                <x-dashboard.input name="q" label="Search" :value="$search ?? ''" placeholder="Name, email, username..." />
            </div>
            <x-dashboard.button type="submit" variant="secondary">Search</x-dashboard.button>
        </form>
    </x-dashboard.card>

    <x-dashboard.ajax-tabs
        :active="$status"
        :tabs="[
            ['id' => 'active', 'label' => 'Active', 'href' => route('admin.users', array_filter(['status' => 'active', 'role' => ($roleFilter ?? 'all') !== 'all' ? ($roleFilter ?? null) : null, 'q' => $search ?? null])), 'count' => $activeCount ?? null],
            ['id' => 'suspended', 'label' => 'Suspended', 'href' => route('admin.users', array_filter(['status' => 'suspended', 'role' => ($roleFilter ?? 'all') !== 'all' ? ($roleFilter ?? null) : null, 'q' => $search ?? null])), 'count' => $suspendedCount ?? null],
        ]"
        class="mb-4"
    />

    <div id="dashboard-tab-panel">
        @include('dashboard.admin.users._table')
    </div>

    {{--
      One shared delete modal for the page — outside #dashboard-tab-panel so ajax-tabs
      destroyTree/initTree cannot tear it down. Rows only select a user + open this modal.
    --}}
    <div
        x-data="{
            userId: null,
            userLabel: '',
            action: '',
            reset() {
                this.userId = null;
                this.userLabel = '';
                this.action = '';
            },
        }"
        x-on:admin-delete-user.window="
            userId = $event.detail?.id ?? null;
            userLabel = $event.detail?.label ?? '';
            action = $event.detail?.action ?? '';
            if (userId && action) {
                $dispatch('open-modal', 'admin-delete-user');
            }
        "
        x-on:close-modal.window="
            if ($event.detail === 'admin-delete-user') reset();
        "
    >
        <x-dashboard.modal
            name="admin-delete-user"
            title="Permanently delete this user?"
            variant="danger"
            confirm-label="Permanently Delete"
        >
            <p class="text-sm text-text-secondary leading-relaxed">
                This anonymizes personal data for
                <span class="font-medium text-text-primary" x-text="userLabel || 'this user'"></span>
                and cannot be undone.
            </p>

            <x-slot:footer>
                <form
                    method="POST"
                    x-bind:action="action"
                    class="flex w-full flex-col-reverse gap-2.5 sm:flex-row sm:justify-end"
                    x-data="{ submitting: false }"
                    @submit="
                        if (!action || !userId) {
                            $event.preventDefault();
                            return;
                        }
                        submitting = true;
                    "
                >
                    @csrf
                    @method('DELETE')
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        @click="$dispatch('close-modal', 'admin-delete-user')"
                    >
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="danger"
                        x-bind:disabled="submitting || !action || !userId"
                    >
                        <span class="inline-flex items-center gap-2">
                            <span x-show="submitting" x-cloak><x-ui.icon name="spinner" class="w-4 h-4 animate-spin" /></span>
                            Permanently Delete
                        </span>
                    </x-ui.button>
                </form>
            </x-slot:footer>
        </x-dashboard.modal>
    </div>

    @include('dashboard.admin.users._manual-purchase-modal')
</x-layout.page>
@endsection
