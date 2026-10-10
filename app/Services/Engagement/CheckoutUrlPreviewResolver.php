<?php

namespace App\Services\Engagement;

/**
 * Creator checkout destination preview: official YouTube embed only (no Data API calls).
 * Separate from {@see VideoEmbedResolver} used on Agent task screens.
 *
 * @phpstan-type PreviewPayload array{
 *     mode: string,
 *     platform: string,
 *     open_url: string,
 *     note: string,
 *     iframe_src?: string,
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
            return $this->openUrl((string) $url, 'unknown', 'Enter a valid public YouTube link to preview.');
        }

        if ($productSlug) {
            try {
                TargetUrlValidator::assertValidForProduct($normalized, $productSlug);
            } catch (\InvalidArgumentException $e) {
                return $this->openUrl($normalized, TargetUrlValidator::platformFromUrl($normalized) ?? 'unknown', $e->getMessage());
            }
        }

        if (TargetUrlValidator::platformFromUrl($normalized) !== 'youtube') {
            return $this->openUrl($normalized, 'unknown', 'Only YouTube links are supported.');
        }

        if (TargetUrlValidator::isYoutubeChannelUrl($normalized)) {
            return $this->openUrl($normalized, 'youtube', 'Open the link to confirm this is the channel you want agents to subscribe to.');
        }

        return $this->youtube($normalized);
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