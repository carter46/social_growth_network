@extends('layouts.dashboard-admin')

@section('title', $integration->name)

@section('content')
@php
    $webhookUrl = url('/webhooks/site-integrations/'.$integration->integration_id);
    $fresh = $freshCredentials ?? null;
    $credentialRows = [
        ['label' => 'Integration ID', 'value' => $fresh['integration_id'] ?? $integration->integration_id, 'secret' => false],
        ['label' => 'Client ID', 'value' => $fresh['client_id'] ?? $integration->client_id, 'secret' => false],
        ['label' => 'Client Secret', 'value' => $fresh['client_secret'] ?? $integration->client_secret, 'secret' => true],
        ['label' => 'Webhook Secret', 'value' => $fresh['webhook_secret'] ?? $integration->webhook_secret, 'secret' => true],
        ['label' => 'Webhook URL', 'value' => $fresh['webhook_url'] ?? $webhookUrl, 'secret' => false],
        ['label' => 'Base URL', 'value' => $integration->base_url, 'secret' => false],
    ];
@endphp
<x-layout.page
    title="{{ $integration->name }}"
    subtitle="{{ $integration->product?->title }}"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['System', null],
        ['Demo Site Integrate', route('admin.site-integrations')],
        [$integration->name, null],
    ]"
>
    <x-slot:actions>
        <form
            method="POST"
            action="{{ route('admin.site-integrations.check', $integration) }}"
            data-ajax-form
            x-data="adminConnectionTest()"
            @submit.prevent="run($event)"
        >
            @csrf
            <x-dashboard.button type="submit" variant="secondary" size="sm" x-bind:disabled="testing">
                <span x-text="testing ? 'Checking…' : 'Check connection'">Check connection</span>
            </x-dashboard.button>
        </form>
        <form method="POST" action="{{ route('admin.site-integrations.rotate', $integration) }}" onsubmit="return confirm('Rotate credentials? The site must update its config.');">
            @csrf
            <x-dashboard.button type="submit" variant="secondary" size="sm">Rotate keys</x-dashboard.button>
        </form>
    </x-slot:actions>

@php
    $integrationDocsSubtitle = 'Give these values to the merchant site. Use <strong>Rotate keys</strong> if secrets may have leaked. '
        .'<a href="'.route('developers.integrations.show', ['path' => 'MERCHANT-GUIDE']).'" class="text-primary hover:underline" target="_blank" rel="noopener">Integration docs</a>';
@endphp
    <x-dashboard.integration-credentials-card
        title="API credentials"
        :subtitle="$integrationDocsSubtitle"
        :credential-rows="$credentialRows"
        :badge-status="$integration->status->value"
        :connection-status="$integration->connection_status ?? 'unchecked'"
        :connection-message="$integration->last_error"
    />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-dashboard.card>
            <h3 class="mb-4 text-sm font-semibold text-text-primary">Integration settings</h3>
            <form method="POST" action="{{ route('admin.site-integrations.update', $integration) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <x-dashboard.input name="name" label="Name" :value="old('name', $integration->name)" required />
                <x-dashboard.input name="base_url" label="Base URL" type="url" :value="old('base_url', $integration->base_url)" required />
                <x-dashboard.input name="demo_user_email" label="Demo user email" type="email" :value="old('demo_user_email', $integration->demo_user_email)" />
                <x-dashboard.input name="demo_admin_email" label="Demo admin email" type="email" :value="old('demo_admin_email', $integration->demo_admin_email)" />
                <p class="text-xs text-text-muted">These emails must already exist on the merchant site with the correct roles — Hub SSO does not create users there.</p>
                <div>
                    <label class="mb-1 block text-sm font-medium">Status</label>
                    <x-dashboard.select name="status">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $integration->status->value) === $status->value)>{{ ucfirst($status->value) }}</option>
                        @endforeach
                    </x-dashboard.select>
                </div>
                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium">Capabilities</legend>
                    @foreach (\App\Models\SiteIntegration::defaultCapabilities() as $cap)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="capabilities[]" value="{{ $cap }}" class="rounded border-border-default"
                                @checked(in_array($cap, old('capabilities', $integration->capabilities ?? []), true))>
                            {{ $cap }}
                        </label>
                    @endforeach
                </fieldset>
                <x-dashboard.button type="submit">Save</x-dashboard.button>
            </form>
        </x-dashboard.card>

        <x-dashboard.card>
            <h3 class="mb-3 text-sm font-semibold">Connection logs</h3>
            <div class="space-y-2">
                @forelse ($logs as $log)
                    <div class="rounded-lg border border-border-default px-3 py-2 text-xs">
                        <div class="flex justify-between gap-2">
                            <span class="{{ $log->ok ? 'text-success' : 'text-danger' }}">{{ $log->ok ? 'OK' : 'Fail' }}</span>
                            <span class="text-text-muted">{{ $log->created_at->format('j M Y H:i') }}</span>
                        </div>
                        <p class="mt-1 break-words text-text-secondary">{{ $log->message }}</p>
                        @if ($log->http_status)
                            <p class="mt-1 text-[11px] text-text-muted">HTTP {{ $log->http_status }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-text-muted">No checks yet.</p>
                @endforelse
            </div>
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
