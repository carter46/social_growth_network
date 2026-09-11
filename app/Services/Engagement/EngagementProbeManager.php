<?php

namespace App\Services\Engagement;

use App\Enums\EngagementMetric;
use Illuminate\Support\Facades\Log;

class EngagementProbeManager
{
    public function __construct(
        private YoutubeDataApiProbe $youtubeApi,
        private UrlScrapeProbe $scrape,
    ) {}

    /**
     * API first (when configured), then immediate scrape. Null ⇒ manual verification.
     * Never cache across verification pre/post steps — callers need fresh counts.
     */
    public function fetchCount(string $platform, EngagementMetric $metric, string $url, bool $fresh = true): ?int
    {
        $platform = $platform === 'twitter' ? 'x' : $platform;

        $count = null;
        $source = null;

        if ($this->youtubeApi->supports($platform)) {
            $count = $this->youtubeApi->fetchCount($platform, $metric, $url);
            if ($count !== null) {
                $source = 'api';
            }
        }

        // Future: Meta/TikTok/X official probes when admin credentials exist.

        if ($count === null && $this->scrape->supports($platform)) {
            $count = $this->scrape->fetchCount($platform, $metric, $url);
            if ($count !== null) {
                $source = 'scrape';
            }
        }

        if ($count === null) {
            Log::info('Engagement probe returned null', compact('platform', 'url') + [
                'metric' => $metric->value,
                'source' => $source,
            ]);
        }

        return $count;
    }
}
