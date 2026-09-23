@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
    'size' => 'md',
    'toggle' => null,
])

@php
    $id = $attributes->get('id', $name);
    $attributes = $attributes->except('id');
    $errorMessage = $error ?? ($name ? $errors->first($name) : null);
    $sizeClass = $size === 'sm' ? 'h-8 px-2 text-xs' : 'h-10 px-3 text-sm';
    $hintId = $id ? $id.'-hint' : null;
    $errorId = $id ? $id.'-error' : null;
    $describedBy = collect([
        $hint && ! $errorMessage ? $hintId : null,
        $errorMessage ? $errorId : null,
    ])->filter()->implode(' ');
    $isPassword = $type === 'password';
    $showToggle = $isPassword && ($toggle ?? true);
    $inputClass = 'block w-full rounded-lg border bg-elevated/50 text-text-primary placeholder:text-text-muted focus-ring '.$sizeClass.' '.
        ($errorMessage ? 'border-danger' : 'border-border-default').
        ($showToggle ? ' pr-11' : '');
@endphp

<div class="space-y-1.5" @if ($showToggle) x-data="{ show: false }" @endif>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-text-secondary">{{ $label }}</label>
    @endif

    @if ($showToggle)
        <div class="relative">
            <input
                @if ($name) name="{{ $name }}" @endif
                @if ($id) id="{{ $id }}" @endif
                type="password"
                x-bind:type="show ? 'text' : 'password'"
                @if ($errorMessage) aria-invalid="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                {{ $attributes->merge(['class' => $inputClass]) }}
            >
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-primary transition-colors focus:outline-none focus-visible:text-primary"
                @click="show = ! show"
                :aria-label="show ? 'Hide password' : 'Show password'"
                :aria-pressed="show.toString()"
            >
                <span class="relative inline-flex h-5 w-5 items-center justify-center">
                    <x-ui.icon name="eye" class="absolute inset-0 w-5 h-5" x-show="!show" />
                    <x-ui.icon name="eye-off" class="absolute inset-0 w-5 h-5" x-cloak x-show="show" />
                </span>
            </button>
        </div>
    @else
        <input
            @if ($name) name="{{ $name }}" @endif
            @if ($id) id="{{ $id }}" @endif
            type="{{ $type }}"
            @if ($errorMessage) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->merge(['class' => $inputClass]) }}
        >
    @endif

    @if ($hint && ! $errorMessage)
        <p @if ($hintId) id="{{ $hintId }}" @endif class="text-xs text-text-muted">{{ $hint }}</p>
    @endif
    @if ($errorMessage)
        <p @if ($errorId) id="{{ $errorId }}" @endif class="text-xs text-danger">{{ $errorMessage }}</p>
    @endif
</div>
