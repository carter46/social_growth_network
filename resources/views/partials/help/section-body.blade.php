@php
    /** @var array $section */
    $legalEmail = $legalEmail ?? null;
    $ticketHref = $ticketHref ?? route('contact');
@endphp

<div class="space-y-4 text-sm sm:text-base text-text-secondary leading-relaxed">
    @foreach(($section['blocks'] ?? []) as $block)
        @php $type = $block['type'] ?? 'paragraph'; @endphp

        @if($type === 'paragraph')
            <p>{{ $block['content'] ?? '' }}</p>

        @elseif($type === 'bullets')
            <ul class="list-disc ml-5 space-y-2">
                @foreach(($block['items'] ?? []) as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>

        @elseif($type === 'checklist')
            <ul class="list-none space-y-3">
                @foreach(($block['items'] ?? []) as $item)
                    <li class="flex items-start gap-3">
                        <span class="text-success mt-0.5 shrink-0"><x-ui.icon name="check" class="w-5 h-5" /></span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>

        @elseif(in_array($type, ['tip', 'important', 'warning', 'success'], true))
            @php
                $calloutStyles = [
                    'tip' => 'border-accent/30 bg-accent/5 text-accent',
                    'important' => 'border-sky-400/30 bg-sky-500/5 text-sky-300',
                    'warning' => 'border-warning/30 bg-warning/5 text-warning',
                    'success' => 'border-success/30 bg-success/5 text-success',
                ];
                $style = $calloutStyles[$type] ?? $calloutStyles['tip'];
                $labels = [
                    'tip' => 'Tip',
                    'important' => 'Important',
                    'warning' => 'Warning',
                    'success' => 'Success',
                ];
            @endphp
            <div class="rounded-xl border p-4 {{ $style }}">
                <p class="text-[11px] font-bold uppercase tracking-wider mb-1">{{ $block['title'] ?? ($labels[$type] ?? 'Note') }}</p>
                <p class="text-sm text-text-secondary leading-relaxed">{{ $block['content'] ?? '' }}</p>
            </div>

        @elseif($type === 'faq')
            <div class="space-y-3">
                @foreach(($block['items'] ?? []) as $faq)
                    <details class="group glassmorphism rounded-xl overflow-hidden [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex justify-between items-center gap-3 p-4 cursor-pointer hover:bg-white/5 transition-colors">
                            <span class="font-semibold text-sm text-white text-left">{{ $faq['q'] ?? '' }}</span>
                            <span class="text-text-secondary transition-transform group-open:rotate-180 shrink-0">
                                <x-ui.icon name="chevron-down" class="w-5 h-5" />
                            </span>
                        </summary>
                        <div class="px-4 pb-4 text-sm text-text-secondary border-t border-border-subtle pt-3">
                            {{ $faq['a'] ?? '' }}
                        </div>
                    </details>
                @endforeach
            </div>

        @elseif($type === 'screenshot')
            @php
                $size = $block['size'] ?? 'large';
                $align = $block['alignment'] ?? 'center';
                $maxW = match ($size) {
                    'small' => 'max-w-sm',
                    'medium' => 'max-w-xl',
                    default => 'max-w-3xl',
                };
                $alignClass = match ($align) {
                    'left' => 'mr-auto',
                    'full' => 'w-full max-w-none',
                    default => 'mx-auto',
                };
            @endphp
            <figure class="{{ $maxW }} {{ $alignClass }} my-2">
                @if(! empty($block['image']))
                    <img
                        src="{{ $block['image'] }}"
                        alt="{{ $block['alt'] ?? ($block['title'] ?? 'Screenshot') }}"
                        class="w-full rounded-xl border border-border-subtle"
                    >
                @else
                    <div
                        class="rounded-xl border-2 border-dashed border-border-default bg-muted/40 px-6 py-12 sm:py-16 text-center"
                        role="img"
                        aria-label="{{ $block['alt'] ?? ($block['title'] ?? 'Screenshot placeholder') }}"
                    >
                        <span class="inline-flex text-text-muted mb-3"><x-ui.icon name="empty" class="w-8 h-8" /></span>
                        <p class="font-display text-sm font-semibold text-white">{{ $block['title'] ?? 'Screenshot' }}</p>
                        @if(! empty($block['caption']))
                            <p class="text-xs text-text-secondary mt-2 max-w-md mx-auto">{{ $block['caption'] }}</p>
                        @endif
                        <p class="text-[10px] uppercase tracking-wider text-text-muted mt-3">Screenshot placeholder</p>
                    </div>
                @endif
                @if(! empty($block['caption']) && ! empty($block['image']))
                    <figcaption class="text-xs text-text-muted mt-2 text-center">{{ $block['caption'] }}</figcaption>
                @endif
            </figure>

        @elseif($type === 'video')
            <div class="rounded-xl border border-border-subtle bg-muted/30 p-6 text-center">
                <span class="inline-flex text-text-muted mb-2"><x-ui.icon name="monitoring" class="w-8 h-8" /></span>
                <p class="text-sm font-semibold text-white">Video coming soon</p>
                <p class="text-xs text-text-secondary mt-1">
                    @if(! empty($block['youtube_id']))
                        YouTube: {{ $block['youtube_id'] }}
                    @elseif(! empty($block['video_url']))
                        {{ $block['video_url'] }}
                    @else
                        Embed will appear here when a video is added.
                    @endif
                </p>
            </div>
        @endif
    @endforeach
</div>
