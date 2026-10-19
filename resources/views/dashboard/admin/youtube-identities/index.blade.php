@extends('layouts.dashboard-admin')

@section('title', 'YouTube channels')

@section('content')
@php
    $badgeFor = [
        'pending' => 'pending',
        'verified' => 'verified',
        'failed' => 'failed',
        'replaced' => 'neutral',
        'rejected' => 'rejected',
        'change_req' => 'warning',
    ];
@endphp
<x-layout.page
    title="YouTube channels"
    subtitle="Channels linked by agents (required for YouTube tasks) and creators (optional). When the YouTube Data API key is set, description codes are checked automatically. This page is for manual fallbacks, change requests, and revokes."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['YouTube channels', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif
    @if (empty($youtubeApiReady))
        <x-dashboard.alert type="warning" class="mb-4" data-youtube-api-key-missing>
            No YouTube Data API key is configured, so the system cannot read channel descriptions to confirm codes.
            Every new link lands in <strong>Needs review</strong> for manual approval.
            Add the key under Admin → API integrations to turn automatic verification on.
        </x-dashboard.alert>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($filters as $key => $label)
            <a
                href="{{ route('admin.youtube-identities.index', array_filter(['filter' => $key, 'role' => $role, 'q' => request('q')])) }}"
                @class([
                    'rounded-full px-3 py-1 text-sm',
                    'bg-primary text-white' => $filter === $key,
                    'bg-muted text-text-secondary hover:text-text-primary' => $filter !== $key,
                ])
            >
                {{ $label }}@if (isset($counts[$key]) && $counts[$key] > 0) ({{ $counts[$key] }})@endif
            </a>
        @endforeach
        <form method="GET" action="{{ route('admin.youtube-identities.index') }}" class="ml-auto flex items-center gap-2">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <select name="role" onchange="this.form.submit()" aria-label="Account type" class="rounded-lg border border-border-default bg-surface px-3 py-1.5 text-sm">
                <option value="" @selected(! $role)>Agents and creators</option>
                <option value="agent" @selected($role === 'agent')>Agents</option>
                <option value="user" @selected($role === 'user')>Creators</option>
            </select>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Channel, handle or email" class="rounded-lg border border-border-default bg-surface px-3 py-1.5 text-sm">
            <x-dashboard.button type="submit" variant="secondary" size="sm">Search</x-dashboard.button>
        </form>
    </div>

    <x-dashboard.table
        :empty="$identities->isEmpty()"
        empty-title="Nothing here"
        empty-description="No linked channels match this filter."
        empty-icon="verified"
        striped
    >
        <x-slot:head>
            <x-dashboard.th>Account</x-dashboard.th>
            <x-dashboard.th>Channel</x-dashboard.th>
            <x-dashboard.th>Status</x-dashboard.th>
            <x-dashboard.th>Method</x-dashboard.th>
            <x-dashboard.th>Actions</x-dashboard.th>
        </x-slot:head>
        @foreach ($identities as $identity)
            <tr class="align-top">
                <x-dashboard.td>
                    <p class="font-medium text-text-primary">{{ $identity->user?->name }}</p>
                    <p class="text-xs text-text-muted">{{ $identity->user?->email }}</p>
                    @if ($identity->user)
                        <x-dashboard.badge :status="$identity->user->isAgent() ? 'info' : 'neutral'" class="mt-1">{{ $identity->user->isAgent() ? 'Agent' : 'Creator' }}</x-dashboard.badge>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>
                    @if ($identity->channel_url)
                        <a href="{{ $identity->channel_url }}" target="_blank" rel="noopener" class="font-medium text-accent underline">{{ $identity->title ?: ($identity->handle ?: $identity->channel_url) }}</a>
                    @else
                        <span class="font-medium">{{ $identity->title ?: '-' }}</span>
                    @endif
                    <p class="font-mono text-xs text-text-muted">{{ $identity->channel_id ?: 'Channel ID unknown' }}</p>
                    @if ($identity->verification_code && $identity->isPending())
                        <p class="text-xs text-text-muted">Code: <span class="font-mono">{{ $identity->verification_code }}</span></p>
                    @endif
                    @if ($identity->failure_reason)
                        <p class="text-xs text-danger">{{ $identity->failure_reason }}</p>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.badge :status="$badgeFor[$identity->status] ?? 'neutral'">{{ $identity->statusLabel() }}</x-dashboard.badge>
                    <p class="mt-1 text-xs text-text-muted">{{ $identity->created_at?->format('Y-m-d H:i') }}</p>
                </x-dashboard.td>
                <x-dashboard.td>{{ ucfirst((string) $identity->method) }}</x-dashboard.td>
                <x-dashboard.td>
                    <div class="space-y-2 min-w-[14rem]">
                        @if (in_array($identity->status, ['pending', 'failed'], true))
                            <form method="POST" action="{{ route('admin.youtube-identities.approve', $identity) }}" class="flex items-center gap-2">
                                @csrf
                                <input type="text" name="channel_id" value="{{ $identity->channel_id }}" placeholder="UC..." class="w-40 rounded-lg border border-border-default bg-surface px-2 py-1 font-mono text-xs">
                                <x-dashboard.button type="submit" size="xs">Approve</x-dashboard.button>
                            </form>
                        @endif
                        @if ($identity->status === 'change_req')
                            <form method="POST" action="{{ route('admin.youtube-identities.approve-change', $identity) }}">
                                @csrf
                                <x-dashboard.button type="submit" size="xs">Approve change</x-dashboard.button>
                            </form>
                        @endif
                        @if (in_array($identity->status, ['pending', 'failed', 'change_req', 'verified'], true))
                            <form method="POST" action="{{ route('admin.youtube-identities.reject', $identity) }}" class="flex items-center gap-2">
                                @csrf
                                <input type="text" name="reason" required maxlength="500" placeholder="Reason" class="w-40 rounded-lg border border-border-default bg-surface px-2 py-1 text-xs">
                                <x-dashboard.button type="submit" variant="secondary" size="xs">{{ $identity->status === 'verified' ? 'Revoke' : 'Reject' }}</x-dashboard.button>
                            </form>
                        @endif
                    </div>
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <div class="mt-4">{{ $identities->links() }}</div>
</x-layout.page>
@endsection
