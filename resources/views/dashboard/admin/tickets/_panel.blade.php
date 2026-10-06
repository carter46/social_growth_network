<x-dashboard.table
    :empty="$tickets->isEmpty()"
    empty-title="No support tickets"
    empty-description="Tickets matching this view will appear here."
    empty-icon="support"
    striped
>
    <x-slot:head>
        <x-dashboard.th>User</x-dashboard.th>
        <x-dashboard.th>Category</x-dashboard.th>
        <x-dashboard.th>Subject</x-dashboard.th>
        <x-dashboard.th>Status</x-dashboard.th>
        <x-dashboard.th>Assignee</x-dashboard.th>
        <x-dashboard.th>Actions</x-dashboard.th>
    </x-slot:head>

    @foreach ($tickets as $ticket)
        @php
            $ticketUser = $ticket->user;
            $ticketUserLabel = ! $ticketUser || $ticketUser->isAnonymized()
                ? __('Deleted User')
                : ($ticketUser->username ? '@'.$ticketUser->username : $ticketUser->displayName());
        @endphp
        <tr class="hover:bg-muted/50">
            <x-dashboard.td>
                <span class="block font-medium text-text-primary">{{ $ticketUserLabel }}</span>
                <a href="{{ route('admin.tickets.show', $ticket) }}" class="mt-0.5 block text-xs text-primary hover:underline">#{{ $ticket->id }}</a>
            </x-dashboard.td>
            <x-dashboard.td class="text-text-muted text-xs">{{ \App\Models\SupportTicket::categoryLabel($ticket->category) }}</x-dashboard.td>
            <x-dashboard.td>
                <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-text-primary hover:text-primary hover:underline">{{ $ticket->subject }}</a>
                <span class="mt-0.5 block text-xs text-text-muted">{{ $ticket->created_at->format('M j, Y H:i') }}</span>
            </x-dashboard.td>
            <x-dashboard.td><x-dashboard.badge :status="$ticket->status" /></x-dashboard.td>
            <x-dashboard.td class="text-xs">{{ $ticket->assignee?->name ?? '-' }}</x-dashboard.td>
            <x-dashboard.td>
                <x-dashboard.button :href="route('admin.tickets.show', $ticket)" variant="secondary" size="sm">View</x-dashboard.button>
            </x-dashboard.td>
        </tr>
    @endforeach
</x-dashboard.table>

<x-dashboard.pagination :paginator="$tickets" />
