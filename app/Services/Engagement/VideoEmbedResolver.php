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

        // Standard player (not the Shorts layout) so the play control stays usable on a phone.
        $origin = rtrim((string) (request()->getHttpHost() ? request()->getSchemeAndHttpHost() : config('app.url')), '/');
        $embedUrl = 'https://www.youtube-nocookie.com/embed/'.rawurlencode($id)
            .'?rel=0&modestbranding=1&playsinline=1&controls=1&enablejsapi=1&iv_load_policy=3&origin='.rawurlencode($origin);
        $html = '<iframe data-youtube-player class="aspect-video w-full rounded-lg bg-black" src="'.e($embedUrl).'" title="YouTube video" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>';

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
