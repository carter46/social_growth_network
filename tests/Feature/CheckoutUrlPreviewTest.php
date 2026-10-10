<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Engagement\CheckoutUrlPreviewResolver;
use App\Services\Engagement\VideoEmbedResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CheckoutUrlPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');
    }

    public function test_resolver_builds_youtube_iframe_without_remote_api(): void
    {
        $payload = app(CheckoutUrlPreviewResolver::class)->resolve(
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube-watch-hours'
        );

        $this->assertSame('embed', $payload['mode']);
        $this->assertSame('youtube', $payload['platform']);
        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $payload['iframe_src'] ?? '');
    }

    public function test_preview_endpoint_returns_json_for_authenticated_creator(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->postJson(route('dashboard.services.url-preview'), [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'product_slug' => 'youtube-watch-hours',
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'embed')
            ->assertJsonPath('platform', 'youtube');
    }

    public function test_non_youtube_link_is_not_previewed(): void
    {
        $payload = app(CheckoutUrlPreviewResolver::class)->resolve(
            'https://www.instagram.com/p/CxS1Yg0Lk0N/',
            'youtube-likes'
        );

        $this->assertSame('open_url', $payload['mode']);
        $this->assertArrayNotHasKey('iframe_src', $payload);
        $this->assertSame('Please provide a YouTube link.', $payload['note']);
    }

    public function test_subscribers_preview_accepts_channel_and_rejects_video(): void
    {
        $channel = app(CheckoutUrlPreviewResolver::class)->resolve(
            'https://www.youtube.com/@examplechannel',
            'youtube-subscribers'
        );
        $video = app(CheckoutUrlPreviewResolver::class)->resolve(
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube-subscribers'
        );

        $this->assertSame('open_url', $channel['mode']);
        $this->assertSame('youtube', $channel['platform']);
        $this->assertStringContainsString('channel', $channel['note']);

        $this->assertSame('open_url', $video['mode']);
        $this->assertStringContainsString('Video links are not accepted', $video['note']);
    }

    public function test_agent_video_embed_resolver_still_embeds_youtube(): void
    {
        $payload = app(VideoEmbedResolver::class)->resolve(
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube'
        );

        $this->assertSame('embed', $payload['mode']);
        $this->assertStringContainsString('youtube.com/embed/', $payload['html'] ?? '');
    }

    public function test_agent_video_embed_resolver_opens_channel_links(): void
    {
        $payload = app(VideoEmbedResolver::class)->resolve(
            'https://www.youtube.com/@examplechannel',
            'youtube'
        );

        $this->assertSame('open_url', $payload['mode']);
        $this->assertArrayNotHasKey('html', $payload);
    }
}
