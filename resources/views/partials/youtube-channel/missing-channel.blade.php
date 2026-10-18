@php
    $prefix = $prefix ?? 'agent';
    $oauthEnabled = $oauthEnabled ?? false;
    $returnTo = $returnTo ?? null;
    $isAgent = $prefix === 'agent';
    $fromLink = ($source ?? 'oauth') === 'link';
    $oauthParams = $returnTo ? ['return' => $returnTo] : [];
@endphp

<div class="space-y-4 rounded-xl border border-warning/30 bg-warning/10 px-4 py-4 text-sm" data-youtube-missing-channel data-source="{{ $fromLink ? 'link' : 'oauth' }}">
    <div>
        <p class="font-semibold text-text-primary">
            {{ $fromLink ? "We couldn't find a YouTube channel at that link" : "We couldn't find a YouTube channel on this Google account" }}
        </p>
        <p class="mt-1 text-text-secondary">
            @if ($isAgent)
                A channel is your public YouTube identity. We use it to check your YouTube tasks, such as comments and subscriptions.
            @else
                A channel is your public YouTube identity. Connecting it helps us identify your channel and videos when you set up campaigns.
            @endif
            If you don't have one yet, creating one is free, takes about a minute, and you never need to upload anything.
        </p>
    </div>

    <ol class="list-decimal space-y-1 pl-5 text-text-secondary">
        <li>Open YouTube's channel page and choose a name and handle.</li>
        <li>Come back to this page.</li>
        <li>{{ $oauthEnabled ? 'Choose "I\'ve created my channel, try again".' : 'Paste your new channel link below.' }}</li>
    </ol>

    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        <x-dashboard.button :href="\App\Http\Controllers\YoutubeChannelController::CREATE_CHANNEL_URL" target="_blank" rel="noopener">
            Create a YouTube channel
        </x-dashboard.button>
        @if ($oauthEnabled)
            <x-dashboard.button :href="route($prefix.'.youtube-channel.oauth', $oauthParams)" variant="secondary">
                I've created my channel, try again
            </x-dashboard.button>
        @else
            <x-dashboard.button type="button" variant="secondary" x-on:click="$dispatch('youtube-show-paste')">
                I've created my channel, add its link
            </x-dashboard.button>
        @endif
    </div>

    <details class="text-text-secondary">
        <summary class="cursor-pointer font-medium text-text-primary">My channel exists but isn't detected</summary>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @if ($fromLink)
                <li>Copy the link from your channel page, not from a video. It usually looks like youtube.com/@yourhandle.</li>
                <li>Make sure your channel is public.</li>
            @else
                <li>Make sure you signed in with the Google account that owns the channel. Many people have more than one.</li>
                <li>If your channel is a brand channel, pick it when Google asks which account or channel to use.</li>
            @endif
            @if ($oauthEnabled)
                <li><a href="{{ route($prefix.'.youtube-channel.oauth', $oauthParams) }}" class="text-primary hover:underline">{{ $fromLink ? 'Sign in with Google instead' : 'Use another Google account' }}</a></li>
            @endif
            @unless ($fromLink)
                <li>Or paste your channel link below instead.</li>
            @endunless
        </ul>
    </details>
</div>
