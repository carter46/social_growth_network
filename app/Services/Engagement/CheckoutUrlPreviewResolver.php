<?php

namespace App\Services\Engagement;

/**
 * Creator checkout destination preview — official client embeds only (no Graph/oEmbed APIs).
 * Separate from {@see VideoEmbedResolver} used on Agent task screens.
 *
 * @phpstan-type PreviewPayload array{
 *     mode: string,
 *     platform: string,
 *     open_url: string,
 *     note: string,
 *     iframe_src?: string,
 *     permalink?: string,
 *     widget?: string,
 *     title?: string,
 *     host?: string
 * }
 */
class CheckoutUrlPreviewResolver
{
    /**
     * @return PreviewPayload
     */
    public function resolve(?string $url, ?string $productSlug = null): array
    {
        $normalized = TargetUrlValidator::normalize($url);
        if (! $normalized) {
            return $this->openUrl((string) $url, 'unknown', 'Enter a valid public post or video URL to preview.');
        }

        if ($productSlug) {
            try {
                TargetUrlValidator::assertValidForProduct($normalized, $productSlug);
            } catch (\InvalidArgumentException $e) {
                return $this->openUrl($normalized, TargetUrlValidator::platformFromUrl($normalized) ?? 'unknown', $e->getMessage());
            }
        }

        $platform = TargetUrlValidator::platformFromUrl($normalized) ?? 'unknown';

        return match ($platform) {
            'youtube' => $this->youtube($normalized),
            'tiktok' => $this->tiktok($normalized),
            'instagram' => $this->instagram($normalized),
            'facebook' => $this->facebook($normalized),
            'x', 'twitter' => $this->x($normalized),
            default => $this->openUrl($normalized, $platform, 'Preview is not available for this URL. You can still continue if the URL is accepted at checkout.'),
        };
    }

    /**
     * @return PreviewPayload
     */
    private function youtube(string $url): array
    {
        $id = TargetUrlValidator::extractYoutubeVideoId($url);
        if (! $id) {
            return $this->openUrl($url, 'youtube', 'Could not parse a YouTube video ID. Open the link to confirm the destination.');
        }

        return [
            'mode' => 'embed',
            'platform' => 'youtube',
            'open_url' => $url,
            'iframe_src' => 'https://www.youtube.com/embed/'.rawurlencode($id).'?rel=0&playsinline=1',
            'note' => 'Confirm this is the video you want agents to work on.',
            'host' => 'youtube.com',
            'title' => 'YouTube video',
        ];
    }

    /**
     * @return PreviewPayload
     */
    private function tiktok(string $url): array
    {
        if (! preg_match('#/video/(\d+)#', $url, $m)) {
            return $this->openUrl($url, 'tiktok', 'Could not parse a TikTok video ID. Open the link to confirm the destination.');
        }

        return [
            'mode' => 'embed',
            'platform' => 'tiktok',
            'open_url' => $url,
            'iframe_src' => 'https://www.tiktok.com/player/v1/'.$m[1],
            'note' => 'Confirm this is the TikTok video you want agents to work on.',
            'host' => 'tiktok.com',
            'title' => 'TikTok video',
        ];
    }

    /**
     * @return PreviewPayload
     */
    private function instagram(string $url): array
    {
        $safe = self::httpsUrlOrEmpty($url);

        return [
            'mode' => 'widget',
            'widget' => 'instagram',
            'platform' => 'instagram',
            'open_url' => $safe,
            'permalink' => $safe,
            'note' => 'Public Instagram posts and Reels can preview here. Private or embed-disabled posts will not load — your URL is still accepted if valid.',
            'host' => 'instagram.com',
            'title' => 'Instagram post',
        ];
    }

    /**
     * @return PreviewPayload
     */
    private function facebook(string $url): array
    {
        $safe = self::httpsUrlOrEmpty($url);

        return [
            'mode' => 'widget',
            'widget' => 'facebook',
            'platform' => 'facebook',
            'open_url' => $safe,
            'permalink' => $safe,
            'note' => 'Public Facebook posts can preview here. Private posts will not load — your URL is still accepted if valid.',
            'host' => 'facebook.com',
            'title' => 'Facebook post',
        ];
    }

    /**
     * @return PreviewPayload
     */
    private function x(string $url): array
    {
        $safe = self::httpsUrlOrEmpty($url);

        return [
            'mode' => 'widget',
            'widget' => 'x',
            'platform' => 'x',
            'open_url' => $safe,
            'permalink' => $safe,
            'note' => 'Public posts on X can preview here. Restricted posts will not load — your URL is still accepted if valid.',
            'host' => 'x.com',
            'title' => 'X post',
        ];
    }

    /**
     * @return PreviewPayload
     */
    private function openUrl(string $url, string $platform, string $note): array
    {
        $safeUrl = self::httpsUrlOrEmpty($url);
        $host = $safeUrl !== '' ? parse_url($safeUrl, PHP_URL_HOST) : null;

        return [
            'mode' => 'open_url',
            'platform' => $platform,
            'open_url' => $safeUrl,
            'note' => $note,
            'host' => is_string($host) ? $host : null,
            'title' => 'Destination URL',
        ];
    }

    private static function httpsUrlOrEmpty(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }
}
