{{--
  Single segmented range control. Custom opens a modal (Done applies).
--}}
@props([
    'value' => '24h',
    'options' => [],
    'name' => 'range',
])

@php
    $options = $options ?: \App\Services\Reporting\ReportingRange::presetOptions();
    $modalId = 'command-range-modal-'.uniqid();
@endphp

<div {{ $attributes->class(['flex flex-wrap items-center gap-3']) }} data-command-range>
    <div class="inline-flex flex-wrap rounded-xl border border-slate-200 bg-slate-200/50 p-1 dark:border-border-default dark:bg-muted/40">
        @foreach ($options as $opt)
            @if (($opt['value'] ?? '') !== 'custom')
                <button
                    type="button"
                    data-range-value="{{ $opt['value'] }}"
                    class="rounded-lg px-3.5 py-1.5 text-xs font-bold transition-all {{ $value === $opt['value'] ? 'bg-white text-slate-800 shadow-sm dark:bg-elevated dark:text-text-primary' : 'text-slate-500 hover:text-slate-700 dark:text-text-muted dark:hover:text-text-primary' }}"
                >{{ $opt['label'] }}</button>
            @endif
        @endforeach
        <button
            type="button"
            data-range-custom-open
            class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition-all {{ $value === 'custom' ? 'bg-white text-slate-800 shadow-sm dark:bg-elevated dark:text-text-primary' : 'text-slate-500 hover:text-slate-700 dark:text-text-muted' }}"
        >Custom</button>
    </div>
    <input type="hidden" name="{{ $name }}" data-range-select value="{{ $value }}">
    <input type="hidden" data-range-from value="{{ request('from') }}">
    <input type="hidden" data-range-to value="{{ request('to') }}">

    <div
        data-range-modal
        id="{{ $modalId }}"
        class="fixed inset-0 z-[80] hidden items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $modalId }}-title"
    >
        <div data-range-modal-backdrop class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-border-default dark:bg-elevated">
            <h3 id="{{ $modalId }}-title" class="text-sm font-bold text-slate-900 dark:text-text-primary">Custom date range</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-text-muted">Choose a from / to period, then confirm.</p>
            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-widest text-slate-400" for="{{ $modalId }}-from">From</label>
                    <input id="{{ $modalId }}-from" type="date" data-range-modal-from value="{{ request('from') }}" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 dark:border-border-default dark:bg-surface dark:text-text-primary">
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-widest text-slate-400" for="{{ $modalId }}-to">To</label>
                    <input id="{{ $modalId }}-to" type="date" data-range-modal-to value="{{ request('to') }}" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 dark:border-border-default dark:bg-surface dark:text-text-primary">
                </div>
            </div>
            <p data-range-modal-error class="mt-3 hidden text-xs font-medium text-red-600">Please select both dates.</p>
            <div class="mt-6 flex items-center justify-end gap-2">
                <button type="button" data-range-modal-cancel class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-border-default dark:text-text-secondary">Cancel</button>
                <button type="button" data-range-modal-done class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-hover">Done</button>
            </div>
        </div>
    </div>
</div>
