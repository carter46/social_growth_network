<?php

namespace App\Services\Engagement;

class VideoEmbedResolver
{
    /**
     * @return array{mode: string, html?: string, open_url: string, platform: string, note: string}
     */
    public function resolve(?string $url, ?string $platform = null): array
    {
        $url = TargetUrlValidator::normalize($url) ?? (string) $url;
        $platform = $platform ?: TargetUrlValidator::platformFromUrl($url) ?: 'unknown';

        if ($platform !== 'youtube') {
            return $this->openPrimary($url, $platform, 'Open the content to complete this task.');
        }

        if (TargetUrlValidator::isYoutubeChannelUrl($url)) {
            return $this->openPrimary($url, 'youtube', 'Open the channel on YouTube to complete this task.');
        }

        return $this->youtube($url);
    }

    /**
     * @return array{mode: string, html?: string, open_url: string, platform: string, note: string}
     */
    private function youtube(string $url): array
    {
        $id = TargetUrlValidator::extractYoutubeVideoId($url);
        if (! $id) {
            return $this->openPrimary($url, 'youtube', 'Open on YouTube to watch.');
        }

        // No origin parameter: a host that does not match the page (http vs https, or a proxy)
        // makes YouTube paint a black player. Shorts need a tall frame; a 16:9 box leaves a tiny picture.
        $isShort = (bool) preg_match('#/shorts/#i', $url);
        $embedUrl = 'https://www.youtube.com/embed/'.rawurlencode($id)
            .'?rel=0&playsinline=1&controls=1&enablejsapi=1';
        $frame = $isShort
            ? 'position:relative;width:min(100%,22rem);aspect-ratio:9/16;margin-inline:auto;'
            : 'position:relative;width:100%;aspect-ratio:16/9;';
        $html = '<div class="overflow-hidden rounded-lg bg-black" style="'.e($frame).'"><iframe data-youtube-player style="position:absolute;inset:0;width:100%;height:100%;border:0;display:block;" src="'.e($embedUrl).'" title="YouTube video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>';

        return [
            'mode' => 'embed',
            'html' => $html,
            'open_url' => $url,
            'platform' => 'youtube',
            'note' => 'Press play on the video. The timer starts when it plays.',
        ];
    }


    /**
     * @return array{mode: string, open_url: string, platform: string, note: string}
     */
    private function openPrimary(string $url, string $platform, string $note): array
    {
        return [
            'mode' => 'open_url',
            'open_url' => $url,
            'platform' => $platform,
            'note' => $note,
        ];
    }
}
