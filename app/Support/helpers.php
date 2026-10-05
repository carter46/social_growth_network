<?php

use App\Models\MediaAsset;
use App\Services\Media\MediaPathService;

if (! function_exists('media_url')) {
    /**
     * Resolve a public media URL from a MediaAsset and/or legacy path.
     */
    function media_url(?MediaAsset $asset = null, ?string $legacyPath = null, string $variant = 'medium'): ?string
    {
        return app(MediaPathService::class)->resolveUrl($asset, $legacyPath, $variant);
    }
}

if (! function_exists('media_url_from_id')) {
    function media_url_from_id(?int $mediaId, ?string $legacyPath = null, string $variant = 'medium'): ?string
    {
        if (! $mediaId) {
            return app(MediaPathService::class)->urlFromLegacyPath($legacyPath);
        }

        $asset = MediaAsset::query()->with('variants')->find($mediaId);

        return app(MediaPathService::class)->resolveUrl($asset, $legacyPath, $variant);
    }
}

if (! function_exists('absolute_url')) {
    /**
     * Turn a root-relative or scheme-relative URL into a fully absolute URL for email clients and external links.
     */
    function absolute_url(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url) || str_starts_with($url, 'data:')) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$url;
        }

        $base = rtrim((string) config('app.url'), '/');
        if ($base === '' || ! preg_match('#^https?://#i', $base)) {
            return $url;
        }

        return $base.(str_starts_with($url, '/') ? $url : '/'.$url);
    }
}

if (! function_exists('absolute_media_url_from_id')) {
    function absolute_media_url_from_id(?int $mediaId, ?string $legacyPath = null, string $variant = 'medium'): ?string
    {
        return absolute_url(media_url_from_id($mediaId, $legacyPath, $variant));
    }
}

if (! function_exists('email_branding_logo_url')) {
    /**
     * Absolute logo URL safe for email clients (PNG/JPEG, never WebP).
     */
    function email_branding_logo_url(?int $mediaId): ?string
    {
        return app(\App\Services\Branding\EmailLogoResolver::class)->absoluteUrl($mediaId);
    }
}

if (! function_exists('mask_secret')) {
    /**
     * Mask a stored secret for form placeholders (never put the real value in the input).
     * Example: "expl_live_abc123xyz" → "expl********xyz"
     */
    function mask_secret(?string $value, int $prefix = 4, int $suffix = 4): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $length = mb_strlen($value);
        if ($length <= 2) {
            return str_repeat('*', max($length, 4));
        }

        if ($length <= 8) {
            $prefix = 1;
            $suffix = 1;
        } elseif ($length < $prefix + $suffix + 2) {
            $prefix = min(2, (int) floor($length / 3));
            $suffix = min(2, (int) floor($length / 3));
        }

        $middle = max(4, $length - $prefix - $suffix);

        return mb_substr($value, 0, $prefix).str_repeat('*', min($middle, 12)).mb_substr($value, -$suffix);
    }
}
