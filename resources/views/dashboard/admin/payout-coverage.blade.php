@extends('layouts.dashboard-admin')

@section('title', 'Payout coverage')

@section('content')
<x-layout.page
    title="Payout coverage"
    subtitle="Which gateways may pay each country and method after Sync. Agents connect banks through Bachs hosted onboarding; this list is the site whitelist used for withdrawals and any on-site bank forms."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Payout coverage', null],
    ]"
>
    @if ($errors->any())
        <x-dashboard.alert type="danger">{{ $errors->first() }}</x-dashboard.alert>
    @endif
    @if (session('status'))
        <x-dashboard.alert type="success">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger">{{ session('error') }}</x-dashboard.alert>
    @endif

    @foreach ($lastErrors as $key => $error)
        @if ($error && $error !== 'No longer reported by the provider.')
            <x-dashboard.alert type="warning" class="mb-2">Last sync for {{ $gateways[$key]['name'] ?? $key }} failed: {{ $error }}. The last verified routes are kept.</x-dashboard.alert>
        @endif
    @endforeach

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('admin.payout-coverage.sync') }}">
            @csrf
            <x-dashboard.button type="submit" size="sm" variant="primary">Sync with providers now</x-dashboard.button>
        </form>
        <span class="text-xs text-text-muted">Last synced: {{ $lastSynced ? \Carbon\Carbon::parse($lastSynced)->toDayDateTimeString() : 'never' }}. Runs daily.</span>
    </div>

    <x-dashboard.table :empty="$routes === []" empty-title="No routes" empty-description="Add countries in config/payout_methods.php." empty-icon="withdraw" striped :min-height="false">
        <x-slot:head>
            <x-dashboard.th>Route</x-dashboard.th>
            @foreach ($gateways as $key => $g)
                <x-dashboard.th>{{ $g['name'] }}@unless ($g['configured']) <span class="text-xs text-text-muted">(not configured)</span>@endunless</x-dashboard.th>
            @endforeach
        </x-slot:head>
        @foreach ($routes as $route)
            <tr>
                <x-dashboard.td>
                    <div class="font-medium text-text-primary">{{ $route['country_name'] }} · {{ $route['method_label'] }}</div>
                    <div class="text-xs {{ $route['offered'] ? 'text-success' : 'text-text-muted' }}">{{ $route['offered'] ? 'Offered to agents' : 'Hidden from agents' }}</div>
                </x-dashboard.td>
                @foreach ($gateways as $key => $g)
                    @php $row = $rows[$key.'|'.$route['country'].'|'.$route['method']] ?? null; @endphp
                    <x-dashboard.td class="align-top text-xs">
                        @php $state = $row?->state ?? 'unavailable'; @endphp
                        <x-dashboard.badge :status="match ($state) { 'verified_api', 'verified_manual' => 'completed', 'disabled' => 'rejected', default => 'pending' }">
                            {{ str_replace('_', ' ', $state) }}
                        </x-dashboard.badge>
                        @if ($row?->verified_at)
                            <div class="mt-1 text-text-muted">since {{ $row->verified_at->toFormattedDateString() }}</div>
                        @endif
                        @if ($row?->manual_expires_at)
                            <div class="text-text-muted">expires {{ $row->manual_expires_at->toFormattedDateString() }}</div>
                        @endif
                        @if ($row && ($row->provider_min !== null || $row->provider_max !== null))
                            <div class="text-text-muted">limits {{ $row->provider_min ?? '—' }} to {{ $row->provider_max ?? '—' }} {{ $row->currency }}</div>
                        @endif
                        @if ($row?->note)
                            <div class="text-text-muted">{{ $row->note }}</div>
                        @endif

                        <details class="mt-2">
                            <summary class="cursor-pointer text-primary">Change</summary>
                            <div class="mt-2 space-y-2">
                                @if ($state !== 'disabled')
                                    <form method="POST" action="{{ route('admin.payout-coverage.disable') }}" class="space-y-1">
                                        @csrf
                                        <input type="hidden" name="gateway" value="{{ $key }}">
                                        <input type="hidden" name="country" value="{{ $route['country'] }}">
                                        <input type="hidden" name="method" value="{{ $route['method'] }}">
                                        <input type="text" name="note" placeholder="Reason (optional)" class="w-full rounded border border-border-subtle px-2 py-1">
                                        <x-dashboard.button type="submit" size="sm" variant="danger">Disable</x-dashboard.button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.payout-coverage.reset') }}">
                                        @csrf
                                        <input type="hidden" name="gateway" value="{{ $key }}">
                                        <input type="hidden" name="country" value="{{ $route['country'] }}">
                                        <input type="hidden" name="method" value="{{ $route['method'] }}">
                                        <x-dashboard.button type="submit" size="sm" variant="secondary">Clear disable</x-dashboard.button>
                                    </form>
                                @endif
                                @if (! in_array($state, ['verified_api', 'disabled'], true))
                                    <form method="POST" action="{{ route('admin.payout-coverage.enable') }}" class="space-y-1" onsubmit="return confirm('Enable this route manually? This is recorded in the audit log.');">
                                        @csrf
                                        <input type="hidden" name="gateway" value="{{ $key }}">
                                        <input type="hidden" name="country" value="{{ $route['country'] }}">
                                        <input type="hidden" name="method" value="{{ $route['method'] }}">
                                        <input type="hidden" name="confirm" value="1">
                                        <input type="text" name="test_reference" required placeholder="Successful test payout reference" class="w-full rounded border border-border-subtle px-2 py-1">
                                        <input type="text" name="note" required placeholder="Note" class="w-full rounded border border-border-subtle px-2 py-1">
                                        <x-dashboard.button type="submit" size="sm" variant="primary">Enable manually</x-dashboard.button>
                                    </form>
                                @endif
                            </div>
                        </details>
                    </x-dashboard.td>
                @endforeach
            </tr>
        @endforeach
    </x-dashboard.table>
</x-layout.page>
@endsection
