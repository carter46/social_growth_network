<?php

namespace App\Services\Engagement;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Public YouTube Data API reads with the platform API key. Every method returns null
 * when the answer is unknown (no key, quota, network, private data); callers must never
 * treat null as success.
 */
class YoutubeChannelApi
{
    private const BASE = 'https://www.googleapis.com/youtube/v3/';

    public function hasKey(): bool
    {
        return $this->key() !== '';
    }

    /**
     * Canonical channel from a channel link (@handle, /channel/UC..., /user/...).
     * /c/custom links can't be resolved reliably and return null.
     *
     * @return array{id: string, title: string, handle: ?string, thumbnail: ?string, description: string}|null
     */
    public function resolveChannel(?string $url): ?array
    {
        $ref = TargetUrlValidator::extractYoutubeChannelRef($url);
        if (! $ref || $ref['type'] === 'custom') {
            return null;
        }

        $params = match ($ref['type']) {
            'id' => ['id' => $ref['value']],
            'handle' => ['forHandle' => '@'.$ref['value']],
            'user' => ['forUsername' => $ref['value']],
        };

        return $this->channel($params);
    }

    /** @return array{id: string, title: string, handle: ?string, thumbnail: ?string, description: string}|null */
    public function channelById(string $channelId): ?array
    {
        return $this->channel(['id' => $channelId]);
    }

    /** @return array{id: string, title: string, handle: ?string, thumbnail: ?string, description: string}|null */
    private function channel(array $params): ?array
    {
        $item = $this->get('channels', ['part' => 'snippet'] + $params)['items'][0] ?? null;
        if (! is_array($item) || empty($item['id'])) {
            return null;
        }

        $snippet = $item['snippet'] ?? [];

        return [
            'id' => (string) $item['id'],
            'title' => (string) ($snippet['title'] ?? ''),
            'handle' => isset($snippet['customUrl']) ? (string) $snippet['customUrl'] : null,
            'thumbnail' => $snippet['thumbnails']['default']['url'] ?? null,
            'description' => (string) ($snippet['description'] ?? ''),
        ];
    }

    /**
     * @return array{id: string, channel_id: string, comments_disabled: bool}|null
     */
    public function video(string $videoId): ?array
    {
        $lookup = $this->videoLookup($videoId);

        return ($lookup['found'] ?? false) ? array_diff_key($lookup, ['found' => true]) : null;
    }

    /**
     * Null means the API was unavailable; found=false means no public video with that ID.
     *
     * @return array{found: bool, id?: string, channel_id?: string, comments_disabled?: bool}|null
     */
    public function videoLookup(string $videoId): ?array
    {
        $response = $this->get('videos', ['part' => 'snippet,statistics', 'id' => $videoId]);
        if ($response === null) {
            return null;
        }

        $item = $response['items'][0] ?? null;
        if (! is_array($item)) {
            return ['found' => false];
        }

        return [
            'found' => true,
            'id' => (string) $item['id'],
            'channel_id' => (string) ($item['snippet']['channelId'] ?? ''),
            // YouTube omits commentCount when comments are turned off.
            'comments_disabled' => ! array_key_exists('commentCount', $item['statistics'] ?? []),
        ];
    }

    /**
     * A single comment by ID (top-level or reply). Null means the API was unavailable;
     * found=false means no such public comment (held for review, deleted or wrong ID).
     *
     * @return array{found: bool, video_id?: ?string, author_channel_id?: ?string, text?: string}|null
     */
    public function comment(string $commentId): ?array
    {
        $response = $this->get('comments', ['part' => 'snippet', 'id' => $commentId, 'textFormat' => 'plainText']);
        if ($response === null) {
            return null;
        }

        $item = $response['items'][0] ?? null;
        if (! is_array($item)) {
            return ['found' => false];
        }

        $snippet = $item['snippet'] ?? [];
        $videoId = $snippet['videoId'] ?? null;

        if ($videoId === null && ! empty($snippet['parentId'])) {
            $parent = $this->get('commentThreads', ['part' => 'snippet', 'id' => $snippet['parentId']])['items'][0] ?? null;
            $videoId = $parent['snippet']['videoId'] ?? null;
        }

        return [
            'found' => true,
            'video_id' => $videoId ? (string) $videoId : null,
            'author_channel_id' => isset($snippet['authorChannelId']['value']) ? (string) $snippet['authorChannelId']['value'] : null,
            'text' => (string) ($snippet['textDisplay'] ?? ''),
        ];
    }

    /**
     * true = the agent channel publicly subscribes to the target; false = public list without it;
     * null = unknown (subscriptions private, no key, API error).
     */
    public function isSubscribed(string $agentChannelId, string $targetChannelId): ?bool
    {
        $response = $this->get('subscriptions', [
            'part' => 'id',
            'channelId' => $agentChannelId,
            'forChannelId' => $targetChannelId,
            'maxResults' => 1,
        ]);

        if ($response === null) {
            return null;
        }

        return ! empty($response['items']);
    }

    private function key(): string
    {
        return trim((string) SystemSetting::get('api_youtube_data_key', ''));
    }

    /** @return array<string, mixed>|null */
    public function get(string $endpoint, array $params): ?array
    {
        $key = $this->key();
        if ($key === '') {
            return null;
        }

        try {
            $response = Http::timeout(8)->get(self::BASE.$endpoint, $params + ['key' => $key]);
            if (! $response->successful()) {
                Log::info('YouTube Data API request not successful', ['endpoint' => $endpoint, 'status' => $response->status()]);

                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::warning('YouTube Data API request failed', [
                'endpoint' => $endpoint,
                'exception' => $e::class,
                'error' => self::redactKey($e->getMessage()),
            ]);

            return null;
        }
    }

    /** HTTP client exception messages include the request URL, and with it the API key. */
    public static function redactKey(string $message): string
    {
        return (string) preg_replace('/([?&]key=)[^&\s"\']+/i', '$1[redacted]', $message);
    }
}
