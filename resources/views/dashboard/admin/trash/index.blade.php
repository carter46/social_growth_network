@extends('layouts.dashboard-admin')

@section('title', 'Trash')

@section('content')
<x-layout.page
    title="Trash"
    subtitle="Accounts moved here keep their wallet, campaigns and history. Restore them, or permanently delete them."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Trash', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <form method="GET" action="{{ route('admin.trash') }}" class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
            <x-dashboard.input name="q" label="Search" :value="$q" placeholder="Name, email or username" />
        </div>
        <x-dashboard.button type="submit" variant="secondary">Search</x-dashboard.button>
    </form>

    <x-dashboard.card :padding="false">
        @if ($users->isEmpty())
            <div class="p-6">
                <x-dashboard.empty
                    icon="users"
                    title="Trash is empty"
                    description="Suspended or deleted accounts you move to trash will show up here."
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-border-subtle bg-surface text-left text-xs font-semibold uppercase tracking-wide text-text-muted">
                        <tr>
                            <th class="px-4 py-3">Account</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Moved to trash</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-text-primary">{{ $user->name }}</p>
                                    <p class="text-text-secondary">{{ $user->email }}</p>
                                    @if ($user->username)
                                        <p class="text-xs text-text-muted">@{{ $user->username }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-text-secondary">
                                    {{ $user->hasRole('agent') ? 'Agent' : 'Creator' }}
                                </td>
                                <td class="px-4 py-3 text-text-secondary">
                                    {{ $user->deleted_at?->format('j M Y H:i') }}
                                    <span class="block text-xs text-text-muted">{{ $user->deleted_at?->diffForHumans() }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.trash.restore', $user->id) }}">
                                            @csrf
                                            <x-dashboard.button type="submit" variant="success" size="sm">Restore</x-dashboard.button>
                                        </form>
                                        <x-dashboard.button
                                            type="button"
                                            variant="danger"
                                            size="sm"
                                            x-on:click="$dispatch('open-modal', 'purge-user-{{ $user->id }}')"
                                        >
                                            Delete forever
                                        </x-dashboard.button>
                                        <x-dashboard.modal
                                            name="purge-user-{{ $user->id }}"
                                            title="Permanently delete this account?"
                                            variant="danger"
                                            confirm-label="Delete forever"
                                            :form-action="route('admin.trash.destroy', $user->id)"
                                            method="DELETE"
                                        >
                                            This removes personal data and cannot be undone. Wallet history and related records may remain for compliance.
                                        </x-dashboard.modal>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border-subtle px-4 py-3">
                {{ $users->links() }}
            </div>
        @endif
    </x-dashboard.card>
</x-layout.page>
@endsection
