@php
    /** @var array $section */
    $legalEmail = $legalEmail ?? null;
    $ticketHref = $ticketHref ?? route('login');
@endphp

<div @class([
    'space-y-4 text-sm sm:text-base text-text-secondary leading-relaxed',
    'rounded-xl border border-danger/25 bg-danger/5 p-4' => ($section['variant'] ?? null) === 'danger',
])>
    @foreach(($section['paragraphs'] ?? []) as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach

    @if(! empty($section['checklist']))
        <ul class="list-none space-y-3">
            @foreach($section['checklist'] as $item)
                <li class="flex items-start gap-3">
                    <span class="text-success mt-0.5 shrink-0"><x-ui.icon name="check" class="w-5 h-5" /></span>
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if(! empty($section['bullets']))
        <ul class="list-disc ml-5 space-y-2">
            @foreach($section['bullets'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    @endif

    @if(! empty($section['cards']))
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
            @foreach($section['cards'] as $card)
                <div class="p-4 rounded-lg bg-elevated border border-border-subtle">
                    <h3 class="text-accent font-bold text-sm mb-1">{{ $card['title'] }}</h3>
                    <p class="text-sm text-text-secondary">{{ $card['body'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if(! empty($section['blocks']))
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($section['blocks'] as $block)
                <div class="flex items-center gap-2 text-sm text-text-primary">
                    <span class="text-danger shrink-0"><x-ui.icon name="warning" class="w-5 h-5" /></span>
                    <span>{{ $block }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if(($section['variant'] ?? null) === 'contact')
        <div class="glassmorphism p-5 sm:p-6 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-5 mt-4 mb-2">
            <div class="space-y-3">
                @if($legalEmail)
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wider text-text-secondary">Legal email</p>
                        <a href="mailto:{{ $legalEmail }}" class="text-accent font-medium hover:underline">{{ $legalEmail }}</a>
                    </div>
                @endif
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wider text-text-secondary">Support</p>
                    <p class="text-sm text-text-primary">Open a ticket from your dashboard for legal or privacy questions.</p>
                </div>
            </div>
            <x-ui.button href="{{ $ticketHref }}" variant="primary" size="md" class="shrink-0 hover:!bg-primary-hover">
                Open ticket
            </x-ui.button>
        </div>
    @endif
</div>
