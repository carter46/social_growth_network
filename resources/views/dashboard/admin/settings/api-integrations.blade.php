@extends('layouts.dashboard-admin')

@section('title', 'API Integrations')

@section('content')
<x-layout.page
    title="API Integrations"
    subtitle="Optional YouTube Data API key for engagement probes. Campaigns keep working via URL scrape when the API is not configured."
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
            @unless (filled($youtubeKey))
                <x-dashboard.alert type="warning">
                    Without an API key, agent channels can't be checked automatically (verification codes go to admin review), creators' video and channel links are not matched, and subscribe and comment steps go to manual review.
                </x-dashboard.alert>
            @endunless
        </x-dashboard.card>

        <x-dashboard.card class="space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-text-primary">Connect with Google (agent channels)</h2>
                    <p class="text-xs text-text-muted">Optional. Lets agents link their YouTube channel by signing in with Google (read-only access). Off by default. Agents can always link with a verification code instead.</p>
                </div>
                <x-dashboard.badge :status="$oauthEnabled ? 'success' : 'neutral'">{{ $oauthEnabled ? 'On' : 'Off' }}</x-dashboard.badge>
            </div>
            <form method="POST" action="{{ route('admin.settings.api-integrations.update') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="channel" value="youtube_oauth">
                <x-dashboard.input label="Google OAuth client ID" name="youtube_oauth_client_id" :value="old('youtube_oauth_client_id', $oauthClientId)" />
                <x-dashboard.input
                    label="Google OAuth client secret"
                    name="youtube_oauth_client_secret"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="$oauthHasSecret ? 'Saved. Leave blank to keep it.' : ''"
                />
                <p class="text-xs text-text-muted break-all">Authorised redirect URI to add in Google Cloud: <span class="font-mono">{{ $oauthCallbackUrl }}</span></p>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="youtube_oauth_enabled" value="0">
                    <input type="checkbox" name="youtube_oauth_enabled" value="1" @checked(old('youtube_oauth_enabled', $oauthEnabled))>
                    Show "Connect with Google" to agents
                </label>
                <x-dashboard.button type="submit">Save</x-dashboard.button>
            </form>
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
