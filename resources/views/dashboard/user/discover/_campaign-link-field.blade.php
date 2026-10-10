@php
    $engagementMetric = $engagementMetric ?? \App\Enums\EngagementMetric::fromProductSlug($product->slug);
    $destinationLabel = match ($engagementMetric) {
        \App\Enums\EngagementMetric::Subscribers => 'Channel URL',
        null => 'Campaign link',
        default => 'Video URL',
    };
    $destinationHelp = match ($engagementMetric) {
        \App\Enums\EngagementMetric::Likes, \App\Enums\EngagementMetric::Comments => 'Public YouTube video URL where you want likes or comments. Channel links are not accepted.',
        \App\Enums\EngagementMetric::Views, \App\Enums\EngagementMetric::WatchHours => 'Public YouTube video URL where you want views or watch sessions. Channel links are not accepted.',
        \App\Enums\EngagementMetric::Subscribers => 'Your public YouTube channel link, for example https://www.youtube.com/@yourchannel. Video links are not accepted.',
        default => 'Public YouTube link for this campaign.',
    };
    $linkOptions = [
        'isCampaign' => true,
        'productSlug' => $product->slug,
        'urlPreviewUrl' => route('dashboard.services.url-preview'),
        'csrfToken' => csrf_token(),
        'initialTargetUrl' => $initialTargetUrl ?? '',
    ];
@endphp
<div
    x-data="platformCheckout([], @js($linkOptions))"
    x-init="$dispatch('target-url-changed', targetUrl)"
>
    <label for="campaign-target-url" class="block text-sm font-medium text-text-primary">
        {{ $destinationLabel }} <span class="text-danger">*</span>
    </label>
    <input
        id="campaign-target-url"
        type="url"
        x-model="targetUrl"
        @input="onTargetUrlInput(); $dispatch('target-url-changed', targetUrl)"
        placeholder="https://…"
        class="mt-2 w-full rounded-lg border-border-default bg-elevated text-text-primary text-sm"
    >
    <p class="mt-1 text-xs text-text-muted">{{ $destinationHelp }}</p>

    <div class="mt-3" x-show="targetUrl.trim().length > 8" x-cloak>
        <div x-show="previewLoading" class="rounded-lg border border-border-default bg-muted/30 px-3 py-2 text-xs text-text-muted">
            Loading preview…
        </div>
        <div x-show="!previewLoading && preview" class="space-y-2">
            <template x-if="preview && preview.mode === 'embed' && preview.iframe_src">
                <div class="overflow-hidden rounded-lg border border-border-default bg-elevated">
                    <iframe
                        class="w-full aspect-video min-h-[200px]"
                        :src="preview.iframe_src"
                        :title="preview.title || 'Content preview'"
                        allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        allowfullscreen
                        referrerpolicy="strict-origin-when-cross-origin"
                    ></iframe>
                </div>
            </template>
            <div
                x-show="preview && preview.mode === 'open_url'"
                class="rounded-lg border border-border-default bg-muted/20 px-4 py-3 flex flex-wrap items-center justify-between gap-3"
            >
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-text-muted" x-text="preview?.host || preview?.platform || 'URL'"></p>
                    <p class="text-sm text-text-primary truncate max-w-md" x-text="preview?.open_url || targetUrl"></p>
                </div>
                <a
                    class="shrink-0 text-sm font-semibold text-primary hover:underline"
                    :href="preview?.open_url || targetUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                >Open link</a>
            </div>
            <p class="text-xs text-text-muted" x-show="preview?.note" x-text="preview?.note"></p>
            <p class="text-xs text-text-muted" x-show="previewError" x-text="previewError"></p>
        </div>
    </div>
</div>
