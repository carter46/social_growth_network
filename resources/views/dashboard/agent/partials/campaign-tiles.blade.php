@php
    $tile = 'rounded-xl bg-muted/40 px-4 py-3';
    $minutes = (int) ($campaign->estimated_minutes ?? 0);
    $description = trim(strip_tags((string) ($campaign->product?->description ?? '')));
    $status = $status ?? null;
@endphp
<div @class(['grid grid-cols-1 gap-3', 'sm:grid-cols-2 xl:grid-cols-4' => $status, 'sm:grid-cols-3' => ! $status])>
    @if ($status)
        <div class="{{ $tile }}">
            <p class="text-xs text-text-muted">{{ __('Status') }}</p>
            <div class="mt-1"><x-dashboard.badge :status="$status" /></div>
        </div>
    @endif
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Reward') }}</p>
        <p class="mt-0.5 text-lg font-semibold text-text-primary">₦{{ number_format((float) $reward, 2) }}</p>
    </div>
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Slots left') }}</p>
        <p class="mt-0.5 text-lg font-semibold text-text-primary">{{ number_format($campaign->availableStartSlots()) }}</p>
    </div>
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Estimated time') }}</p>
        <p class="mt-0.5 text-lg font-semibold text-text-primary">{{ $minutes > 0 ? trans_choice(':count min|:count mins', $minutes) : '-' }}</p>
    </div>
</div>

@if ($campaign->target_url)
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Target') }}</p>
        <a href="{{ $campaign->target_url }}" class="mt-0.5 block break-all text-sm font-medium text-primary hover:underline" target="_blank" rel="noopener">{{ $campaign->target_url }}</a>
    </div>
@endif

@if ($description !== '')
    <div class="{{ $tile }}">
        <p class="text-xs text-text-muted">{{ __('Description') }}</p>
        <p class="mt-0.5 text-sm text-text-secondary">{{ \Illuminate\Support\Str::limit($description, 400) }}</p>
    </div>
@endif
