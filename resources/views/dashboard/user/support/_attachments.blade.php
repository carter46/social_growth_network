@php
    $attachments = collect($attachments ?? [])->filter(fn ($a) => ! $a->isExpired());
@endphp
@if ($attachments->isNotEmpty())
    <ul class="mt-3 flex flex-wrap gap-2">
        @foreach ($attachments as $attachment)
            <li>
                <a
                    href="{{ $attachment->temporaryUrl() }}"
                    @class([
                        'inline-flex items-center gap-1 rounded-lg border px-2 py-1 text-xs',
                        'border-white/40 text-white hover:bg-white/10' => $onPrimary ?? false,
                        'border-border-default bg-elevated text-primary hover:bg-muted/50' => ! ($onPrimary ?? false),
                    ])
                    target="_blank"
                    rel="noopener"
                >
                    <x-ui.icon name="upload" class="w-3.5 h-3.5" />
                    {{ \Illuminate\Support\Str::limit($attachment->original_name, 28) }}
                </a>
            </li>
        @endforeach
    </ul>
@endif
