@extends('layouts.dashboard-admin')

@section('title', 'Configure '.$provider->display_name)

@section('content')
<x-layout.page
    :title="$provider->display_name"
    subtitle="Domain reseller credentials and routing."
    width="default"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Domain providers', route('admin.domain-providers')],
        [$provider->display_name, null],
    ]"
>
    @if(session('status'))
        <x-dashboard.alert type="success">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if(session('error'))
        <x-dashboard.alert type="danger">{{ session('error') }}</x-dashboard.alert>
    @endif

    <x-dashboard.card>
        <form method="POST" action="{{ route('admin.domain-providers.update', $provider) }}" class="space-y-5" autocomplete="off">
            @csrf
            @method('PUT')

            <label class="flex items-center gap-3">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $provider->enabled)) class="accent-primary">
                <span class="text-sm text-text-primary">Enabled</span>
            </label>

            <label class="flex items-center gap-3">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $provider->is_default)) class="accent-primary">
                <span class="text-sm text-text-primary">Default provider (tried first)</span>
            </label>

            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Fallback priority</label>
                <input type="number" name="fallback_priority" min="1" max="999" value="{{ old('fallback_priority', $provider->fallback_priority) }}" class="w-32 rounded-lg border-border-default bg-elevated text-sm">
                <p class="mt-1 text-xs text-text-muted">Lower numbers are tried earlier when not default. Leave empty for default provider.</p>
            </div>

            <label class="flex items-center gap-3">
                <input type="hidden" name="sandbox" value="0">
                <input type="checkbox" name="sandbox" value="1" @checked(old('sandbox', $provider->sandbox)) class="accent-primary">
                <span class="text-sm text-text-primary">Sandbox / test environment</span>
            </label>

            @if($sandboxHint ?? null)
                <p class="text-xs text-amber-600">{{ $sandboxHint }}</p>
            @endif

            @foreach($credentialLabels as $field => $label)
                @if($provider->isSecretCredentialField($field))
                    <x-dashboard.secret-input
                        :name="'credentials['.$field.']'"
                        :label="$label"
                        :stored="$provider->credential($field)"
                    />
                @else
                    <x-dashboard.input
                        :name="'credentials['.$field.']'"
                        :label="$label"
                        :value="old('credentials.'.$field, $provider->credential($field))"
                        autocomplete="off"
                        :hint="filled($provider->credential($field)) ? 'Saved on server. Edit to replace.' : 'No value stored yet.'"
                    />
                @endif
            @endforeach

            <div class="flex flex-wrap gap-2">
                <x-dashboard.button type="submit">Save</x-dashboard.button>
                <x-dashboard.button :href="route('admin.domain-providers')" variant="secondary">Cancel</x-dashboard.button>
            </div>
        </form>

        <form
            method="POST"
            action="{{ route('admin.domain-providers.test', $provider) }}"
            class="mt-4 pt-4 border-t border-border-default"
            data-ajax-form
            x-data="adminConnectionTest()"
            @submit.prevent="run($event)"
        >
            @csrf
            <p x-show="status" x-cloak class="mb-2 text-sm" :class="ok ? 'text-success' : 'text-danger'" x-text="status"></p>
            <x-dashboard.button type="submit" variant="secondary" size="sm" x-bind:disabled="testing">
                <span x-text="testing ? 'Testing…' : 'Test connection'">Test connection</span>
            </x-dashboard.button>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
