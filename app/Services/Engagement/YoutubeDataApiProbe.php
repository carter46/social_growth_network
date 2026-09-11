<?php

namespace App\Services\Engagement;

use App\Enums\EngagementMetric;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YoutubeDataApiProbe implements EngagementProbeInterface
{
    public function supports(string $platform): bool
    {
        return $platform === 'youtube';
    }

    public function fetchCount(string $platform, EngagementMetric $metric, string $url): ?int
    {
        if (! $this->supports($platform)) {
            return null;
        }

        $apiKey = trim((string) SystemSetting::get('api_youtube_data_key', ''));
        if ($apiKey === '') {
            return null;
        }

        $videoId = TargetUrlValidator::extractYoutubeVideoId($url);
        if (! $videoId) {
            return null;
        }

        try {
            $response = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'statistics,status',
                'id' => $videoId,
                'key' => $apiKey,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $item = $response->json('items.0');
            if (! is_array($item)) {
                return null;
            }

            $stats = $item['statistics'] ?? [];

            return match ($metric) {
                EngagementMetric::Likes => isset($stats['likeCount']) ? (int) $stats['likeCount'] : null,
                EngagementMetric::Comments => isset($stats['commentCount']) ? (int) $stats['commentCount'] : null,
                EngagementMetric::Views, EngagementMetric::WatchHours => isset($stats['viewCount']) ? (int) $stats['viewCount'] : null,
            };
        } catch (\Throwable $e) {
            Log::warning('YouTube Data API probe failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function isEmbeddable(string $url): ?bool
    {
        $apiKey = trim((string) SystemSetting::get('api_youtube_data_key', ''));
        $videoId = TargetUrlValidator::extractYoutubeVideoId($url);
        if ($apiKey === '' || ! $videoId) {
            return null;
        }

        try {
            $response = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'status',
                'id' => $videoId,
                'key' => $apiKey,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $embeddable = $response->json('items.0.status.embeddable');

            return is_bool($embeddable) ? $embeddable : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
