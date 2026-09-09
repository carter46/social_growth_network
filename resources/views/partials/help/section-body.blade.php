@php
    /** @var array $section */
@endphp

<div class="space-y-4 text-sm sm:text-base text-slate-600 leading-relaxed">
    @foreach(($section['blocks'] ?? []) as $block)
        @php $type = $block['type'] ?? 'paragraph'; @endphp

        @if($type === 'paragraph')
            <p>{{ $block['content'] ?? '' }}</p>

        @elseif($type === 'bullets')
            <ul class="list-disc ml-5 space-y-2 marker:text-slate-400">
                @foreach(($block['items'] ?? []) as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>

        @elseif($type === 'checklist')
            <ul class="list-none space-y-3">
                @foreach(($block['items'] ?? []) as $item)
                    <li class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-emerald-600 text-xl mt-0.5 shrink-0" aria-hidden="true">check_circle</span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>

        @elseif(in_array($type, ['tip', 'important', 'warning', 'success'], true))
            @php
                $calloutStyles = [
                    'tip' => 'border-primary/20 bg-primary/5',
                    'important' => 'border-sky-200 bg-sky-50',
                    'warning' => 'border-amber-200 bg-amber-50',
                    'success' => 'border-emerald-200 bg-emerald-50',
                ];
                $titleStyles = [
                    'tip' => 'text-primary',
                    'important' => 'text-sky-700',
                    'warning' => 'text-amber-800',
                    'success' => 'text-emerald-800',
                ];
                $style = $calloutStyles[$type] ?? $calloutStyles['tip'];
                $titleClass = $titleStyles[$type] ?? $titleStyles['tip'];
                $labels = [
                    'tip' => 'Tip',
                    'important' => 'Important',
                    'warning' => 'Warning',
                    'success' => 'Success',
                ];
            @endphp
            <div class="rounded-xl border p-4 {{ $style }}">
                <p class="text-[11px] font-bold uppercase tracking-wider mb-1 {{ $titleClass }}">{{ $block['title'] ?? ($labels[$type] ?? 'Note') }}</p>
                <p class="text-sm text-slate-600 leading-relaxed">{{ $block['content'] ?? '' }}</p>
            </div>

        @elseif($type === 'faq')
            <div class="space-y-3">
                @foreach(($block['items'] ?? []) as $faq)
                    <details class="group bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex justify-between items-center gap-3 p-4 cursor-pointer hover:bg-slate-50 transition-colors">
                            <span class="font-semibold text-sm text-slate-900 text-left">{{ $faq['q'] ?? '' }}</span>
                            <span class="material-symbols-outlined text-slate-400 transition-transform group-open:rotate-180 shrink-0" aria-hidden="true">expand_more</span>
                        </summary>
                        <div class="px-4 pb-4 text-sm text-slate-600 border-t border-slate-100 pt-3">
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
                        class="w-full rounded-xl border border-slate-200"
                    >
                @else
                    <div
                        class="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 px-6 py-12 sm:py-16 text-center"
                        role="img"
                        aria-label="{{ $block['alt'] ?? ($block['title'] ?? 'Screenshot placeholder') }}"
                    >
                        <span class="material-symbols-outlined text-3xl text-slate-400 mb-3" aria-hidden="true">image</span>
                        <p class="font-display text-sm font-semibold text-slate-900">{{ $block['title'] ?? 'Screenshot' }}</p>
                        @if(! empty($block['caption']))
                            <p class="text-xs text-slate-500 mt-2 max-w-md mx-auto">{{ $block['caption'] }}</p>
                        @endif
                        <p class="text-[10px] uppercase tracking-wider text-slate-400 mt-3">Screenshot placeholder</p>
                    </div>
                @endif
                @if(! empty($block['caption']) && ! empty($block['image']))
                    <figcaption class="text-xs text-slate-500 mt-2 text-center">{{ $block['caption'] }}</figcaption>
                @endif
            </figure>

        @elseif($type === 'video')
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-center">
                <span class="material-symbols-outlined text-3xl text-slate-400 mb-2" aria-hidden="true">videocam</span>
                <p class="text-sm font-semibold text-slate-900">Video coming soon</p>
                <p class="text-xs text-slate-500 mt-1">
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
