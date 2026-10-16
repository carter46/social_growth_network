<?php

namespace Tests\Feature\Catalog;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Modules\Catalog\Services\CatalogBrowseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Services have no public pages: every old /services link lands on the member catalog.
 */
class ServicesHubTest extends TestCase
{
    use RefreshDatabase;

    private function seedYoutubeProduct(string $slug = 'youtube-views'): PlatformProduct
    {
        Artisan::call('catalog:backfill-hierarchy');
        $service = ProductType::query()->where('slug', 'social_service')->firstOrFail();
        $category = ServiceCategory::query()->where('slug', 'youtube')->firstOrFail();

        $product = $this->forceCreatePlatformProduct([
            'product_type_id' => $service->id,
            'service_category_id' => $category->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'YouTube Views',
            'slug' => $slug,
            'short_description' => 'Grow your YouTube video reach',
            'description' => 'A test YouTube package',
            'status' => PlatformProductStatus::Published,
            'is_featured' => false,
            'base_price' => 3500,
            'sort_order' => 1,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
            'auto_renew' => false,
        ]);

        PlatformProductVariant::create([
            'platform_product_id' => $product->id,
            'name' => 'Standard',
            'label' => 'Standard',
            'sku' => $slug.'-std',
            'price' => 3500,
            'is_default' => true,
            'is_active' => true,
        ]);

        return $product;
    }

    public function test_services_index_redirects_to_member_catalog(): void
    {
        $this->get('/services')
            ->assertStatus(301)
            ->assertRedirect(route('dashboard.services'));
    }

    public function test_product_urls_redirect_to_member_product_page(): void
    {
        $this->seedYoutubeProduct();
        $target = route('dashboard.services.product', 'youtube-views');

        foreach ([
            '/services/youtube/youtube-views',
            '/services/youtube-views',
            '/services/youtube/social_service/youtube-views',
            '/services/facebook/youtube-views',
        ] as $url) {
            $this->get($url)->assertStatus(301)->assertRedirect($target);
        }
    }

    public function test_renamed_product_slug_redirects_to_current_product(): void
    {
        $this->seedYoutubeProduct();

        $this->get('/services/youtube/youtube-views-lite')
            ->assertRedirect(route('dashboard.services.product', 'youtube-views'));
    }

    public function test_category_and_unknown_urls_redirect_to_member_catalog(): void
    {
        Artisan::call('catalog:backfill-hierarchy');

        foreach (['/services/youtube', '/services/not-a-real-group-or-type', '/services/network-services'] as $url) {
            $this->get($url)->assertStatus(301)->assertRedirect(route('dashboard.services'));
        }
    }

    public function test_guest_opening_a_product_is_asked_to_log_in(): void
    {
        $this->seedYoutubeProduct();

        $this->get(route('dashboard.services.product', 'youtube-views'))
            ->assertRedirect(route('login'));
    }

    public function test_creator_sees_the_product_page_in_the_dashboard(): void
    {
        $product = $this->seedYoutubeProduct();
        Role::findOrCreate('user', 'web');
        $creator = User::factory()->create();
        $creator->assignRole('user');

        $this->actingAs($creator)
            ->get(route('dashboard.services.product', $product->slug))
            ->assertOk()
            ->assertSee('YouTube Views');
    }

    public function test_catalog_links_point_to_the_dashboard(): void
    {
        $product = $this->seedYoutubeProduct();
        $browse = app(CatalogBrowseService::class);

        $this->assertSame(route('dashboard.services.product', $product->slug), $browse->productUrl($product));
        $this->assertSame(route('dashboard.services.browse', 'youtube'), $browse->serviceListingUrl('youtube'));
    }
}
