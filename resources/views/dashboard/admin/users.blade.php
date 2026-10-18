@extends('layouts.dashboard-admin')

@section('title', 'User Management')

@section('content')
@php
    $status = $status ?? 'active';
@endphp
<div
    x-data="{
        selected: [],
        status: @js($status),
        toggleAll(event, ids) {
            this.selected = event.target.checked ? [...ids] : [];
        },
    }"
    @dashboard-tab-navigated.window="selected = []; status = $event.detail?.id || status"
>
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
        <form
            method="GET"
            action="{{ route('admin.users') }}"
            class="flex flex-wrap gap-3 items-end"
            x-data="{
                role: @js($roleFilter ?? 'all'),
                q: @js($search ?? ''),
                loading: false,
                timer: null,
                controller: null,
                suggestions: [],
                suggestOpen: false,
                activeIndex: -1,
                suggestController: null,
                queue() {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => { this.load(); this.suggest(); }, 250);
                },
                async suggest() {
                    const term = this.q.trim();
                    this.suggestController?.abort();
                    if (term === '') { this.suggestions = []; this.suggestOpen = false; return; }
                    this.suggestController = new AbortController();
                    const params = new URLSearchParams({ q: term });
                    if (this.role !== 'all') params.set('role', this.role);
                    try {
                        const res = await fetch(@js(route('admin.users')) + '?' + params.toString(), {
                            headers: { 'X-Users-Suggest': '1', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                            signal: this.suggestController.signal,
                        });
                        if (!res.ok) return;
                        const json = await res.json();
                        this.suggestions = (json.data || []).slice(0, 5);
                        this.activeIndex = -1;
                        this.suggestOpen = true;
                    } catch (e) {}
                },
                moveSuggestion(step) {
                    if (!this.suggestOpen || !this.suggestions.length) return;
                    const count = this.suggestions.length;
                    this.activeIndex = (this.activeIndex + step + count) % count;
                },
                pickSuggestion(event) {
                    if (this.suggestOpen && this.activeIndex >= 0 && this.suggestions[this.activeIndex]) {
                        event.preventDefault();
                        window.location.href = this.suggestions[this.activeIndex].url;
                    }
                },
                async load() {
                    clearTimeout(this.timer);
                    const params = new URLSearchParams({ status: status || 'active' });
                    if (this.role !== 'all') params.set('role', this.role);
                    if (this.q.trim() !== '') params.set('q', this.q.trim());
                    const url = @js(route('admin.users')) + '?' + params.toString();
                    const target = document.getElementById('users-results');
                    if (!target) return;
                    this.controller?.abort();
                    this.controller = new AbortController();
                    this.loading = true;
                    try {
                        const res = await fetch(url, {
                            headers: { 'X-Users-Live': '1', Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                            signal: this.controller.signal,
                        });
                        if (!res.ok) { window.location.href = url; return; }
                        const html = await res.text();
                        window.Alpine?.destroyTree?.(target);
                        target.innerHTML = html;
                        window.Alpine?.initTree?.(target);
                        selected = [];
                        history.replaceState(history.state, '', url);
                    } catch (e) {
                        if (e?.name !== 'AbortError') window.location.href = url;
                    } finally {
                        this.loading = false;
                    }
                },
            }"
            @submit.prevent="load()"
        >
            <div class="min-w-[10rem]">
                <label class="mb-1 block text-xs font-medium text-text-secondary" for="role-filter">Role</label>
                <select id="role-filter" name="role" x-model="role" @change="load(); suggestOpen = false" class="w-full rounded-lg border border-border-default bg-surface px-3 py-2 text-sm text-text-primary">
                    <option value="all" @selected(($roleFilter ?? 'all') === 'all')>All members</option>
                    <option value="creator" @selected(($roleFilter ?? 'all') === 'creator')>Creators</option>
                    <option value="agent" @selected(($roleFilter ?? 'all') === 'agent')>Agents</option>
                </select>
            </div>
            <div class="relative min-w-[16rem] flex-1" @click.outside="suggestOpen = false">
                <x-dashboard.input
                    name="q"
                    id="users-search"
                    type="search"
                    label="Search"
                    :value="$search ?? ''"
                    placeholder="Start typing a name, email or username..."
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="users-search-suggestions"
                    x-bind:aria-expanded="(suggestOpen && suggestions.length > 0).toString()"
                    x-model="q"
                    @input="queue()"
                    @focus="if (suggestions.length) suggestOpen = true"
                    @keydown.arrow-down.prevent="moveSuggestion(1)"
                    @keydown.arrow-up.prevent="moveSuggestion(-1)"
                    @keydown.enter="pickSuggestion($event)"
                    @keydown.escape="suggestOpen = false"
                />
                <ul
                    id="users-search-suggestions"
                    role="listbox"
                    class="absolute left-0 right-0 top-full z-30 mt-1 overflow-hidden rounded-xl border border-border-default bg-surface shadow-lg"
                    x-show="suggestOpen && q.trim() !== ''"
                    x-cloak
                >
                    <template x-for="(user, index) in suggestions" :key="user.id">
                        <li role="option" :aria-selected="(index === activeIndex).toString()">
                            <a
                                :href="user.url"
                                class="flex items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-muted focus-ring"
                                :class="index === activeIndex ? 'bg-muted' : ''"
                                @mouseenter="activeIndex = index"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-text-primary" x-text="user.name"></span>
                                    <span class="block truncate text-xs text-text-muted" x-text="user.email"></span>
                                </span>
                                <span class="flex shrink-0 flex-wrap justify-end gap-1">
                                    <x-dashboard.badge status="info" x-show="user.roles.includes('Creator')">Creator</x-dashboard.badge>
                                    <x-dashboard.badge status="warning" x-show="user.roles.includes('Agent')">Agent</x-dashboard.badge>
                                    <x-dashboard.badge status="suspended" x-show="user.suspended" />
                                </span>
                            </a>
                        </li>
                    </template>
                    <li x-show="suggestions.length === 0" class="px-3 py-2 text-sm text-text-muted">No matching users</li>
                </ul>
            </div>
            <span class="inline-flex h-10 items-center gap-1 text-xs text-text-muted" x-show="loading" x-cloak aria-live="polite">
                <x-ui.icon name="spinner" class="h-3.5 w-3.5 animate-spin" />
                Searching…
            </span>
        </form>
    </x-dashboard.card>

    <div class="mb-4 flex flex-wrap items-center gap-2" x-show="selected.length" x-cloak>
        <form x-ref="bulkSuspendForm" method="POST" action="{{ route('admin.users.bulk-suspend') }}" class="hidden">
            @csrf
            <template x-for="id in selected" :key="'suspend-' + id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
        </form>
        <form x-ref="bulkRestoreForm" method="POST" action="{{ route('admin.users.bulk-restore') }}" class="hidden">
            @csrf
            <template x-for="id in selected" :key="'restore-' + id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
        </form>
        <form x-ref="bulkDestroyForm" method="POST" action="{{ route('admin.users.bulk-destroy') }}" class="hidden">
            @csrf
            @method('DELETE')
            <template x-for="id in selected" :key="'destroy-' + id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
        </form>

        <template x-if="status === 'active'">
            <x-dashboard.button
                type="button"
                variant="danger"
                size="sm"
                @click="$dispatch('open-modal', 'bulk-suspend-users')"
            >
                Suspend selected (<span x-text="selected.length"></span>)
            </x-dashboard.button>
        </template>
        <template x-if="status === 'suspended'">
            <div class="flex flex-wrap items-center gap-2">
                <x-dashboard.button
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="$dispatch('open-modal', 'bulk-restore-users')"
                >
                    Restore selected (<span x-text="selected.length"></span>)
                </x-dashboard.button>
                <x-dashboard.button
                    type="button"
                    variant="danger"
                    size="sm"
                    @click="$dispatch('open-modal', 'bulk-delete-users')"
                >
                    Permanently delete selected (<span x-text="selected.length"></span>)
                </x-dashboard.button>
            </div>
        </template>
    </div>

    <div id="users-results">
        @include('dashboard.admin.users._results')
    </div>

    <div @modal-confirmed.window="
        if ($event.detail === 'bulk-suspend-users') $refs.bulkSuspendForm?.submit();
        if ($event.detail === 'bulk-restore-users') $refs.bulkRestoreForm?.submit();
        if ($event.detail === 'bulk-delete-users') $refs.bulkDestroyForm?.submit();
    ">
        <x-dashboard.modal
            name="bulk-suspend-users"
            title="Suspend selected users?"
            variant="danger"
            confirm-label="Suspend selected"
        >
            Suspended users lose access until restored.
        </x-dashboard.modal>
        <x-dashboard.modal
            name="bulk-restore-users"
            title="Restore selected users?"
            confirm-label="Restore selected"
        >
            Selected accounts will regain access immediately.
        </x-dashboard.modal>
        <x-dashboard.modal
            name="bulk-delete-users"
            title="Permanently delete selected users?"
            variant="danger"
            confirm-label="Permanently delete"
        >
            This anonymizes personal data for the selected suspended accounts and cannot be undone.
        </x-dashboard.modal>
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
</x-layout.page>
</div>
@endsection
