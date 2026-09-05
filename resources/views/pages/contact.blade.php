@extends('layouts.marketing')

@section('title', 'Contact Us')

@section('content')
@php
    $c = $contact ?? [];
    $chatOn = (bool) ($chatEnabled ?? false);
    $chatLabel = $liveChat['label'] ?? 'Live chat';
    $supportHref = route('dashboard.support.create');
    $ticketsHref = auth()->check()
        ? route('dashboard.support.index')
        : route('login');
    $wa = preg_replace('/\D+/', '', (string) ($c['phone_whatsapp'] ?? ''));
    $mapsEmbed = trim((string) ($c['maps_embed_url'] ?? ''));
    if ($mapsEmbed === '' && filled($c['latitude'] ?? null) && filled($c['longitude'] ?? null)) {
        $mapsEmbed = 'https://maps.google.com/maps?q='.urlencode($c['latitude'].','.$c['longitude']).'&z=15&output=embed';
    }

    $helpCategories = collect(config('help.categories', []))->map(function (array $cat) {
        $slug = $cat['article'] ?? $cat['key'] ?? null;

        return [
            'label' => $cat['title'] ?? 'Guide',
            'href' => $slug ? route('help.article', $slug) : route('help'),
            'icon' => $cat['icon'] ?? 'info',
        ];
    })->values();

    $legalDocs = collect(config('legal.documents', []))->map(function (array $doc, string $key) {
        return [
            'label' => $doc['label'] ?? ucfirst($key),
            'href' => route('legal', ['doc' => $key]),
            'icon' => 'audit',
        ];
    })->values();

    $emails = collect([
        'Info' => $c['email_info'] ?? '',
        'Support' => $c['email_support'] ?? '',
        'Sales' => $c['email_sales'] ?? '',
    ])->filter(fn ($email) => $email !== '');
@endphp

@include('partials.marketing.page-header', [
    'breadcrumbs' => [
        ['label' => 'Home', 'href' => route('home')],
        ['label' => 'Contact'],
    ],
    'title' => 'Contact & Support',
    'subtitle' => 'Reach our team for social media orders, wallet funding, KYC, and account help.',
    'image' => 'assets/images/helpcenter.jpg',
    'cta' => [
        'href' => route('help'),
        'label' => 'Browse Help Center',
    ],
])

<section class="max-w-marketing mx-auto px-5 sm:px-6 pb-16 sm:pb-20">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">
        <div class="lg:col-span-8 space-y-8">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-accent mb-2">Get in touch</p>
                <h2 class="font-display text-2xl sm:text-3xl font-semibold text-white mb-2">Talk to a real person</h2>
                <p class="text-sm sm:text-base text-text-secondary max-w-2xl leading-relaxed">
                    Pick the channel that fits — email for records, WhatsApp or phone for quick questions, tickets for tracked cases.
                </p>
            </div>

            <div class="space-y-3">
                @forelse($emails as $label => $email)
                    <a
                        href="mailto:{{ $email }}"
                        class="group flex items-center gap-4 rounded-2xl border border-border-subtle bg-elevated/80 px-4 py-4 sm:px-5 hover:border-accent/40 hover:bg-elevated transition-colors"
                    >
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-accent/15 text-accent">
                            <x-ui.icon name="mail" class="w-5 h-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[11px] uppercase tracking-wide text-text-muted">{{ $label }} email</span>
                            <span class="block truncate text-sm sm:text-base font-semibold text-white group-hover:text-accent transition-colors">{{ $email }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="w-4 h-4 text-text-muted group-hover:text-accent shrink-0" />
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-border-subtle px-5 py-6 text-sm text-text-muted">
                        Emails appear once set in Admin → Settings.
                    </div>
                @endforelse

                @if(($c['phone_support'] ?? '') !== '')
                    <a
                        href="tel:{{ preg_replace('/\s+/', '', $c['phone_support']) }}"
                        class="group flex items-center gap-4 rounded-2xl border border-border-subtle bg-elevated/80 px-4 py-4 sm:px-5 hover:border-accent/40 hover:bg-elevated transition-colors"
                    >
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-500/15 text-sky-400">
                            <x-ui.icon name="phone" class="w-5 h-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[11px] uppercase tracking-wide text-text-muted">Call support</span>
                            <span class="block text-sm sm:text-base font-semibold text-white group-hover:text-accent transition-colors">{{ $c['phone_support'] }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="w-4 h-4 text-text-muted group-hover:text-accent shrink-0" />
                    </a>
                @endif

                @if(($c['phone_general'] ?? '') !== '' && ($c['phone_general'] ?? '') !== ($c['phone_support'] ?? ''))
                    <a
                        href="tel:{{ preg_replace('/\s+/', '', $c['phone_general']) }}"
                        class="group flex items-center gap-4 rounded-2xl border border-border-subtle bg-elevated/80 px-4 py-4 sm:px-5 hover:border-accent/40 hover:bg-elevated transition-colors"
                    >
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-500/15 text-sky-400">
                            <x-ui.icon name="phone" class="w-5 h-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[11px] uppercase tracking-wide text-text-muted">Call general</span>
                            <span class="block text-sm sm:text-base font-semibold text-white group-hover:text-accent transition-colors">{{ $c['phone_general'] }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="w-4 h-4 text-text-muted group-hover:text-accent shrink-0" />
                    </a>
                @endif

                @if($wa !== '')
                    <a
                        href="https://wa.me/{{ $wa }}"
                        target="_blank"
                        rel="noopener"
                        class="group flex items-center gap-4 rounded-2xl border border-border-subtle bg-elevated/80 px-4 py-4 sm:px-5 hover:border-emerald-400/40 hover:bg-elevated transition-colors"
                    >
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-400">
                            <x-ui.icon name="whatsapp" class="w-5 h-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[11px] uppercase tracking-wide text-text-muted">WhatsApp</span>
                            <span class="block text-sm sm:text-base font-semibold text-white group-hover:text-emerald-400 transition-colors">{{ $c['phone_whatsapp'] }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="w-4 h-4 text-text-muted group-hover:text-emerald-400 shrink-0" />
                    </a>
                @endif

                @if(($c['phone_support'] ?? '') === '' && ($c['phone_general'] ?? '') === '' && $wa === '' && $emails->isNotEmpty())
                    {{-- phones empty but emails exist — no extra empty state --}}
                @elseif(($c['phone_support'] ?? '') === '' && ($c['phone_general'] ?? '') === '' && $wa === '' && $emails->isEmpty())
                    <div class="rounded-2xl border border-dashed border-border-subtle px-5 py-6 text-sm text-text-muted">
                        Phone numbers appear once set in Admin → Settings.
                    </div>
                @endif
            </div>

            @if(($c['support_hours'] ?? '') !== '' || ($c['business_hours'] ?? '') !== '')
                <p class="text-xs text-text-muted">
                    Hours: {{ $c['business_hours'] ?: $c['support_hours'] }}
                    @if(($c['timezone'] ?? '') !== '') · {{ $c['timezone'] }} @endif
                </p>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @if($chatOn)
                    <div id="live-chat" class="rounded-2xl border border-accent/30 bg-primary/10 p-5 sm:p-6 scroll-mt-28">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent/20 text-accent">
                                <x-ui.icon name="chat" class="w-5 h-5" />
                            </span>
                            <div>
                                <h3 class="font-display text-base font-semibold text-white">{{ $chatLabel }}</h3>
                                <p class="text-[11px] text-emerald-400">Available now</p>
                            </div>
                        </div>
                        <p class="text-sm text-text-secondary mb-4">Best for time-sensitive help while you’re on the site.</p>
                        <button type="button" onclick="document.dispatchEvent(new CustomEvent('open-live-chat'))"
                            class="w-full py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-accent transition-colors">
                            Open chat
                        </button>
                    </div>
                @endif

                <div class="rounded-2xl border border-border-subtle bg-elevated p-5 sm:p-6 @if(!$chatOn) sm:col-span-2 @endif">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/15 text-accent">
                            <x-ui.icon name="support" class="w-5 h-5" />
                        </span>
                        <h3 class="font-display text-base font-semibold text-white">Support tickets</h3>
                    </div>
                    <p class="text-sm text-text-secondary mb-4">Create a tracked ticket from your dashboard. Login required.</p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ $supportHref }}" class="flex-1 text-center py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-accent transition-colors">New ticket</a>
                        <a href="{{ $ticketsHref }}" class="flex-1 text-center py-2.5 rounded-xl border border-border-subtle text-sm font-semibold text-white hover:bg-white/5 transition-colors">My tickets</a>
                    </div>
                </div>
            </div>

            @if($formattedAddress !== '' || $mapsEmbed !== '')
                <div class="rounded-2xl border border-border-subtle bg-elevated overflow-hidden">
                    <div class="p-5 sm:p-6">
                        <h3 class="font-display text-lg font-semibold text-white mb-2">Visit us</h3>
                        @if($formattedAddress !== '')
                            <p class="text-sm text-text-secondary">{{ $formattedAddress }}</p>
                        @endif
                        @if(($c['maps_url'] ?? '') !== '')
                            <a href="{{ $c['maps_url'] }}" target="_blank" rel="noopener" class="inline-block mt-3 text-sm text-accent hover:underline">Open in Google Maps</a>
                        @endif
                    </div>
                    @if($mapsEmbed !== '')
                        <div class="aspect-[16/9] w-full bg-muted">
                            <iframe title="Map" src="{{ $mapsEmbed }}" class="w-full h-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    @endif
                </div>
            @endif

            @if(($socialLinks ?? collect())->isNotEmpty())
                <div class="rounded-2xl border border-border-subtle bg-elevated/60 px-5 py-5 sm:px-6">
                    <h3 class="font-display text-base font-semibold text-white mb-4">Follow us</h3>
                    <x-ui.social-links :links="$socialLinks" />
                </div>
            @endif
        </div>

        <aside class="lg:col-span-4">
            <div class="lg:sticky lg:top-28 space-y-5">
                <div class="rounded-2xl border border-border-subtle bg-elevated p-5 sm:p-6">
                    <h3 class="font-display text-lg font-semibold text-white mb-1">Help guides</h3>
                    <p class="text-xs text-text-muted mb-4">Same topics as the Help Center</p>
                    <ul class="space-y-1">
                        @foreach($helpCategories as $item)
                            <li>
                                <a href="{{ $item['href'] }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-text-secondary hover:bg-white/5 hover:text-white transition-colors">
                                    <x-ui.icon :name="$item['icon']" class="w-4 h-4 text-accent shrink-0" />
                                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                    <x-ui.icon name="chevron-right" class="w-3.5 h-3.5 opacity-50 shrink-0" />
                                </a>
                            </li>
                        @endforeach
                        <li>
                            <a href="{{ route('help') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-accent hover:bg-accent/10 transition-colors">
                                <x-ui.icon name="search" class="w-4 h-4 shrink-0" />
                                <span>All Help Center articles</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-border-subtle bg-elevated p-5 sm:p-6">
                    <h3 class="font-display text-lg font-semibold text-white mb-1">Legal</h3>
                    <p class="text-xs text-text-muted mb-4">Policies &amp; compliance</p>
                    <ul class="space-y-1">
                        @foreach($legalDocs as $item)
                            <li>
                                <a href="{{ $item['href'] }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-text-secondary hover:bg-white/5 hover:text-white transition-colors">
                                    <x-ui.icon :name="$item['icon']" class="w-4 h-4 text-accent shrink-0" />
                                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                    <x-ui.icon name="chevron-right" class="w-3.5 h-3.5 opacity-50 shrink-0" />
                                </a>
                            </li>
                        @endforeach
                        <li>
                            <a href="{{ route('legal') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-accent hover:bg-accent/10 transition-colors">
                                <x-ui.icon name="audit" class="w-4 h-4 shrink-0" />
                                <span>Legal hub</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>
    </div>
</section>

@if($chatOn)
<script>
document.addEventListener('open-live-chat', function () {
    try { if (typeof window.smartsupp === 'function') window.smartsupp('chat:open'); } catch (e) {}
    try { if (window.jivo_api && typeof window.jivo_api.open === 'function') window.jivo_api.open(); } catch (e) {}
    try {
        if (window.$chatway && typeof window.$chatway.openChatwayWidget === 'function') {
            window.$chatway.openChatwayWidget();
            return;
        }
    } catch (e) {}
    var chatSection = document.getElementById('live-chat');
    if (chatSection) chatSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
</script>
@endif
@endsection
