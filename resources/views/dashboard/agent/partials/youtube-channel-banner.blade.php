@php
    $ytIdentity = auth()->check() && \App\Services\Campaigns\YoutubeIdentityService::enabled()
        ? app(\App\Services\Campaigns\YoutubeIdentityService::class)->current(auth()->user())
        : null;
@endphp

@if ($ytIdentity?->status === 'failed' && ! request()->routeIs('agent.youtube-channel.*'))
    <div class="mx-auto w-full max-w-7xl px-4 pt-4 sm:px-6">
        <x-dashboard.alert type="danger">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span><strong>YouTube channel verification failed.</strong> We couldn't find your code in time, so you can't start YouTube tasks yet.</span>
                <form method="POST" action="{{ route('agent.youtube-channel.retry') }}">
                    @csrf
                    <x-dashboard.button type="submit" size="sm">Retry</x-dashboard.button>
                </form>
            </div>
        </x-dashboard.alert>
    </div>
@endif
