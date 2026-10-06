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
    </x-dashboard.card>

    @include('dashboard.user.support._thread', [
        'viewer' => 'staff',
        'replyAction' => route('admin.tickets.reply', $ticket),
        'submitLabel' => __('Send reply'),
    ])
</x-layout.page>
@endsection
