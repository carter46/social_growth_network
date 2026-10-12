@php
    /** @var array<string, string> $gateways key => display name */
    $gateways = $gateways ?? [];
    $selected = $selected ?? array_key_first($gateways);
    $inputName = $inputName ?? 'gateway';
@endphp
@if (count($gateways) > 1)
    <fieldset class="space-y-2">
        <legend class="text-sm font-medium text-text-secondary mb-1">Pay with</legend>
        @foreach ($gateways as $key => $label)
            <label class="flex items-center gap-3 rounded-lg border border-border-default px-3 py-2.5 cursor-pointer hover:border-primary/50 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input type="radio" name="{{ $inputName }}" value="{{ $key }}" @checked($selected === $key) class="text-primary focus-ring">
                <span class="text-sm text-text-primary">{{ $label }}</span>
                @if ($loop->first)
                    <x-dashboard.badge variant="info" class="ml-auto">Default</x-dashboard.badge>
                @endif
            </label>
        @endforeach
        <p class="text-xs text-text-muted">You will finish paying on the {{ implode(' or ', $gateways) }} secure page, which shows the payment methods available to you.</p>
    </fieldset>
@elseif (count($gateways) === 1)
    <input type="hidden" name="{{ $inputName }}" value="{{ array_key_first($gateways) }}">
    <p class="text-xs text-text-muted">You will finish paying on the {{ reset($gateways) }} secure page.</p>
@endif
@error($inputName)
    <p class="text-xs text-danger">{{ $message }}</p>
@enderror
