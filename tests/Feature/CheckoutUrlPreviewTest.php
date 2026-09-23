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

    public function test_resolver_builds_instagram_widget_markup_payload(): void
    {
        $payload = app(CheckoutUrlPreviewResolver::class)->resolve(
            'https://www.instagram.com/p/CxS1Yg0Lk0N/',
            'instagram-likes'
        );

        $this->assertSame('widget', $payload['mode']);
        $this->assertSame('instagram', $payload['widget'] ?? null);
        $this->assertSame('instagram', $payload['platform']);
        $this->assertNotEmpty($payload['permalink'] ?? null);
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

    public function test_agent_video_embed_resolver_still_uses_open_url_for_instagram(): void
    {
        $payload = app(VideoEmbedResolver::class)->resolve(
            'https://www.instagram.com/p/CxS1Yg0Lk0N/',
            'instagram'
        );

        $this->assertSame('open_url', $payload['mode']);
        $this->assertSame('instagram', $payload['platform']);
        $this->assertArrayNotHasKey('iframe_src', $payload);
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

    public function test_agent_video_embed_resolver_still_uses_open_url_for_facebook_and_x(): void
    {
        $facebook = app(VideoEmbedResolver::class)->resolve(
            'https://www.facebook.com/someone/posts/1234567890',
            'facebook'
        );
        $x = app(VideoEmbedResolver::class)->resolve(
            'https://x.com/someone/status/1234567890123456789',
            'x'
        );

        $this->assertSame('open_url', $facebook['mode']);
        $this->assertSame('open_url', $x['mode']);
    }

    public function test_spoofed_instagram_host_is_rejected(): void
    {
        $payload = app(CheckoutUrlPreviewResolver::class)->resolve(
            'https://notinstagram.com/p/CxS1Yg0Lk0N/',
            'instagram-likes'
        );

        $this->assertSame('open_url', $payload['mode']);
        $this->assertNotSame('instagram', $payload['platform']);
    }

    public function test_resolver_builds_tiktok_and_x_client_payloads(): void
    {
        $tiktok = app(CheckoutUrlPreviewResolver::class)->resolve(
            'https://www.tiktok.com/@scout2015/video/6718335390845095173',
            'tiktok-views'
        );
        $x = app(CheckoutUrlPreviewResolver::class)->resolve(
            'https://x.com/Interior/status/507185938620219395',
            'twitter-likes'
        );

        $this->assertSame('embed', $tiktok['mode']);
        $this->assertStringContainsString('tiktok.com/player/v1/6718335390845095173', $tiktok['iframe_src'] ?? '');
        $this->assertSame('widget', $x['mode']);
        $this->assertSame('x', $x['widget'] ?? null);
    }
}
