<?php

namespace App\Services\Engagement;

use App\Enums\CampaignTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Video and channel previews for the package form, with the rules a link must pass
 * before a creator can buy. Results are cached briefly so typing and re-checking a
 * link does not spend API quota each time.
 *
 * Status values: ok (checked and allowed), unverified (allowed, YouTube could not be
 * checked), rejected (not allowed, message says why), unavailable (blocked because
 * YouTube could not be checked).
 */
class YoutubeLookupService
{
    private const OEMBED = 'https://www.youtube.com/oembed';

    private const IMAGE_HOSTS = ['i.ytimg.com', 'yt3.ggpht.com', 'yt3.googleusercontent.com'];

    public function __construct(private YoutubeChannelApi $api) {}

    /**
     * @param  list<CampaignTask>  $tasks
     * @return array{status: string, message: ?string, video: ?array<string, mixed>, source: ?string}
     */
    public function video(?string $url, array $tasks = []): array
    {
        $url = TargetUrlValidator::normalize($url);
        $id = $url && TargetUrlValidator::isPostOrVideoUrl($url, 'youtube')
            ? TargetUrlValidator::extractYoutubeVideoId($url)
            : null;
        if (! $id) {
            return $this->result('rejected', 'Paste a link to a YouTube video, for example https://www.youtube.com/watch?v=...');
        }

        $lookup = $this->videoDetails($id);

        if ($lookup === null) {
            return $this->videoFallback($id);
        }
        if (! $lookup['found']) {
            return $this->result('rejected', "We couldn't find that video. Check that it's public or unlisted and the link is correct.");
        }

        $video = $lookup['video'];
        $problem = $this->videoProblem($video, $tasks);

        return $problem
            ? $this->result('rejected', $problem, $video, 'api')
            : $this->result('ok', null, $video, 'api');
    }

    /**
     * Channel card plus its most recent videos that pass the product's rules.
     *
     * @param  list<CampaignTask>  $tasks
     * @return array{status: string, message: ?string, channel: ?array<string, mixed>, videos: list<array<string, mixed>>}
     */
    public function channel(?string $url, array $tasks = [], ?int $recent = null): array
    {
        $recent ??= (int) config('platform_products.youtube_lookup.recent_videos', 4);
        $url = TargetUrlValidator::normalize($url);
        $ref = $url ? TargetUrlValidator::extractYoutubeChannelRef($url) : null;

        if (! $ref) {
            return $this->channelResult('rejected', 'Paste your YouTube channel link, for example https://www.youtube.com/@yourchannel');
        }
        if ($ref['type'] === 'custom') {
            return $this->channelResult('rejected', 'Use your channel @handle link (https://www.youtube.com/@yourchannel) or the /channel/ link.');
        }

        $params = match ($ref['type']) {
            'id' => ['id' => $ref['value']],
            'handle' => ['forHandle' => '@'.$ref['value']],
            'user' => ['forUsername' => $ref['value']],
        };

        $lookup = $this->cached(
            'yt:channel:'.$ref['type'].':'.strtolower($ref['value']),
            fn () => $this->fetchChannel($params),
        );

        if ($lookup === null) {
            return (bool) config('platform_products.youtube_lookup.block_when_unavailable', true)
                ? $this->channelResult('unavailable', "We couldn't check this channel right now. Please try again in a few minutes.")
                : $this->channelResult('unverified', "We couldn't check this channel right now. You can continue; we will check it again before your campaign starts.");
        }
        if (! $lookup['found']) {
            return $this->channelResult('rejected', "We couldn't find that channel. Check the link and try again.");
        }

        $channel = $lookup['channel'];
        $videos = $recent > 0 && ! empty($channel['uploads_playlist'])
            ? $this->recentVideos($channel['uploads_playlist'], $tasks, $recent)
            : [];
        unset($channel['uploads_playlist']);

        return $this->channelResult('ok', null, $channel, $videos);
    }

    public static function allowsPurchase(string $status): bool
    {
        return in_array($status, ['ok', 'unverified'], true);
    }

    /**
     * Null when YouTube could not be reached; found=false when no public or unlisted video has that ID.
     *
     * @return array{found: bool, video?: array<string, mixed>}|null
     */
    private function videoDetails(string $id): ?array
    {
        return $this->cached('yt:video:'.$id, function () use ($id) {
            $response = $this->api->get('videos', ['part' => 'snippet,status,contentDetails,statistics', 'id' => $id]);
            if ($response === null) {
                return null;
            }
            $item = $response['items'][0] ?? null;

            return is_array($item) ? ['found' => true, 'video' => $this->mapVideo($item)] : ['found' => false];
        });
    }

    /** @return array{found: bool, channel?: array<string, mixed>}|null */
    private function fetchChannel(array $params): ?array
    {
        $response = $this->api->get('channels', ['part' => 'snippet,contentDetails'] + $params);
        if ($response === null) {
            return null;
        }
        $item = $response['items'][0] ?? null;
        if (! is_array($item) || empty($item['id'])) {
            return ['found' => false];
        }

        $snippet = $item['snippet'] ?? [];
        $thumbs = $snippet['thumbnails'] ?? [];

        return ['found' => true, 'channel' => [
            'id' => (string) $item['id'],
            'title' => (string) ($snippet['title'] ?? ''),
            'handle' => isset($snippet['customUrl']) ? (string) $snippet['customUrl'] : null,
            'thumbnail' => $this->safeImage($thumbs['medium']['url'] ?? $thumbs['default']['url'] ?? null),
            'url' => 'https://www.youtube.com/channel/'.$item['id'],
            'uploads_playlist' => $item['contentDetails']['relatedPlaylists']['uploads'] ?? null,
        ]];
    }

    /**
     * @param  list<CampaignTask>  $tasks
     * @return list<array<string, mixed>>
     */
    private function recentVideos(string $playlistId, array $tasks, int $limit): array
    {
        $items = $this->cached('yt:uploads:'.$playlistId, function () use ($playlistId) {
            $list = $this->api->get('playlistItems', ['part' => 'contentDetails', 'playlistId' => $playlistId, 'maxResults' => 10]);
            if ($list === null) {
                return null;
            }

            $ids = [];
            foreach ((array) ($list['items'] ?? []) as $row) {
                $vid = $row['contentDetails']['videoId'] ?? null;
                if (is_string($vid) && preg_match('/^[A-Za-z0-9_-]{6,32}$/', $vid)) {
                    $ids[] = $vid;
                }
            }
            if ($ids === []) {
                return ['found' => true, 'videos' => []];
            }

            $videos = $this->api->get('videos', ['part' => 'snippet,status,contentDetails,statistics', 'id' => implode(',', $ids)]);
            if ($videos === null) {
                return null;
            }

            return ['found' => true, 'videos' => array_map(fn ($item) => $this->mapVideo($item), array_values(array_filter(
                (array) ($videos['items'] ?? []),
                'is_array',
            )))];
        });

        $usable = [];
        foreach ((array) ($items['videos'] ?? []) as $video) {
            if ($this->videoProblem($video, $tasks) === null) {
                $usable[] = $video;
            }
            if (count($usable) >= $limit) {
                break;
            }
        }

        return $usable;
    }

    /** @return array<string, mixed> */
    private function mapVideo(array $item): array
    {
        $snippet = $item['snippet'] ?? [];
        $status = $item['status'] ?? [];
        $thumbs = $snippet['thumbnails'] ?? [];
        $id = (string) ($item['id'] ?? '');

        return [
            'id' => $id,
            'url' => 'https://www.youtube.com/watch?v='.$id,
            'title' => (string) ($snippet['title'] ?? ''),
            'channel_id' => (string) ($snippet['channelId'] ?? ''),
            'channel_title' => (string) ($snippet['channelTitle'] ?? ''),
            'channel_url' => ! empty($snippet['channelId']) ? 'https://www.youtube.com/channel/'.$snippet['channelId'] : null,
            'thumbnail' => $this->safeImage($thumbs['medium']['url'] ?? $thumbs['default']['url'] ?? null),
            'duration' => $this->formatDuration($item['contentDetails']['duration'] ?? null),
            'privacy' => (string) ($status['privacyStatus'] ?? 'public'),
            'embeddable' => (bool) ($status['embeddable'] ?? true),
            'made_for_kids' => (bool) ($status['madeForKids'] ?? false),
            'live' => (string) ($snippet['liveBroadcastContent'] ?? 'none'),
            // YouTube omits commentCount when comments are turned off.
            'comments_disabled' => ! array_key_exists('commentCount', $item['statistics'] ?? []),
            'views' => isset($item['statistics']['viewCount']) ? (int) $item['statistics']['viewCount'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $video
     * @param  list<CampaignTask>  $tasks
     */
    private function videoProblem(array $video, array $tasks): ?string
    {
        if (($video['privacy'] ?? 'public') === 'private') {
            return 'That video is private. Make it public or unlisted, then try again.';
        }
        if (in_array($video['live'] ?? 'none', ['live', 'upcoming'], true)) {
            return 'Live streams and upcoming premieres cannot be used. Pick a video that has finished uploading.';
        }
        if (in_array(CampaignTask::Watch, $tasks, true) && ! ($video['embeddable'] ?? true)) {
            return 'That video does not allow playback on other sites. Turn on "Allow embedding" in YouTube Studio, then try again.';
        }
        if (in_array(CampaignTask::Comment, $tasks, true)) {
            if ($video['made_for_kids'] ?? false) {
                return 'That video is set as made for kids, so YouTube turns comments off. Pick a different video.';
            }
            if ($video['comments_disabled'] ?? false) {
                return 'Comments are turned off on that video. Turn them on in YouTube Studio, then try again.';
            }
        }

        return null;
    }

    /** @return array{status: string, message: ?string, video: ?array<string, mixed>, source: ?string} */
    private function videoFallback(string $id): array
    {
        if (! (bool) config('platform_products.youtube_lookup.oembed_fallback', true)) {
            return $this->result('unverified', "We couldn't check this video right now. You can continue; we will check it again before your campaign starts.");
        }

        $watchUrl = 'https://www.youtube.com/watch?v='.$id;
        try {
            $response = Http::timeout(6)->get(self::OEMBED, ['url' => $watchUrl, 'format' => 'json']);
        } catch (\Throwable $e) {
            Log::info('YouTube oEmbed request failed', ['exception' => $e::class]);

            return $this->result('unverified', "We couldn't check this video right now. You can continue; we will check it again before your campaign starts.");
        }

        if (in_array($response->status(), [401, 403, 404], true)) {
            return $this->result('rejected', "We couldn't find that video. Check that it's public or unlisted and the link is correct.");
        }

        $json = $response->successful() ? $response->json() : null;
        if (! is_array($json)) {
            return $this->result('unverified', "We couldn't check this video right now. You can continue; we will check it again before your campaign starts.");
        }

        return $this->result('unverified', 'Video found. Some checks will run again before your campaign starts.', [
            'id' => $id,
            'url' => $watchUrl,
            'title' => (string) ($json['title'] ?? ''),
            'channel_id' => '',
            'channel_title' => (string) ($json['author_name'] ?? ''),
            'channel_url' => is_string($json['author_url'] ?? null) && TargetUrlValidator::isYoutubeChannelUrl($json['author_url']) ? $json['author_url'] : null,
            'thumbnail' => $this->safeImage($json['thumbnail_url'] ?? null),
            'duration' => null,
        ], 'oembed');
    }

    /**
     * Caches answers from YouTube; null (YouTube unreachable) is never cached.
     */
    private function cached(string $key, callable $fetch): ?array
    {
        $hit = Cache::get($key);
        if (is_array($hit)) {
            return $hit;
        }

        $value = $fetch();
        if (is_array($value)) {
            $minutes = ($value['found'] ?? false) ? (int) config('platform_products.youtube_lookup.cache_minutes', 15) : 2;
            Cache::put($key, $value, now()->addMinutes(max(1, $minutes)));
        }

        return $value;
    }

    private function safeImage(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ! in_array(strtolower($parts['host'] ?? ''), self::IMAGE_HOSTS, true)) {
            return null;
        }

        return $url;
    }

    private function formatDuration(mixed $iso): ?string
    {
        if (! is_string($iso) || ! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m)) {
            return null;
        }
        $h = (int) ($m[1] ?? 0);
        $min = (int) ($m[2] ?? 0);
        $s = (int) ($m[3] ?? 0);

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $min, $s) : sprintf('%d:%02d', $min, $s);
    }

    /** @return array{status: string, message: ?string, video: ?array<string, mixed>, source: ?string} */
    private function result(string $status, ?string $message, ?array $video = null, ?string $source = null): array
    {
        return ['status' => $status, 'message' => $message, 'video' => $video, 'source' => $source];
    }

    /** @return array{status: string, message: ?string, channel: ?array<string, mixed>, videos: list<array<string, mixed>>} */
    private function channelResult(string $status, ?string $message, ?array $channel = null, array $videos = []): array
    {
        return ['status' => $status, 'message' => $message, 'channel' => $channel, 'videos' => $videos];
    }
}
