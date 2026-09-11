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

        return match ($platform) {
            'youtube' => $this->youtube($url),
            'tiktok' => $this->tiktok($url),
            'instagram' => $this->openPrimary($url, 'instagram', 'Open the post on Instagram to complete this viewing task. Embeds do not reliably count as Instagram views.'),
            'facebook' => $this->openPrimary($url, 'facebook', 'Open the post on Facebook to complete this viewing task.'),
            'x', 'twitter' => $this->openPrimary($url, 'x', 'Open the post on X. Embedded posts do not add to X view counts.'),
            default => $this->openPrimary($url, $platform, 'Open the content on the platform to complete this task.'),
        };
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
            'note' => 'Click the native YouTube play button. Autoplay / scripted play does not count toward YouTube views. Completing the session pays your TaskPulse reward — it does not guarantee a platform view.',
        ];
    }

    /**
     * @return array{mode: string, html?: string, open_url: string, platform: string, note: string}
     */
    private function tiktok(string $url): array
    {
        if (preg_match('#/video/(\d+)#', $url, $m)) {
            $player = 'https://www.tiktok.com/player/v1/'.$m[1];
            $html = '<iframe class="w-full min-h-[480px] rounded-lg" src="'.e($player).'" title="TikTok video" allow="fullscreen" allowfullscreen></iframe>';

            return [
                'mode' => 'embed',
                'html' => $html,
                'open_url' => $url,
                'platform' => 'tiktok',
                'note' => 'Prefer watching in the player and also use Open on TikTok. Completing the session is a TaskPulse task completion — not a guaranteed TikTok view.',
            ];
        }

        return $this->openPrimary($url, 'tiktok', 'Open on TikTok to complete this viewing task.');
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
