<?php

namespace Tests\Feature\Catalog;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ServicesHubTest extends TestCase
{
    use RefreshDatabase;

    private function seedYoutubeProduct(string $slug = 'youtube-views-lite', bool $featured = false): PlatformProduct
    {
        Artisan::call('catalog:backfill-hierarchy');
        $service = ProductType::query()->where('slug', 'social_service')->firstOrFail();
        $category = ServiceCategory::query()->where('slug', 'youtube')->firstOrFail();

        $product = $this->forceCreatePlatformProduct([
            'product_type_id' => $service->id,
            'service_category_id' => $category->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'YouTube Views Lite',
            'slug' => $slug,
            'short_description' => 'Grow your YouTube video reach',
            'description' => 'A test YouTube package',
            'status' => PlatformProductStatus::Published,
            'is_featured' => $featured,
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

    public function test_services_landing_shows_product_marketplace(): void
    {
        $this->seedYoutubeProduct();

        $this->get(route('services'))
            ->assertOk()
            ->assertSee('Find the campaign service you need')
            ->assertSee('Available campaign services')
            ->assertSee('YouTube')
            ->assertSee('YouTube Views Lite');
    }

    public function test_services_landing_filters_by_category(): void
    {
        $this->seedYoutubeProduct();

        $this->get(route('services', ['category' => 'youtube']))
            ->assertOk()
            ->assertSee('YouTube Views Lite');

        $this->get(route('services', ['category' => 'facebook']))
            ->assertOk()
            ->assertDontSee('YouTube Views Lite');
    }

    public function test_services_filter_partial_returns_results_fragment(): void
    {
        $this->seedYoutubeProduct();

        $this->get(route('services', ['category' => 'youtube']), [
            'X-Services-Filter' => '1',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertSee('YouTube Views Lite')
            ->assertSee('Available campaign services')
            ->assertDontSee('Find the campaign service you need');
    }

    public function test_group_page_lists_category_products(): void
    {
        $this->seedYoutubeProduct();

        $this->get(route('services.segment', 'youtube'))
            ->assertOk()
            ->assertSee('YouTube')
            ->assertSee('YouTube Views Lite')
            ->assertSee('Products in');
    }

    public function test_canonical_product_url_resolves_category_to_product(): void
    {
        $this->seedYoutubeProduct();

        $this->get(route('services.show', [
            'type' => 'youtube',
            'productSlug' => 'youtube-views-lite',
        ]))
            ->assertOk()
            ->assertSee('YouTube Views Lite');
    }

    public function test_nested_legacy_url_redirects_to_canonical(): void
    {
        $this->seedYoutubeProduct('youtube-views-lite');

        $this->get(route('services.nested.show', [
            'category' => 'youtube',
            'service' => 'social_service',
            'productSlug' => 'youtube-views-lite',
        ]))
            ->assertRedirect('/services/youtube/youtube-views-lite');
    }

    public function test_legacy_type_product_url_redirects_to_canonical(): void
    {
        $this->seedYoutubeProduct('youtube-views-lite');

        $this->get(route('services.show', [
            'type' => 'social_service',
            'productSlug' => 'youtube-views-lite',
        ]))
            ->assertRedirect('/services/youtube/youtube-views-lite');
    }

    public function test_product_slug_segment_redirects_to_canonical(): void
    {
        $this->seedYoutubeProduct('youtube-views-lite');

        $this->get('/services/youtube-views-lite')
            ->assertRedirect('/services/youtube/youtube-views-lite');
    }

    public function test_services_search_finds_products(): void
    {
        $this->seedYoutubeProduct();

        $this->get(route('services', ['q' => 'YouTube Views']))
            ->assertOk()
            ->assertSee('YouTube Views Lite')
            ->assertSee('result');
    }

    public function test_unknown_segment_returns_404(): void
    {
        Artisan::call('catalog:backfill-hierarchy');

        $this->get('/services/not-a-real-group-or-type')
            ->assertNotFound();
    }

    public function test_legacy_retired_slugs_redirect_to_services_hub(): void
    {
        Artisan::call('catalog:backfill-hierarchy');

        $this->get('/services/network-services')
            ->assertRedirect(route('services'));
        $this->get('/services/digital-services')
            ->assertRedirect(route('services'));
    }

    public function test_wrong_category_in_product_url_redirects_to_canonical(): void
    {
        $this->seedYoutubeProduct('youtube-views-lite');

        $this->get('/services/facebook/youtube-views-lite')
            ->assertRedirect('/services/youtube/youtube-views-lite');
    }
}
