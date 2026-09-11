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
        if (! is_array($parts) || empty($parts['host'])) {
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

        return match (true) {
            str_contains($host, 'youtube.com'), $host === 'youtu.be' => 'youtube',
            str_contains($host, 'facebook.com'), str_contains($host, 'fb.watch'), str_contains($host, 'fb.com') => 'facebook',
            str_contains($host, 'instagram.com') => 'instagram',
            str_contains($host, 'tiktok.com') => 'tiktok',
            str_contains($host, 'twitter.com'), $host === 'x.com' => 'x',
            default => null,
        };
    }

    /**
     * Reject bare profile URLs for likes/comments/views that require a post.
     */
    public static function isPostOrVideoUrl(?string $url, ?string $expectedPlatform = null): bool
    {
        $url = self::normalize($url);
        if (! $url) {
            return false;
        }

        $platform = self::platformFromUrl($url);
        if (! $platform) {
            return false;
        }

        if ($expectedPlatform && $expectedPlatform !== $platform && ! ($expectedPlatform === 'twitter' && $platform === 'x')) {
            return false;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $path = rtrim($path, '/') ?: '/';

        return match ($platform) {
            'youtube' => self::extractYoutubeVideoId($url) !== null,
            'tiktok' => (bool) preg_match('#/(video|photo)/\d+#', $path),
            'instagram' => (bool) preg_match('#/(p|reel|tv)/[^/]+#', $path),
            'facebook' => str_contains($path, '/posts/')
                || str_contains($path, '/videos/')
                || str_contains($path, '/watch')
                || str_contains($path, '/reel/')
                || str_contains((string) parse_url($url, PHP_URL_QUERY), 'v='),
            'x' => (bool) preg_match('#/status/\d+#', $path),
            default => false,
        };
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

        if (str_contains($host, 'youtu.be')) {
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

    public static function assertValidForProduct(string $url, string $productSlug): void
    {
        $metric = EngagementMetric::fromProductSlug($productSlug);
        $platform = EngagementMetric::platformFromProductSlug($productSlug);

        if (! $metric || ! $platform) {
            return;
        }

        $expected = $platform === 'twitter' ? 'x' : $platform;

        if (! self::isPostOrVideoUrl($url, $expected) && ! self::isPostOrVideoUrl($url, $platform)) {
            throw new \InvalidArgumentException(
                'Please provide a public post or video URL for this product (profile links are not accepted).'
            );
        }

        $urlPlatform = self::platformFromUrl($url);
        if ($urlPlatform !== $expected) {
            throw new \InvalidArgumentException('The URL must match the selected platform product.');
        }
    }
}
