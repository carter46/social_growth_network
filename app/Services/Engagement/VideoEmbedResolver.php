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

        $embedUrl = 'https://www.youtube.com/embed/'.rawurlencode($id).'?rel=0&playsinline=1';
        $html = '<iframe class="w-full aspect-video rounded-lg" src="'.e($embedUrl).'" title="YouTube video" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>';

        return [
            'mode' => 'embed',
            'html' => $html,
            'open_url' => $url,
            'platform' => 'youtube',
            'note' => 'Click the native YouTube play button. Autoplay / scripted play does not count toward YouTube views. Completing the session earns your task reward. It does not guarantee a platform view.',
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
