<?php

namespace App\Services\Engagement;

use App\Enums\EngagementMetric;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort public page scrape for aggregate counters.
 * First-class fallback when official APIs are missing or fail.
 */
class UrlScrapeProbe implements EngagementProbeInterface
{
    public function supports(string $platform): bool
    {
        return $platform === 'youtube';
    }

    public function fetchCount(string $platform, EngagementMetric $metric, string $url): ?int
    {
        // Public subscriber counts are rounded, so subscriber tasks are reviewed manually.
        if ($metric === EngagementMetric::Subscribers) {
            return null;
        }

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

            return $platform === 'youtube' ? $this->parseYoutube($html, $metric) : null;
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
}
