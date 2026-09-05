@extends('layouts.dashboard-admin')

@section('title', 'Domain providers')

@section('content')
<x-layout.page
    title="Domain providers"
    subtitle="Configure domain resellers used for search and pricing. Customers never see provider names."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['System', null],
        ['Domain providers', null],
    ]"
>
    @if(session('status'))
        <x-dashboard.alert type="success">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if(session('error'))
        <x-dashboard.alert type="danger">{{ session('error') }}</x-dashboard.alert>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($providers as $provider)
            <x-dashboard.card class="space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-text-primary">{{ $provider->display_name }}</h3>
                        <p class="text-xs text-text-muted">{{ $provider->key }}</p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $provider->enabled ? 'bg-success/10 text-success' : 'bg-muted text-text-muted' }}">
                        {{ $provider->enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-text-muted">Default</dt>
                        <dd>{{ $provider->is_default ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Fallback</dt>
                        <dd>{{ $provider->fallback_priority ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Sandbox</dt>
                        <dd>{{ $provider->sandbox ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Health</dt>
                        <dd>
                            @php
                                $healthClass = match($provider->health_status) {
                                    'healthy' => 'text-success',
                                    'degraded' => 'text-amber-700',
                                    'unavailable' => 'text-red-700',
                                    default => 'text-text-muted',
                                };
                            @endphp
                            <span class="{{ $healthClass }}">{{ ucfirst($provider->health_status) }}</span>
                            @if($provider->last_health_check_at)
                                <span class="block text-xs text-text-muted">Checked {{ $provider->last_health_check_at->diffForHumans() }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-text-muted">Credentials</dt>
                        <dd>{{ $provider->hasCredentials() ? 'Configured' : 'Missing' }}</dd>
                    </div>
                </dl>
                <x-dashboard.button :href="route('admin.domain-providers.edit', $provider)" size="sm" variant="secondary">Configure</x-dashboard.button>
            </x-dashboard.card>
        @endforeach
    </div>
</x-layout.page>
@endsection
