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
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
