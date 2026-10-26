@extends('layouts.dashboard-admin')

@section('title', 'Historical accounts')

@section('content')
<x-layout.page
    title="Historical accounts"
    subtitle="Accounts removed from live Trash after purge. Each generation keeps its original user ID. A later signup with the same email is a separate live identity — both can appear in search."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Trash', route('admin.trash')],
        ['Historical', null],
    ]"
>
    <form method="GET" action="{{ route('admin.purged-accounts') }}" class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
            <x-dashboard.input name="q" label="Search" :value="$q" placeholder="Email, name, username, phone, or user ID" />
        </div>
        <x-dashboard.button type="submit" variant="secondary">Search</x-dashboard.button>
    </form>

    @if ($q !== '' && isset($liveMatches) && $liveMatches->isNotEmpty())
        <x-dashboard.card :padding="false" class="mb-4">
            <div class="border-b border-border-subtle px-4 py-3">
                <h2 class="text-sm font-semibold text-text-primary">Current live accounts</h2>
                <p class="text-xs text-text-muted">Separate registrations matching this search — not merged with historical rows below.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-border-subtle bg-surface text-left text-xs font-semibold uppercase tracking-wide text-text-muted">
                        <tr>
                            <th class="px-4 py-3">Account</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">User ID</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach ($liveMatches as $live)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-text-primary">{{ $live->name }}</p>
                                    <p class="text-text-secondary">{{ $live->email }}</p>
                                </td>
                                <td class="px-4 py-3 text-text-secondary">
                                    {{ $live->hasRole('agent') ? 'Agent' : 'Creator' }}
                                </td>
                                <td class="px-4 py-3 text-text-secondary">{{ $live->id }}</td>
                                <td class="px-4 py-3 text-right">
                                    <x-dashboard.button :href="route('admin.users.show', $live)" size="sm" variant="secondary">Open user</x-dashboard.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-dashboard.card>
    @endif

    <x-dashboard.card :padding="false">
        <div class="border-b border-border-subtle px-4 py-3">
            <h2 class="text-sm font-semibold text-text-primary">Historical (purged) generations</h2>
        </div>
        @if ($records->isEmpty())
            <div class="p-6">
                <x-dashboard.empty
                    icon="audit"
                    title="No historical records"
                    description="When trash accounts are purged (automatically or by an admin), their identity and financial references appear here."
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-border-subtle bg-surface text-left text-xs font-semibold uppercase tracking-wide text-text-muted">
                        <tr>
                            <th class="px-4 py-3">Former account</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Purged</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach ($records as $record)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-text-primary">{{ $record->name ?: '—' }}</p>
                                    <p class="text-text-secondary">{{ $record->email ?: '—' }}</p>
                                    <p class="text-xs text-text-muted">Original ID {{ $record->original_user_id }}@if($record->username) · @{{ $record->username }}@endif</p>
                                </td>
                                <td class="px-4 py-3 text-text-secondary capitalize">{{ $record->role ?: '—' }}</td>
                                <td class="px-4 py-3 text-text-secondary">
                                    {{ $record->purged_at?->format('j M Y H:i') }}
                                    <span class="block text-xs text-text-muted">{{ $record->purge_reason }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-dashboard.button :href="route('admin.purged-accounts.show', $record)" size="sm" variant="secondary">View</x-dashboard.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border-subtle px-4 py-3">
                {{ $records->links() }}
            </div>
        @endif
    </x-dashboard.card>
</x-layout.page>
@endsection
