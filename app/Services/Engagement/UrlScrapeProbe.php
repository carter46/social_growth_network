<?php

namespace App\Services\Engagement;

use App\Enums\EngagementMetric;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Best-effort public page scrape for aggregate counters.
 * First-class fallback when official APIs are missing or fail.
 */
class UrlScrapeProbe implements EngagementProbeInterface
{
    public function supports(string $platform): bool
    {
        return in_array($platform, ['youtube', 'facebook', 'instagram', 'tiktok', 'x', 'twitter'], true);
    }

    public function fetchCount(string $platform, EngagementMetric $metric, string $url): ?int
    {
        $normalized = TargetUrlValidator::normalize($url);
        if (! $normalized) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; TaskPulseBot/1.0; +https://example.local)',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($normalized);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            return match ($platform) {
                'youtube' => $this->parseYoutube($html, $metric),
                'tiktok' => $this->parseTiktok($html, $metric),
                'instagram' => $this->parseGenericOgOrJson($html, $metric, ['like', 'comment', 'view']),
                'facebook' => $this->parseGenericOgOrJson($html, $metric, ['like', 'comment', 'view']),
                'x', 'twitter' => $this->parseGenericOgOrJson($html, $metric, ['like', 'reply', 'view', 'impression']),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('URL scrape probe failed', [
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function parseYoutube(string $html, EngagementMetric $metric): ?int
    {
        foreach (['ytInitialPlayerResponse', 'ytInitialData'] as $marker) {
            if (! preg_match('/'.$marker.'\s*=\s*(\{.+?\})\s*;/s', $html, $m)) {
                continue;
            }
            $json = json_decode($m[1], true);
            if (! is_array($json)) {
                continue;
            }

            $stats = data_get($json, 'videoDetails') ? data_get($json, 'videoDetails') : null;
            // Common path: videoDetails is not always present; try microformat / player response stats.
            $like = data_get($json, 'videoDetails.likeCount')
                ?? data_get($json, 'contents.twoColumnWatchNextResults.results.results.contents.0.videoPrimaryInfoRenderer.videoActions.menuRenderer.topLevelButtons.0.segmentedLikeDislikeButtonViewModel.likeButtonViewModel.likeButtonViewModel.buttonViewModel.accessibilityText');

            if ($metric === EngagementMetric::Views || $metric === EngagementMetric::WatchHours) {
                $views = data_get($json, 'videoDetails.viewCount');
                if ($views !== null) {
                    return (int) $views;
                }
            }

            if ($metric === EngagementMetric::Likes && is_numeric($like)) {
                return (int) $like;
            }
        }

        // Fallback meta / interactionStatistic style
        if ($metric === EngagementMetric::Views || $metric === EngagementMetric::WatchHours) {
            if (preg_match('/interactionCount["\']?\s*:\s*["\']?(\d+)/i', $html, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/"viewCount"\s*:\s*"(\d+)"/', $html, $m)) {
                return (int) $m[1];
            }
        }

        if ($metric === EngagementMetric::Likes && preg_match('/"likeCount"\s*:\s*"(\d+)"/', $html, $m)) {
            return (int) $m[1];
        }

        if ($metric === EngagementMetric::Comments && preg_match('/"commentCount"\s*:\s*"(\d+)"/', $html, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function parseTiktok(string $html, EngagementMetric $metric): ?int
    {
        if (preg_match('/<script[^>]*id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>(.*?)<\/script>/si', $html, $m)) {
            $json = json_decode(html_entity_decode($m[1]), true);
            $stats = data_get($json, '__DEFAULT_SCOPE__.webapp.video-detail.itemInfo.itemStruct.stats')
                ?? data_get($json, '__DEFAULT_SCOPE__.webapp.video-detail.itemInfo.itemStruct.statsV2');

            if (is_array($stats)) {
                return match ($metric) {
                    EngagementMetric::Likes => isset($stats['diggCount']) ? (int) $stats['diggCount'] : null,
                    EngagementMetric::Comments => isset($stats['commentCount']) ? (int) $stats['commentCount'] : null,
                    EngagementMetric::Views, EngagementMetric::WatchHours => isset($stats['playCount']) ? (int) $stats['playCount'] : null,
                };
            }
        }

        return $this->parseGenericOgOrJson($html, $metric, ['digg', 'like', 'comment', 'play', 'view']);
    }

    /**
     * @param  list<string>  $keywords
     */
    private function parseGenericOgOrJson(string $html, EngagementMetric $metric, array $keywords): ?int
    {
        $keys = match ($metric) {
            EngagementMetric::Likes => array_values(array_filter($keywords, fn ($k) => Str::contains($k, ['like', 'digg']))),
            EngagementMetric::Comments => array_values(array_filter($keywords, fn ($k) => Str::contains($k, ['comment', 'reply']))),
            EngagementMetric::Views, EngagementMetric::WatchHours => array_values(array_filter($keywords, fn ($k) => Str::contains($k, ['view', 'play', 'impression']))),
        };

        if ($keys === []) {
            $keys = $keywords;
        }

        foreach ($keys as $key) {
            if (preg_match('/"'.$key.'Count"\s*:\s*"?(\d+)"?/i', $html, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/"'.$key.'_count"\s*:\s*"?(\d+)"?/i', $html, $m)) {
                return (int) $m[1];
            }
        }

        // og:description sometimes includes "1,234 likes"
        if (preg_match('/property="og:description"\s+content="([^"]+)"/i', $html, $m)
            || preg_match('/content="([^"]+)"\s+property="og:description"/i', $html, $m)) {
            $desc = $m[1];
            if ($metric === EngagementMetric::Likes && preg_match('/([\d,.]+)\s*likes?/i', $desc, $n)) {
                return (int) str_replace([',', '.'], '', $n[1]);
            }
            if ($metric === EngagementMetric::Comments && preg_match('/([\d,.]+)\s*comments?/i', $desc, $n)) {
                return (int) str_replace([',', '.'], '', $n[1]);
            }
        }

        return null;
    }
}
