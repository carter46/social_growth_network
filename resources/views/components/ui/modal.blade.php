@props([
    'name',
    'title' => 'Confirm',
    'description' => null,
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'variant' => 'default',
    'formAction' => null,
    'method' => 'POST',
])

@php
    $confirmVariant = match ($variant) {
        'danger' => 'danger',
        'warning' => 'warning',
        default => 'primary',
    };
    $hasBody = isset($slot) && trim((string) $slot) !== '';
@endphp

{{-- Theme-aware confirm modal. Open via $dispatch('open-modal', 'name') --}}
<div
    x-data="{
        open: false,
        previouslyFocused: null,
        focusables() {
            return [...$refs.panel.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex=\'-1\'])')]
                .filter((el) => !el.hasAttribute('disabled') && el.offsetParent !== null);
        },
        trap(e) {
            if (!this.open || e.key !== 'Tab') return;
            const items = this.focusables();
            if (!items.length) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        },
        openModal() {
            this.previouslyFocused = document.activeElement;
            this.open = true;
            this.$nextTick(() => {
                const items = this.focusables();
                (items[0] || $refs.panel)?.focus?.();
            });
        },
        closeModal() {
            if (! this.open) {
                return;
            }
            this.open = false;
            this.$dispatch('close-modal', '{{ $name }}');
            this.$nextTick(() => this.previouslyFocused?.focus?.());
        },
    }"
    x-on:open-modal.window="const d = $event.detail; if (d === '{{ $name }}' || d?.id === '{{ $name }}' || d?.[0] === '{{ $name }}') openModal()"
    x-on:close-modal.window="const d = $event.detail; if ((d === '{{ $name }}' || d?.id === '{{ $name }}' || d?.[0] === '{{ $name }}') && open) closeModal()"
    {{ $attributes }}
>
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[90] flex items-end justify-center p-4 sm:items-center"
            style="overflow-y: auto; -webkit-overflow-scrolling: touch;"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="'modal-title-{{ $name }}'"
            @keydown.escape.stop="closeModal()"
            @keydown.tab="trap($event)"
        >
            <div
                class="absolute inset-0 bg-overlay backdrop-blur-[2px]"
                @click="closeModal()"
                x-transition.opacity
            ></div>
            <div
                x-ref="panel"
                x-show="open"
                tabindex="-1"
                x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                class="relative my-auto flex w-full max-w-md flex-col rounded-2xl border border-border-default bg-elevated shadow-panel outline-none"
                style="max-height: min(90vh, 90dvh); overflow: hidden;"
                @click.stop
            >
                <div class="flex shrink-0 items-start justify-between gap-3 px-5 pt-5">
                    <div class="min-w-0">
                        <h2 id="modal-title-{{ $name }}" class="text-lg font-semibold text-text-primary">{{ $title }}</h2>
                        @if ($description)
                            <p class="mt-1.5 text-sm text-text-secondary leading-relaxed">{{ $description }}</p>
                        @endif
                    </div>
                    <button
                        type="button"
                        class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg p-2 text-text-muted hover:bg-surface-muted hover:text-text-primary transition-colors"
                        @click="closeModal()"
                        aria-label="Close"
                    >
                        <x-ui.icon name="x" class="w-4 h-4" />
                    </button>
                </div>

                @if ($formAction)
                    <form method="POST" action="{{ $formAction }}" class="flex min-h-0 flex-1 flex-col" style="min-height: 0;" x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault(); return; } submitting = true;">
                        @csrf
                        @if (strtoupper($method) !== 'POST')
                            @method($method)
                        @endif

                        <div class="min-h-0 flex-1 px-5 py-4 text-sm text-text-secondary" style="overflow-y: auto; -webkit-overflow-scrolling: touch;">
                            @if ($hasBody)
                                {{ $slot }}
                            @endif

                            @isset($form)
                                <div @class(['space-y-4', 'mt-4' => $hasBody])>
                                    {{ $form }}
                                </div>
                            @endisset
                        </div>

                        <div class="flex shrink-0 flex-col-reverse gap-2.5 border-t border-border-default px-5 py-4 sm:flex-row sm:justify-end">
                            @isset($footer)
                                {{ $footer }}
                            @else
                                <x-ui.button type="button" variant="secondary" @click="$dispatch('close-modal', '{{ $name }}')">{{ $cancelLabel }}</x-ui.button>
                                <x-ui.button type="submit" :variant="$confirmVariant" aria-busy="false" x-bind:aria-busy="submitting ? 'true' : 'false'">
                                    <span class="inline-flex items-center gap-2">
                                        <span x-show="submitting" x-cloak><x-ui.icon name="spinner" class="w-4 h-4 animate-spin" /></span>
                                        {{ $confirmLabel }}
                                    </span>
                                </x-ui.button>
                            @endisset
                        </div>
                    </form>
                @else
                    @if ($hasBody)
                        <div class="min-h-0 flex-1 px-5 py-4 text-sm text-text-secondary" style="overflow-y: auto; -webkit-overflow-scrolling: touch;">{{ $slot }}</div>
                    @endif

                    <div class="flex shrink-0 flex-col-reverse gap-2.5 border-t border-border-default px-5 py-4 sm:flex-row sm:justify-end">
                        @isset($footer)
                            {{ $footer }}
                        @else
                            <x-ui.button type="button" variant="secondary" @click="closeModal()">{{ $cancelLabel }}</x-ui.button>
                            <x-ui.button type="button" :variant="$confirmVariant" @click="closeModal(); $dispatch('modal-confirmed', '{{ $name }}')">
                                {{ $confirmLabel }}
                            </x-ui.button>
                        @endisset
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>
