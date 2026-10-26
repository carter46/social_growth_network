@extends('layouts.dashboard-admin')

@section('title', 'Historical account')

@section('content')
<x-layout.page
    title="Historical account"
    :subtitle="'Former user #'.$record->original_user_id"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Historical', route('admin.purged-accounts')],
        ['Record', null],
    ]"
>
    <x-dashboard.alert type="info" class="mb-4">
        This generation (original user ID {{ $record->original_user_id }}) was removed from live Trash and kept as a historical record. An anonymized soft-deleted tombstone may remain so wallet ledgers are not cascade-deleted. This record cannot be restored to Trash.
    </x-dashboard.alert>

    @if (! empty($liveWithSameEmail))
        <x-dashboard.alert type="warning" class="mb-4">
            A separate live account currently uses this email
            (<a href="{{ route('admin.users.show', $liveWithSameEmail) }}" class="font-semibold underline">user #{{ $liveWithSameEmail->id }}</a>).
            That registration is a new identity — not a restore of this historical generation.
        </x-dashboard.alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-dashboard.card class="space-y-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Identity</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-text-muted">Name</dt><dd class="font-medium text-text-primary">{{ $record->name ?: '—' }}</dd></div>
                <div><dt class="text-text-muted">Email</dt><dd class="font-medium text-text-primary">{{ $record->email ?: '—' }}</dd></div>
                <div><dt class="text-text-muted">Username</dt><dd class="font-medium text-text-primary">{{ $record->username ?: '—' }}</dd></div>
                <div><dt class="text-text-muted">Phone</dt><dd class="font-medium text-text-primary">{{ $record->phone ?: '—' }}</dd></div>
                <div><dt class="text-text-muted">Role</dt><dd class="font-medium text-text-primary capitalize">{{ $record->role ?: '—' }}</dd></div>
                <div><dt class="text-text-muted">Original user ID</dt><dd class="font-medium text-text-primary">{{ $record->original_user_id }}</dd></div>
            </dl>
        </x-dashboard.card>

        <x-dashboard.card class="space-y-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Lifecycle</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-text-muted">Registered</dt><dd class="font-medium text-text-primary">{{ $record->registered_at?->format('j M Y H:i') ?: '—' }}</dd></div>
                <div><dt class="text-text-muted">Suspended</dt><dd class="font-medium text-text-primary">{{ $record->suspended_at?->format('j M Y H:i') ?: '—' }}</dd></div>
                <div><dt class="text-text-muted">Trashed</dt><dd class="font-medium text-text-primary">{{ $record->trashed_at?->format('j M Y H:i') ?: '—' }}</dd></div>
                <div><dt class="text-text-muted">Purged</dt><dd class="font-medium text-text-primary">{{ $record->purged_at?->format('j M Y H:i') ?: '—' }}</dd></div>
                <div><dt class="text-text-muted">Reason / process</dt><dd class="font-medium text-text-primary">{{ $record->purge_reason }} · {{ $record->purge_process }}</dd></div>
            </dl>
        </x-dashboard.card>

        <x-dashboard.card class="space-y-3 lg:col-span-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Financial snapshot</h2>
            <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                <div><dt class="text-text-muted">Wallet balance at purge</dt><dd class="font-medium text-text-primary">{{ $record->wallet_balance_at_purge ?? '—' }}</dd></div>
                <div><dt class="text-text-muted">Locked balance at purge</dt><dd class="font-medium text-text-primary">{{ $record->wallet_locked_at_purge ?? '—' }}</dd></div>
            </dl>
            <div class="mt-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted mb-2">Ledger references</p>
                <pre class="overflow-x-auto rounded-lg bg-muted/40 p-3 text-xs text-text-secondary">{{ json_encode($record->transaction_refs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
            @if (! empty($record->login_security_refs))
                <div class="mt-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted mb-2">Related security / audit refs</p>
                    <pre class="overflow-x-auto rounded-lg bg-muted/40 p-3 text-xs text-text-secondary">{{ json_encode($record->login_security_refs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
