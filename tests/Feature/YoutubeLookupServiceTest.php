<?php

namespace Tests\Feature;

use App\Enums\CampaignTask;
use App\Models\SystemSetting;
use App\Services\Engagement\YoutubeLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YoutubeLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO = 'dQw4w9WgXcQ';

    private const CHANNEL = 'UCcreatorchannel000000001';

    private int $videoCalls = 0;

    private function video(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => self::VIDEO,
            'snippet' => [
                'title' => 'My video',
                'channelId' => self::CHANNEL,
                'channelTitle' => 'Creator',
                'liveBroadcastContent' => 'none',
                'thumbnails' => ['medium' => ['url' => 'https://i.ytimg.com/vi/'.self::VIDEO.'/mqdefault.jpg']],
            ],
            'status' => ['privacyStatus' => 'public', 'embeddable' => true, 'madeForKids' => false],
            'contentDetails' => ['duration' => 'PT4M13S'],
            'statistics' => ['viewCount' => '10', 'commentCount' => '2'],
        ], $overrides);
    }

    private ?array $apiVideo = null;

    private array $apiUploads = [];

    private bool $faked = false;

    /** @param  array<string, mixed>|null  $video  null = YouTube down */
    private function fakeApi(?array $video, array $uploads = []): void
    {
        $this->apiVideo = $video;
        $this->apiUploads = $uploads;
        if ($this->faked) {
            return;
        }
        $this->faked = true;
        SystemSetting::set('api_youtube_data_key', 'test-key');

        Http::fake(function (HttpRequest $request) {
            $video = $this->apiVideo;
            $uploads = $this->apiUploads;
            $url = $request->url();
            if (str_contains($url, 'oembed')) {
                return Http::response([], 500);
            }
            if ($video === null) {
                return Http::response([], 500);
            }
            if (str_contains($url, '/playlistItems')) {
                return Http::response(['items' => array_map(fn ($v) => ['contentDetails' => ['videoId' => $v['id']]], $uploads)]);
            }
            if (str_contains($url, '/videos')) {
                $this->videoCalls++;
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

                return Http::response(['items' => ($query['id'] ?? '') === self::VIDEO ? [$video] : $uploads]);
            }
            if (str_contains($url, '/channels')) {
                return Http::response(['items' => [[
                    'id' => self::CHANNEL,
                    'snippet' => ['title' => 'Creator', 'customUrl' => '@creator', 'thumbnails' => ['default' => ['url' => 'https://example.com/avatar.jpg']]],
                    'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUcreatorchannel000000001']],
                ]]]);
            }

            return Http::response([], 404);
        });
    }

    public function test_public_video_is_ok_and_mapped(): void
    {
        $this->fakeApi($this->video());

        $result = app(YoutubeLookupService::class)->video('https://youtu.be/'.self::VIDEO, [CampaignTask::Watch]);

        $this->assertSame('ok', $result['status']);
        $this->assertSame('My video', $result['video']['title']);
        $this->assertSame('4:13', $result['video']['duration']);
        $this->assertSame(self::CHANNEL, $result['video']['channel_id']);
        $this->assertTrue(YoutubeLookupService::allowsPurchase($result['status']));
    }

    public function test_unlisted_video_is_allowed(): void
    {
        $this->fakeApi($this->video(['status' => ['privacyStatus' => 'unlisted']]));

        $this->assertSame('ok', app(YoutubeLookupService::class)->video('https://www.youtube.com/watch?v='.self::VIDEO)['status']);
    }

    public function test_video_rules_reject_private_live_non_embeddable_and_comments_off(): void
    {
        $cases = [
            [['status' => ['privacyStatus' => 'private']], [], 'private'],
            [['snippet' => ['liveBroadcastContent' => 'upcoming']], [], 'Live streams'],
            [['status' => ['embeddable' => false]], [CampaignTask::Watch], 'embedding'],
            [['status' => ['madeForKids' => true]], [CampaignTask::Comment], 'made for kids'],
        ];

        foreach ($cases as [$overrides, $tasks, $needle]) {
            \Illuminate\Support\Facades\Cache::flush();
            $this->fakeApi($this->video($overrides));

            $result = app(YoutubeLookupService::class)->video('https://www.youtube.com/watch?v='.self::VIDEO, $tasks);

            $this->assertSame('rejected', $result['status'], $needle);
            $this->assertStringContainsString($needle, $result['message']);
            $this->assertFalse(YoutubeLookupService::allowsPurchase($result['status']));
        }

        \Illuminate\Support\Facades\Cache::flush();
        $noComments = $this->video();
        unset($noComments['statistics']['commentCount']);
        $this->fakeApi($noComments);
        $result = app(YoutubeLookupService::class)->video('https://www.youtube.com/watch?v='.self::VIDEO, [CampaignTask::Comment]);
        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('Comments are turned off', $result['message']);
    }

    public function test_non_embeddable_video_is_fine_when_the_product_has_no_watch_step(): void
    {
        $this->fakeApi($this->video(['status' => ['embeddable' => false]]));

        $this->assertSame('ok', app(YoutubeLookupService::class)->video('https://www.youtube.com/watch?v='.self::VIDEO, [CampaignTask::Like])['status']);
    }

    public function test_non_youtube_link_is_rejected_without_calling_the_api(): void
    {
        $this->fakeApi($this->video());

        $result = app(YoutubeLookupService::class)->video('https://example.com/watch?v='.self::VIDEO);

        $this->assertSame('rejected', $result['status']);
        $this->assertSame(0, $this->videoCalls);
    }

    public function test_found_video_is_cached(): void
    {
        $this->fakeApi($this->video());
        $lookup = app(YoutubeLookupService::class);

        $lookup->video('https://www.youtube.com/watch?v='.self::VIDEO);
        $lookup->video('https://youtu.be/'.self::VIDEO);

        $this->assertSame(1, $this->videoCalls);
    }

    public function test_video_is_unverified_when_youtube_is_down_and_fallback_is_off(): void
    {
        config(['platform_products.youtube_lookup.oembed_fallback' => false]);
        $this->fakeApi(null);

        $result = app(YoutubeLookupService::class)->video('https://www.youtube.com/watch?v='.self::VIDEO);

        $this->assertSame('unverified', $result['status']);
        $this->assertTrue(YoutubeLookupService::allowsPurchase($result['status']));
    }

    public function test_channel_is_blocked_when_youtube_is_down_and_blocking_is_on(): void
    {
        config(['platform_products.youtube_lookup.block_when_unavailable' => true]);
        $this->fakeApi(null);

        $result = app(YoutubeLookupService::class)->channel('https://www.youtube.com/@creator');

        $this->assertSame('unavailable', $result['status']);
        $this->assertFalse(YoutubeLookupService::allowsPurchase($result['status']));
    }

    public function test_channel_returns_recent_videos_that_pass_the_rules_and_drops_untrusted_images(): void
    {
        $uploads = [
            $this->video(['id' => 'video000001']),
            $this->video(['id' => 'video000002', 'status' => ['privacyStatus' => 'private']]),
            $this->video(['id' => 'video000003']),
        ];
        $this->fakeApi($this->video(), $uploads);

        $result = app(YoutubeLookupService::class)->channel('https://www.youtube.com/@creator', [CampaignTask::Watch], 4);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(self::CHANNEL, $result['channel']['id']);
        $this->assertNull($result['channel']['thumbnail']);
        $this->assertArrayNotHasKey('uploads_playlist', $result['channel']);
        $this->assertSame(['video000001', 'video000003'], array_column($result['videos'], 'id'));
    }

    public function test_custom_channel_links_are_rejected(): void
    {
        $this->fakeApi($this->video());

        $result = app(YoutubeLookupService::class)->channel('https://www.youtube.com/c/oldname');

        $this->assertSame('rejected', $result['status']);
    }
}
