{{-- Per-step progress for campaigns bought with a target per service. Params: $campaign. --}}
@php $stepRows = $campaign->stepProgress(); @endphp
@if ($stepRows !== [])
    <div class="space-y-3">
        <p class="text-sm font-medium text-text-primary">Progress by step</p>
        @foreach ($stepRows as $row)
            @php $pct = $row['target'] > 0 ? min(100, (int) floor($row['delivered'] * 100 / $row['target'])) : 0; @endphp
            <div>
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <span class="text-text-secondary">{{ $row['label'] }}</span>
                    <span class="font-medium text-text-primary">{{ number_format($row['delivered']) }} / {{ number_format($row['target']) }}</span>
                </div>
                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-primary" style="width: {{ $pct }}%"></div>
                </div>
                <p class="mt-1 text-xs text-text-muted">
                    @if ($row['in_progress'] > 0)
                        {{ number_format($row['in_progress']) }} in progress.
                    @endif
                    @if ($row['task'] === \App\Enums\CampaignTask::Watch && (int) $campaign->estimated_minutes > 0)
                        Each is one agent watching for {{ (int) $campaign->estimated_minutes }} {{ (int) $campaign->estimated_minutes === 1 ? 'minute' : 'minutes' }}, not YouTube Analytics watch hours.
                    @endif
                </p>
            </div>
        @endforeach
        <p class="text-xs text-text-muted">Each new agent takes every step that still needs agents. A step closes once its target is reached.</p>
    </div>
@endif
