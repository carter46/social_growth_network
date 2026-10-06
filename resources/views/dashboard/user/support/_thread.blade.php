@php
    $viewer = $viewer ?? 'member';
    $attachments = $ticket->attachments ?? collect();
    $owner = $ticket->user;

    $ownerName = ! $owner || $owner->isAnonymized()
        ? __('Deleted User')
        : ($viewer === 'staff' && $owner->username ? '@'.$owner->username : $owner->displayName());

    $viewerId = (int) auth()->id();

    $messages = collect([[
        'mine' => $viewer === 'member',
        'self' => $viewer === 'member',
        'name' => $ownerName,
        'staff' => false,
        'body' => $ticket->body,
        'at' => $ticket->created_at,
        'attachments' => $attachments->whereNull('support_ticket_reply_id'),
    ]])->concat($ticket->replies->map(fn ($reply) => [
        'mine' => $viewer === 'staff' ? $reply->is_staff : ! $reply->is_staff,
        'self' => (int) $reply->user_id === $viewerId,
        'name' => $reply->is_staff
            ? ($viewer === 'staff' ? \App\Models\User::nameFor($reply->user) : __('Support'))
            : $ownerName,
        'staff' => $reply->is_staff,
        'body' => $reply->body,
        'at' => $reply->created_at,
        'attachments' => $attachments->where('support_ticket_reply_id', $reply->id),
    ]));

    $initials = fn (string $name) => strtoupper(mb_substr(ltrim($name, '@'), 0, 1)) ?: '?';
@endphp

<x-dashboard.card :padding="false" class="overflow-hidden">
    <div
        class="max-h-[65vh] space-y-4 overflow-y-auto px-4 py-5 sm:px-6"
        x-data
        x-init="$el.scrollTop = $el.scrollHeight"
    >
        @foreach ($messages as $message)
            <div @class(['flex items-end gap-2', 'flex-row-reverse' => $message['mine']])>
                @unless ($message['mine'])
                    <span @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                        'bg-primary/10 text-primary' => $message['staff'],
                        'bg-muted text-text-secondary' => ! $message['staff'],
                    ])>{{ $initials($message['name']) }}</span>
                @endunless
                <div @class(['flex max-w-[85%] flex-col sm:max-w-[70%]', 'items-end' => $message['mine'], 'items-start' => ! $message['mine']])>
                    <div @class([
                        'rounded-2xl px-4 py-2.5 text-sm',
                        'rounded-br-md bg-primary text-white' => $message['mine'],
                        'rounded-bl-md bg-muted/60 text-text-primary' => ! $message['mine'],
                    ])>
                        <p class="whitespace-pre-wrap break-words">{{ $message['body'] }}</p>
                        @include('dashboard.user.support._attachments', [
                            'attachments' => $message['attachments'],
                            'onPrimary' => $message['mine'],
                        ])
                    </div>
                    <p class="mt-1 px-1 text-[11px] text-text-muted">
                        {{ $message['self'] ? __('You') : $message['name'] }} · {{ $message['at']->format('M j, H:i') }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    <form
        method="POST"
        action="{{ $replyAction }}"
        enctype="multipart/form-data"
        class="space-y-3 border-t border-border-default bg-muted/20 px-4 py-4 sm:px-6"
        x-data="{ submitting: false }"
        @submit="submitting = true"
    >
        @csrf
        <x-dashboard.textarea name="body" :rows="3" :placeholder="__('Write a reply...')" :aria-label="__('Reply')" required />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <input
                    type="file"
                    name="attachments[]"
                    multiple
                    accept="image/*,.pdf,.doc,.docx,.txt"
                    class="block w-full text-xs text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-muted file:px-3 file:py-2"
                >
                <p class="mt-1 text-xs text-text-muted">{{ __('Screenshots welcome · auto-deleted after 72 hours') }}</p>
            </div>
            <x-dashboard.button type="submit" size="sm" icon="chat" x-bind:disabled="submitting">{{ $submitLabel ?? __('Send') }}</x-dashboard.button>
        </div>
    </form>
</x-dashboard.card>
