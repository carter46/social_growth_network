@props([
    'amount' => 0,
    'precision' => 2,
    'max' => null,
])
<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ money($amount, (int) $precision, $max !== null ? (int) $max : null) }}</span>
