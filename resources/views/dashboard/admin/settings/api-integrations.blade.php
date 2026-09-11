@extends('layouts.dashboard-admin')

@section('title', 'API Integrations')

@section('content')
<x-layout.page
    title="API Integrations"
    subtitle="Optional platform API credentials for engagement probes. Campaigns keep working via URL scrape when an API is not configured or not approved."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['API Integrations', null],
    ]"
>
    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-dashboard.card class="space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-text-primary">YouTube Data API</h2>
                    <p class="text-xs text-text-muted">videos.list statistics + status.embeddable. <a class="underline" href="https://developers.google.com/youtube/v3/docs/videos/list" target="_blank" rel="noopener">Docs</a></p>
                </div>
                <span class="text-xs rounded-full px-2 py-0.5 {{ filled($youtubeKey) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ filled($youtubeKey) ? 'Connected' : 'Using scrape fallback' }}
                </span>
            </div>
            <form method="POST" action="{{ route('admin.settings.api-integrations.update') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="channel" value="youtube">
                <x-dashboard.input label="API key" name="api_youtube_data_key" :value="old('api_youtube_data_key', $youtubeKey)" />
                <div class="flex flex-wrap gap-2">
                    <x-dashboard.button type="submit">Save</x-dashboard.button>
                    <x-dashboard.button type="submit" formaction="{{ route('admin.settings.api-integrations.test') }}" formmethod="POST" variant="secondary">Test</x-dashboard.button>
                </div>
            </form>
        </x-dashboard.card>

        <x-dashboard.card class="space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-text-primary">TikTok</h2>
                    <p class="text-xs text-text-muted">Display API is for owned videos; Research API is approval-gated. Scrape is the practical path for arbitrary URLs. <a class="underline" href="https://developers.tiktok.com/doc/tiktok-api-v2-video-object" target="_blank" rel="noopener">Docs</a></p>
                </div>
                <span class="text-xs rounded-full px-2 py-0.5 {{ filled($tiktokClientKey) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ filled($tiktokClientKey) ? 'Configured' : 'Using scrape fallback' }}
                </span>
            </div>
            <form method="POST" action="{{ route('admin.settings.api-integrations.update') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="channel" value="tiktok">
                <x-dashboard.input label="Client key" name="api_tiktok_client_key" :value="old('api_tiktok_client_key', $tiktokClientKey)" />
                <x-dashboard.input label="Client secret" name="api_tiktok_client_secret" type="password" :value="old('api_tiktok_client_secret', $tiktokClientSecret)" />
                <div class="flex flex-wrap gap-2">
                    <x-dashboard.button type="submit">Save</x-dashboard.button>
                    <x-dashboard.button type="submit" formaction="{{ route('admin.settings.api-integrations.test') }}" formmethod="POST" variant="secondary">Test</x-dashboard.button>
                </div>
            </form>
        </x-dashboard.card>

        <x-dashboard.card class="space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-text-primary">Meta (Facebook / Instagram)</h2>
                    <p class="text-xs text-text-muted">Graph / Business Discovery for professional accounts. oEmbed is display-only.</p>
                </div>
                <span class="text-xs rounded-full px-2 py-0.5 {{ filled($metaAppId) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ filled($metaAppId) ? 'Configured' : 'Using scrape fallback' }}
                </span>
            </div>
            <form method="POST" action="{{ route('admin.settings.api-integrations.update') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="channel" value="meta">
                <x-dashboard.input label="App ID" name="api_meta_app_id" :value="old('api_meta_app_id', $metaAppId)" />
                <x-dashboard.input label="App secret" name="api_meta_app_secret" type="password" :value="old('api_meta_app_secret', $metaAppSecret)" />
                <x-dashboard.input label="Access token" name="api_meta_access_token" :value="old('api_meta_access_token', $metaAccessToken)" />
                <div class="flex flex-wrap gap-2">
                    <x-dashboard.button type="submit">Save</x-dashboard.button>
                    <x-dashboard.button type="submit" formaction="{{ route('admin.settings.api-integrations.test') }}" formmethod="POST" variant="secondary">Test</x-dashboard.button>
                </div>
            </form>
        </x-dashboard.card>

        <x-dashboard.card class="space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-text-primary">X (Twitter)</h2>
                    <p class="text-xs text-text-muted">API v2 public_metrics (paid tiers for most apps). Embedded posts do not add to view counts.</p>
                </div>
                <span class="text-xs rounded-full px-2 py-0.5 {{ filled($xBearerToken) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ filled($xBearerToken) ? 'Configured' : 'Using scrape fallback' }}
                </span>
            </div>
            <form method="POST" action="{{ route('admin.settings.api-integrations.update') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="channel" value="x">
                <x-dashboard.input label="Bearer token" name="api_x_bearer_token" type="password" :value="old('api_x_bearer_token', $xBearerToken)" />
                <div class="flex flex-wrap gap-2">
                    <x-dashboard.button type="submit">Save</x-dashboard.button>
                    <x-dashboard.button type="submit" formaction="{{ route('admin.settings.api-integrations.test') }}" formmethod="POST" variant="secondary">Test</x-dashboard.button>
                </div>
            </form>
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
