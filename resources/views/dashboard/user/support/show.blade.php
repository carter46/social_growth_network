@extends($layout ?? 'layouts.dashboard-user')

@section('title', $ticket->subject)

@section('content')
@php $prefix = $prefix ?? 'dashboard'; @endphp
<x-layout.page
    title="{{ $ticket->subject }}"
    width="full"
    :breadcrumb="[
        [$prefix === 'agent' ? 'Agent' : 'Dashboard', route($prefix === 'agent' ? 'agent' : 'dashboard')],
        ['Support', route($prefix.'.support.index')],
        ['Ticket', null],
    ]"
>
    <x-slot:actions>
        <x-dashboard.badge :status="$ticket->status === 'open' ? 'pending' : 'completed'">{{ $ticket->status }}</x-dashboard.badge>
    </x-slot:actions>

    <x-dashboard.card>
        <div class="text-text-primary whitespace-pre-wrap">{{ $ticket->body }}</div>
        @include('dashboard.user.support._attachments', [
            'attachments' => $ticket->attachments->whereNull('support_ticket_reply_id'),
        ])
    </x-dashboard.card>

    @foreach ($ticket->replies as $reply)
        <x-dashboard.card :class="$reply->is_staff ? 'border border-primary/30' : ''">
            <p class="text-xs text-text-muted mb-2">
                {{ \App\Models\User::nameFor($reply->user) }}
                @if ($reply->is_staff)
                    <span class="text-primary font-medium">(Staff)</span>
                @endif
            </p>
            <p class="text-sm text-text-primary whitespace-pre-wrap">{{ $reply->body }}</p>
            @include('dashboard.user.support._attachments', [
                'attachments' => $ticket->attachments->where('support_ticket_reply_id', $reply->id),
            ])
        </x-dashboard.card>
    @endforeach

    <x-dashboard.card>
        <form method="POST" action="{{ route($prefix.'.support.reply', $ticket) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <x-dashboard.textarea label="Reply" name="body" :rows="3" required />
            <div>
                <label class="block text-sm font-medium text-text-primary mb-1">Attach evidence</label>
                <input
                    type="file"
                    name="attachments[]"
                    multiple
                    accept="image/*,.pdf,.doc,.docx,.txt,capture=environment"
                    class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-muted file:px-3 file:py-2"
                >
                <p class="mt-1 text-xs text-text-muted">Screenshots welcome · auto-deleted after 72 hours</p>
            </div>
            <x-dashboard.button type="submit" size="sm" icon="chat" x-bind:disabled="submitting">Reply</x-dashboard.button>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
