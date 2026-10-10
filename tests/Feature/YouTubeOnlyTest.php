<?php

namespace Tests\Feature;

use App\Enums\PlatformProductType;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\Order;
use App\Models\PlatformProduct;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Modules\Catalog\Services\PlatformCheckoutService;
use App\Services\Campaigns\CampaignParticipationService;
use App\Services\Engagement\TargetUrlValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class YouTubeOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The SQLite schema still references the dropped escrows table from transactions.
        if (! Schema::hasTable('escrows')) {
            Schema::create('escrows', fn (Blueprint $table) => $table->id());
        }

        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');
    }

    private function retiredProduct(): PlatformProduct
    {
        $category = ServiceCategory::query()->where('slug', 'instagram')->first()
            ?? $this->forceCreateServiceCategory([
                'name' => 'Instagram',
                'slug' => 'instagram',
                'is_active' => true,
                'sort_order' => 50,
            ]);

        return $this->forceCreatePlatformProduct([
            'slug' => 'instagram-likes',
            'title' => 'Instagram Likes',
            'product_type' => PlatformProductType::SocialService,
            'service_category_id' => $category->id,
            'short_description' => 'Retired',
            'description' => 'Retired',
            'status' => 'published',
            'base_price' => 5000,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
        ]);
    }

    private function campaign(PlatformProduct $product, string $targetUrl, ?string $metric = null): Campaign
    {
        return Campaign::query()->create([
            'creator_id' => User::factory()->creator()->create()->id,
            'platform_product_id' => $product->id,
            'title' => $product->title.' campaign',
            'target_url' => $targetUrl,
            'engagement_metric' => $metric,
            'quantity' => 5,
            'completed_count' => 0,
            'locked_creator_price' => 0,
            'locked_agent_reward' => 0,
            'status' => Campaign::STATUS_ACTIVE,
        ]);
    }

    public function test_only_youtube_products_are_public(): void
    {
        $retired = $this->retiredProduct();

        $publicSlugs = PlatformProduct::query()->visibleToPublic()->pluck('slug')->all();

        $this->assertNotContains('instagram-likes', $publicSlugs);
        $this->assertContains('youtube-watch-hours', $publicSlugs);
        $this->assertNotContains('youtube-subscribers', $publicSlugs, 'Subscribers stays draft until an admin publishes it.');
        $this->assertFalse($retired->fresh()->isVisibleToPublic());
        $this->assertFalse($retired->fresh()->isOffered());
    }

    public function test_agents_cannot_start_tasks_for_retired_platforms(): void
    {
        $campaign = $this->campaign($this->retiredProduct(), 'https://www.instagram.com/p/example/');
        $agent = User::factory()->agent()->kycApproved()->create();

        $this->assertFalse($campaign->isOpenForAgents());
        $this->assertFalse(Campaign::query()->openForAgents()->whereKey($campaign->id)->exists());

        $this->expectException(InvalidArgumentException::class);
        app(CampaignParticipationService::class)->start($agent, $campaign);
    }

    public function test_links_must_be_youtube_and_match_the_product(): void
    {
        $this->assertNull(TargetUrlValidator::platformFromUrl('https://www.tiktok.com/@example/video/1'));

        try {
            TargetUrlValidator::assertValidForProduct('https://www.youtube.com/@examplechannel', 'youtube-likes');
            $this->fail('Channel links should be rejected for video products.');
        } catch (InvalidArgumentException) {
        }

        try {
            TargetUrlValidator::assertValidForProduct('https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube-subscribers');
            $this->fail('Video links should be rejected for Subscribers.');
        } catch (InvalidArgumentException) {
        }

        TargetUrlValidator::assertValidForProduct('https://www.youtube.com/@examplechannel', 'youtube-subscribers');

        $this->assertSame('handle:examplechannel', TargetUrlValidator::youtubeChannelKey('https://youtube.com/@ExampleChannel/videos'));
        $this->assertNull(TargetUrlValidator::youtubeChannelKey('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_agent_cannot_take_two_subscriber_tasks_for_the_same_channel(): void
    {
        $subscribers = PlatformProduct::query()->where('slug', 'youtube-subscribers')->firstOrFail();
        $first = $this->campaign($subscribers, 'https://www.youtube.com/@examplechannel', 'subscribers');
        $sameChannel = $this->campaign($subscribers, 'https://youtube.com/@ExampleChannel/featured', 'subscribers');
        $otherChannel = $this->campaign($subscribers, 'https://www.youtube.com/@anotherchannel', 'subscribers');
        $agent = User::factory()->agent()->kycApproved()->create();

        CampaignParticipation::query()->create([
            'campaign_id' => $first->id,
            'agent_id' => $agent->id,
            'status' => CampaignParticipation::STATUS_APPROVED,
            'started_at' => now(),
            'reward_amount' => 0,
        ]);

        $service = app(CampaignParticipationService::class);

        $this->assertSame($otherChannel->id, $service->start($agent, $otherChannel)->campaign_id);

        $this->expectExceptionMessage('You already took a subscriber task for this YouTube channel.');
        $service->start($agent, $sameChannel);
    }

    public function test_pending_orders_for_retired_services_cannot_be_paid(): void
    {
        $retired = $this->retiredProduct();
        $youtube = PlatformProduct::query()->where('slug', 'youtube-likes')->firstOrFail();
        $checkout = app(PlatformCheckoutService::class);

        $retiredOrder = Order::factory()->create();
        $retiredOrder->items()->create([
            'item_type' => 'platform_product',
            'item_id' => $retired->id,
            'quantity' => 1,
            'unit_price' => 5000,
            'line_total' => 5000,
        ]);

        $youtubeOrder = Order::factory()->create();
        $youtubeOrder->items()->create([
            'item_type' => 'platform_product',
            'item_id' => $youtube->id,
            'quantity' => 1,
            'unit_price' => 5000,
            'line_total' => 5000,
        ]);

        $this->assertTrue($checkout->orderHasRetiredProducts($retiredOrder));
        $this->assertFalse($checkout->orderHasRetiredProducts($youtubeOrder));

        $this->expectExceptionMessage('This service is no longer offered, so this order cannot be paid.');
        $checkout->assertOrderProductsOffered($retiredOrder->fresh());
    }

    public function test_retired_public_service_urls_redirect_instead_of_404(): void
    {
        $this->get('/services/instagram')->assertRedirect(route('services'))->assertStatus(301);
        $this->get('/services/tiktok/tiktok-views')->assertRedirect(route('services'))->assertStatus(301);
        $this->get('/services/facebook/facebook-likes/facebook-page-likes')->assertRedirect(route('services'))->assertStatus(301);

        $response = $this->get('/services/facebook/youtube-views');
        $response->assertStatus(301);
        $this->assertStringContainsString('youtube', (string) $response->headers->get('Location'));
    }

    public function test_retired_dashboard_service_urls_redirect_with_message(): void
    {
        $creator = User::factory()->creator()->create();

        foreach ([
            route('dashboard.services.product', 'instagram-likes'),
            route('dashboard.services.checkout', 'instagram-likes'),
            route('dashboard.services.browse', 'tiktok'),
        ] as $url) {
            $this->actingAs($creator)->get($url)
                ->assertRedirect(route('dashboard.services'))
                ->assertSessionHas('error', 'That service is no longer offered.');
        }
    }

    public function test_non_http_links_are_rejected(): void
    {
        $this->assertNull(TargetUrlValidator::platformFromUrl('javascript://youtube.com/%0Aalert(1)'));

        $this->expectException(InvalidArgumentException::class);
        TargetUrlValidator::assertValidForProduct('javascript://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube-likes');
    }

    public function test_trim_archives_instead_of_deleting(): void
    {
        $retired = $this->retiredProduct();

        \App\Support\PlatformCatalogTrim::apply();

        $this->assertDatabaseHas('platform_products', ['id' => $retired->id, 'status' => 'archived']);
    }
}
