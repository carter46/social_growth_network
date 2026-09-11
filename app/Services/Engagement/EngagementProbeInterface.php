<?php

namespace App\Services\Engagement;

use App\Enums\EngagementMetric;

interface EngagementProbeInterface
{
    /**
     * Fetch aggregate engagement count for a public post/video URL.
     * Returns null when the count cannot be read.
     */
    public function fetchCount(string $platform, EngagementMetric $metric, string $url): ?int;

    public function supports(string $platform): bool;
}
