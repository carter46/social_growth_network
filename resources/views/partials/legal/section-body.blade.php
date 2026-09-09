@php
    /** @var array $section */
    $legalEmail = $legalEmail ?? null;
    $ticketHref = $ticketHref ?? route('login');
@endphp

<div @class([
    'space-y-4 text-sm sm:text-base text-slate-600 leading-relaxed',
    'rounded-xl border border-red-200 bg-red-50 p-4' => ($section['variant'] ?? null) === 'danger',
])>
    @foreach(($section['paragraphs'] ?? []) as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach

    @if(! empty($section['checklist']))
        <ul class="list-none space-y-3">
            @foreach($section['checklist'] as $item)
                <li class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-emerald-600 text-xl mt-0.5 shrink-0" aria-hidden="true">check_circle</span>
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if(! empty($section['bullets']))
        <ul class="list-disc ml-5 space-y-2 marker:text-slate-400">
            @foreach($section['bullets'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    @endif

    @if(! empty($section['cards']))
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
            @foreach($section['cards'] as $card)
                <div class="p-4 rounded-xl bg-white border border-slate-100 shadow-sm">
                    <h3 class="text-primary font-bold text-sm mb-1">{{ $card['title'] }}</h3>
                    <p class="text-sm text-slate-600">{{ $card['body'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if(! empty($section['blocks']))
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($section['blocks'] as $block)
                <div class="flex items-center gap-2 text-sm text-slate-800">
                    <span class="material-symbols-outlined text-red-600 shrink-0" aria-hidden="true">warning</span>
                    <span>{{ $block }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if(($section['variant'] ?? null) === 'contact')
        <div class="bg-slate-50 border border-slate-100 p-5 sm:p-6 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-5 mt-4 mb-2">
            <div class="space-y-3">
                @if($legalEmail)
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Legal email</p>
                        <a href="mailto:{{ $legalEmail }}" class="text-primary font-medium hover:underline">{{ $legalEmail }}</a>
                    </div>
                @endif
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Support</p>
                    <p class="text-sm text-slate-700">Open a ticket from your dashboard for legal or privacy questions.</p>
                </div>
            </div>
            <a href="{{ $ticketHref }}" class="shrink-0 inline-flex items-center justify-center px-5 py-2.5 rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-semibold transition-colors shadow-sm">
                Open ticket
            </a>
        </div>
    @endif
</div>
