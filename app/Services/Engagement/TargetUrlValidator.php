<?php

namespace App\Services\Engagement;

use App\Enums\EngagementMetric;
use Illuminate\Support\Str;

class TargetUrlValidator
{
    /**
     * Normalize and expand common short links shape (best-effort).
     */
    public static function normalize(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $host = Str::startsWith($host, 'www.') ? substr($host, 4) : $host;

        // Expand youtu.be
        if ($host === 'youtu.be' && ! empty($parts['path'])) {
            $id = ltrim($parts['path'], '/');

            return 'https://www.youtube.com/watch?v='.urlencode($id);
        }

        return $url;
    }

    public static function platformFromUrl(?string $url): ?string
    {
        $url = self::normalize($url);
        if (! $url) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = Str::startsWith($host, 'www.') ? substr($host, 4) : $host;

        return self::hostMatches($host, ['youtube.com', 'youtu.be', 'm.youtube.com']) ? 'youtube' : null;
    }

    /**
     * Exact host or trusted subdomain (avoids notyoutube.com spoofing).
     *
     * @param  list<string>  $allowed
     */
    private static function hostMatches(string $host, array $allowed): bool
    {
        foreach ($allowed as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reject channel links for products that work on a single video.
     */
    public static function isPostOrVideoUrl(?string $url, ?string $expectedPlatform = null): bool
    {
        $url = self::normalize($url);
        if (! $url || self::platformFromUrl($url) !== 'youtube') {
            return false;
        }

        if ($expectedPlatform && $expectedPlatform !== 'youtube') {
            return false;
        }

        return self::extractYoutubeVideoId($url) !== null;
    }

    public static function extractYoutubeVideoId(?string $url): ?string
    {
        $url = self::normalize($url);
        if (! $url) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if (self::hostMatches(Str::startsWith($host, 'www.') ? substr($host, 4) : $host, ['youtu.be'])) {
            $id = ltrim($path, '/');

            return $id !== '' ? $id : null;
        }

        if (preg_match('#/(embed|shorts)/([A-Za-z0-9_-]{6,})#', $path, $m)) {
            return $m[2];
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $id = $query['v'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function isYoutubeChannelUrl(?string $url): bool
    {
        return self::extractYoutubeChannelRef($url) !== null;
    }

    /**
     * Channel reference from /@handle, /channel/UC..., /c/name or /user/name links.
     *
     * @return array{type: 'handle'|'id'|'custom'|'user', value: string}|null
     */
    public static function extractYoutubeChannelRef(?string $url): ?array
    {
        $url = self::normalize($url);
        if (! $url || self::platformFromUrl($url) !== 'youtube' || self::extractYoutubeVideoId($url) !== null) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        return match (true) {
            (bool) preg_match('#^/@([A-Za-z0-9._-]{3,100})/?#', $path, $m) => ['type' => 'handle', 'value' => strtolower($m[1])],
            (bool) preg_match('#^/channel/(UC[A-Za-z0-9_-]{22})/?#', $path, $m) => ['type' => 'id', 'value' => $m[1]],
            (bool) preg_match('#^/c/([A-Za-z0-9._-]{1,100})/?#', $path, $m) => ['type' => 'custom', 'value' => strtolower($m[1])],
            (bool) preg_match('#^/user/([A-Za-z0-9._-]{1,100})/?#', $path, $m) => ['type' => 'user', 'value' => strtolower($m[1])],
            default => null,
        };
    }

    /** Stable key for comparing two links to the same channel. */
    public static function youtubeChannelKey(?string $url): ?string
    {
        $ref = self::extractYoutubeChannelRef($url);

        return $ref ? $ref['type'].':'.$ref['value'] : null;
    }

    public static function assertValidForProduct(string $url, string $productSlug): void
    {
        $metric = EngagementMetric::fromProductSlug($productSlug);
        $platform = EngagementMetric::platformFromProductSlug($productSlug);

        if (! $metric || ! $platform) {
            return;
        }

        if (self::platformFromUrl($url) !== 'youtube') {
            throw new \InvalidArgumentException('Please provide a YouTube link.');
        }

        if ($metric->targetsChannel()) {
            if (! self::isYoutubeChannelUrl($url)) {
                throw new \InvalidArgumentException(
                    'Please provide your YouTube channel link (for example https://www.youtube.com/@yourchannel). Video links are not accepted.'
                );
            }

            return;
        }

        if (! self::isPostOrVideoUrl($url, 'youtube')) {
            throw new \InvalidArgumentException(
                'Please provide a public YouTube video URL for this product (channel links are not accepted).'
            );
        }
    }
}