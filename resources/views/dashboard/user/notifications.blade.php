@extends($layout ?? 'layouts.dashboard-user')

@section('title', 'Notifications')

@section('content')
@php
    $notificationReadAll = $notificationReadAll
        ?? (($notificationPrefix ?? 'dashboard') === 'admin.inbox'
            ? 'admin.inbox.read-all'
            : 'dashboard.notifications.read-all');
    $notificationRead = $notificationRead
        ?? (($notificationPrefix ?? 'dashboard') === 'admin.inbox'
            ? 'admin.inbox.read'
            : 'dashboard.notifications.read');
@endphp
<x-layout.page
    title="Notifications"
    width="full"
    :breadcrumb="[
        ['Dashboard', url(auth()->user()->homeRoute())],
        ['Notifications', null],
    ]"
>
    <x-slot:actions>
        <form method="POST" action="{{ route($notificationReadAll) }}" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <x-dashboard.button type="submit" variant="ghost" size="sm" x-bind:disabled="submitting">Mark all read</x-dashboard.button>
        </form>
    </x-slot:actions>

    <x-dashboard.card :padding="false">
        @if ($notifications->isEmpty())
            <x-dashboard.empty
                icon="notifications"
                title="No notifications yet"
                description="Order updates, listing decisions, and message alerts will show up here."
            />
        @else
            <div class="divide-y divide-border-default">
                @foreach ($notifications as $notification)
                    <div class="px-6 py-4 {{ $notification->read_at ? 'opacity-70' : '' }}">
                        @if (! $notification->read_at && $notification->action_url)
                            <form method="POST" action="{{ route($notificationRead, $notification) }}">
                                @csrf
                                <x-dashboard.button type="submit" variant="ghost" class="!h-auto !w-full !justify-start !px-0 !py-0 text-left whitespace-normal">
                                    <span class="block w-full text-left">
                                        <span class="block text-text-primary font-medium">{{ $notification->title }}</span>
                                        @if ($notification->body)
                                            <span class="block text-text-secondary text-sm mt-1 font-normal">{{ $notification->body }}</span>
                                        @endif
                                        <span class="block text-text-muted text-xs mt-1 font-normal">{{ $notification->created_at->diffForHumans() }}</span>
                                    </span>
                                </x-dashboard.button>
                            </form>
                        @else
                            <p class="text-text-primary font-medium">{{ $notification->title }}</p>
                            @if ($notification->body)
                                <p class="text-text-secondary text-sm mt-1">{{ $notification->body }}</p>
                            @endif
                            <div class="flex flex-wrap items-center gap-3 mt-1">
                                <p class="text-text-muted text-xs">{{ $notification->created_at->diffForHumans() }}</p>
                                @if (! $notification->read_at)
                                    <form method="POST" action="{{ route($notificationRead, $notification) }}" x-data="{ submitting: false }" @submit="submitting = true">
                                        @csrf
                                        <x-dashboard.button type="submit" variant="link" size="xs" x-bind:disabled="submitting">Mark read</x-dashboard.button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-dashboard.card>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$notifications" />
    </x-slot:pagination>
</x-layout.page>
@endsection
