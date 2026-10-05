@extends('layouts.dashboard-admin')

@section('title', 'Ticket #'.$ticket->id)

@section('content')
<x-layout.page
    :title="$ticket->subject"
    :subtitle="\App\Models\SupportTicket::categoryLabel($ticket->category) . ' · ' . \App\Models\User::labelFor($ticket->user) . ' · opened ' . $ticket->created_at->format('M j, Y H:i')"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Support Tickets', route('admin.tickets')],
        ['Ticket', null],
    ]"
>
    <x-slot:actions>
        <x-dashboard.button :href="route('admin.tickets')" variant="secondary" size="sm">All tickets</x-dashboard.button>
    </x-slot:actions>

    <x-dashboard.card variant="solid" class="mb-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <x-dashboard.badge :status="$ticket->status" />
                <p class="mt-2 text-xs text-text-muted">Assignee: {{ $ticket->assignee?->name ?? 'Unassigned' }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="flex gap-2 items-end">
                    @csrf
                    <x-dashboard.select name="status" size="sm" label="Status">
                        @foreach (\App\Modules\Admin\Http\Controllers\SupportTicketAdminController::STATUSES as $s)
                            <option value="{{ $s }}" @selected($ticket->status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                        @endforeach
                    </x-dashboard.select>
                    <x-dashboard.button type="submit" variant="secondary" size="sm">Update</x-dashboard.button>
                </form>
                <form method="POST" action="{{ route('admin.tickets.assign', $ticket) }}" class="flex gap-2 items-end">
                    @csrf
                    <x-dashboard.select name="assigned_to" size="sm" label="Assign to">
                        <option value="">Unassigned</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}" @selected($ticket->assigned_to == $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </x-dashboard.select>
                    <x-dashboard.button type="submit" variant="secondary" size="sm">Assign</x-dashboard.button>
                </form>
            </div>
        </div>
        <div class="mt-4 text-text-secondary whitespace-pre-wrap text-sm">{{ $ticket->body }}</div>
        @include('dashboard.user.support._attachments', [
            'attachments' => ($ticket->attachments ?? collect())->whereNull('support_ticket_reply_id'),
        ])
    </x-dashboard.card>

    @foreach ($ticket->replies as $reply)
        <x-dashboard.card
            variant="solid"
            class="mb-3 {{ $reply->is_staff ? 'border-primary/30 bg-primary/5' : '' }}"
        >
            <p class="text-xs text-text-muted mb-1">
                {{ \App\Models\User::labelFor($reply->user) }}
                @if ($reply->is_staff)
                    <span class="text-primary font-medium">(Staff)</span>
                @endif
                — {{ $reply->created_at->format('M j, H:i') }}
            </p>
            <p class="text-text-secondary text-sm whitespace-pre-wrap">{{ $reply->body }}</p>
            @include('dashboard.user.support._attachments', [
                'attachments' => ($ticket->attachments ?? collect())->where('support_ticket_reply_id', $reply->id),
            ])
        </x-dashboard.card>
    @endforeach

    <x-dashboard.card variant="solid">
        <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <x-dashboard.textarea name="body" label="Staff reply" :rows="4" required />
            <div>
                <label class="block text-sm font-medium mb-1">Attachments</label>
                <input type="file" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.txt" class="block w-full text-sm">
            </div>
            <x-dashboard.button type="submit" variant="primary" x-bind:disabled="submitting">Send reply</x-dashboard.button>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
