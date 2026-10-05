@props([
    'eyebrow' => null,
])

<section {{ $attributes->merge(['class' => 'w-full bg-white py-14 sm:py-20']) }}>
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-3xl p-8 sm:p-12 lg:p-16 shadow-xl text-white text-center">
            <div class="absolute inset-0 bg-gradient-to-br from-red-700 via-red-900 to-slate-950" aria-hidden="true"></div>
            <div class="absolute -right-20 -top-20 w-96 h-96 rounded-full bg-red-500/25 blur-3xl pointer-events-none" aria-hidden="true"></div>
            <div class="relative z-10 max-w-3xl mx-auto flex flex-col items-center">
                @if ($eyebrow)
                    <span class="text-[11px] font-bold uppercase tracking-widest text-red-100 mb-3">{{ $eyebrow }}</span>
                @endif
                <h2 class="font-display text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-white">{{ $title }}</h2>
                @isset($description)
                    <p class="text-base sm:text-lg text-red-50/90 max-w-2xl mt-4 leading-relaxed">{{ $description }}</p>
                @endisset
                @isset($actions)
                    <div class="flex flex-wrap items-center justify-center gap-3 mt-8">{{ $actions }}</div>
                @endisset
                @isset($note)
                    <p class="mt-6 text-xs font-medium text-red-100/90">{{ $note }}</p>
                @endisset
            </div>
        </div>
    </div>
</section>
