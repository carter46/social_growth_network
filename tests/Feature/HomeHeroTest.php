<?php

namespace Tests\Feature;

use App\Models\PlatformProduct;
use App\Modules\Catalog\Services\CatalogBrowseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    private const NO_BOTS_LINE = "We don't use bots, fake views or fake subscribers.";

    public function test_home_hero_uses_white_youtube_visibility_treatment(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('assets/images/home_whitepng.png', false)
            ->assertSee('Get your <span class="text-red-600">YouTube</span> videos seen by more viewers.', false)
            ->assertSee('id="youtube-services"', false)
            ->assertSee('Start a campaign', false)
            ->assertSee(self::NO_BOTS_LINE, false)
            ->assertDontSee('Buy Watch Hours', false)
            ->assertDontSee('Watch &amp; Earn', false)
            ->assertSee(route('register'), false)
            ->assertSee('bg-red-600', false)
            ->assertDontSee('· YouTube Watch Hours', false)
            ->assertDontSee('Browse YouTube services', false)
            ->assertDontSee('assets/images/homeslider1.jpg', false)
            ->assertDontSee('home-services-q', false);
    }

    public function test_home_shows_only_youtube_catalog(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('YouTube services', false)
            ->assertSee('<span class="text-red-600">YouTube</span> Watch Hours', false)
            ->assertSee('home-yt-featured', false)
            ->assertSee('Promote your video', false)
            ->assertDontSee('Earn by completing available digital tasks', false)
            ->assertDontSee('Start earning', false)
            ->assertDontSee('Other platforms', false)
            ->assertDontSee('Need other social media services?', false)
            ->assertDontSee('TikTok', false)
            ->assertDontSee('Instagram', false)
            ->assertDontSee('id="services"', false)
            ->assertDontSee('id="creators"', false)
            ->assertDontSee('id="agents"', false)
            ->assertDontSee('Guaranteed verified human activity', false)
            ->assertDontSee('Crypto Cash Exchange', false);

        $youtube = app(CatalogBrowseService::class)->homeYouTubeCatalog();
        $this->assertNotNull($youtube['featured']);
        $this->assertSame('YouTube Watch Hours', $youtube['featured']['title'] ?? null);
        $otherTitles = collect($youtube['others'])->pluck('title')->all();
        $this->assertContains('YouTube Views', $otherTitles);
        $this->assertContains('YouTube Likes', $otherTitles);
        $this->assertContains('YouTube Comments + Watch Hours', $otherTitles);
        $this->assertNotContains('YouTube Subscribers + Watch Hours', $otherTitles);
        $this->assertNotContains('YouTube Subscribers + Watch Hours + Comments', $otherTitles);
    }

    public function test_home_youtube_cards_without_admin_image_render_no_stock_image(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');

        PlatformProduct::query()
            ->where('slug', 'like', 'youtube-%')
            ->update(['hero_image' => null, 'hero_media_id' => null]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $start = strpos($html, 'id="youtube-services"');
        $end = strpos($html, 'id="how-it-works"');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $youtubeChunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('More YouTube packages', $youtubeChunk);
        $this->assertStringContainsString('YouTube Likes', $youtubeChunk);
        $this->assertStringContainsString('YouTube Comments', $youtubeChunk);
        $this->assertStringNotContainsString('Social_Media.jpg', $youtubeChunk);
        $this->assertStringNotContainsString('<img', $youtubeChunk);
    }

    public function test_home_uses_visibility_wording_only(): void
    {
        $main = $this->mainContent($this->get(route('home'))->assertOk()->getContent());

        $this->assertStringContainsString(self::NO_BOTS_LINE, $main);
        foreach ([
            '/pay people/i',
            '/get paid/i',
            '/\bearn(s|ing|er|ers)?\b/i',
            '/\bagents?\b/i',
            '/organic/i',
            '/buy (likes|views|subscribers|watch hours)/i',
            '/real people/i',
            '/\bfarm/i',
        ] as $pattern) {
            $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($main), $pattern);
        }
    }

    public function test_home_carries_the_former_creators_page_sections(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Stay in control', false)
            ->assertSee('Ready to get your <span class="text-white">YouTube</span> videos seen?', false)
            ->assertSee('href="'.route('dashboard.services').'"', false);
    }

    public function test_creators_page_is_gone_and_redirects_home(): void
    {
        $this->get('/creators')->assertStatus(301)->assertRedirect('/');
    }

    public function test_header_shows_only_about_us_and_footer_links_agents(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $header = substr($html, 0, (int) strpos($html, '<main'));
        $footer = substr($html, (int) strpos($html, '<footer'));

        $this->assertStringContainsString('About Us', $header);
        foreach (['>Services<', 'For Creators', 'For Agents', 'Help Center'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $header);
        }
        $this->assertStringContainsString('href="'.route('login').'"', $header);
        $this->assertStringContainsString('href="'.route('register').'"', $header);
        $this->assertStringContainsString('href="'.route('agents').'"', $footer);
        $this->assertStringNotContainsString('For Creators', $footer);
        $this->assertStringNotContainsString('Join as an Agent', $footer);
    }

    public function test_agents_page_keeps_earning_wording_and_links_to_agent_onboarding(): void
    {
        $this->get(route('agents'))
            ->assertOk()
            ->assertSee('earn', false)
            ->assertSee('invite (referral) code', false)
            ->assertSee('href="'.route('agent-onboarding').'"', false)
            ->assertDontSee('/register/agent', false);
    }

    public function test_agents_page_header_has_only_agent_login_and_agent_signup(): void
    {
        $html = $this->get(route('agents'))->assertOk()->getContent();
        $header = substr($html, 0, (int) strpos($html, '<main'));
        $main = $this->mainContent($html);

        $this->assertStringNotContainsString('About Us', $header);
        $this->assertStringNotContainsString('aria-label="Open menu"', $header);
        $this->assertStringContainsString('href="'.route('agent-login').'"', $header);
        $this->assertStringContainsString('href="'.route('agent-onboarding').'"', $header);
        $this->assertStringNotContainsString('href="'.route('login').'"', $header);
        $this->assertStringNotContainsString('href="'.route('register').'"', $header);

        preg_match_all('/href="([^"#]+)"/', $main, $links);
        foreach (array_unique($links[1]) as $href) {
            $this->assertContains($href, [route('agent-login'), route('agent-onboarding')], $href);
        }
    }

    public function test_agent_login_page_links_to_agent_signup(): void
    {
        $this->get(route('agent-login'))
            ->assertOk()
            ->assertSee('Agent Login', false)
            ->assertSee('action="'.route('login').'"', false)
            ->assertSee('href="'.route('agent-onboarding').'"', false)
            ->assertDontSee('href="'.route('register').'"', false);
    }

    private function mainContent(string $html): string
    {
        $start = strpos($html, '<main');
        $end = strpos($html, '</main>');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }
}
