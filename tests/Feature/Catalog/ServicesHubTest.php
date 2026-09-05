<?php

namespace Tests\Feature\Catalog;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ServicesHubTest extends TestCase
{
    use RefreshDatabase;

    private function seedVpnProduct(string $slug = 'residential-vpn-demo', bool $featured = false): PlatformProduct
    {
        Artisan::call('catalog:backfill-hierarchy');
        $service = ProductType::query()->where('slug', 'vpn')->firstOrFail();

        $product = $this->forceCreatePlatformProduct([
            'product_type_id' => $service->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'Residential VPN Demo',
            'slug' => $slug,
            'short_description' => 'Test VPN product',
            'description' => 'A test VPN',
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

    public function test_services_landing_shows_groups_not_product_grid(): void
    {
        $this->seedVpnProduct();

        $this->get(route('services'))
            ->assertOk()
            ->assertSee('Network Services')
            ->assertSee('Social Media')
            ->assertSee('Documents & Receipts')
            ->assertSee('Browse Categories')
            ->assertSee('assets/images/services_1.jpg', false)
            ->assertSee('assets/images/Network Services_1.jpg', false)
            ->assertSee('assets/images/Communication_1.jpg', false)
            ->assertSee('assets/images/Social_Media.jpg', false)
            ->assertSee('assets/images/Website_Services.jpg', false)
            ->assertSee('assets/images/Business_Documents.jpg', false)
            ->assertSee('assets/images/flat-lay-real-estate-concept.jpg', false)
            ->assertDontSee('Residential VPN Demo');
    }

    public function test_group_page_shows_type_cards(): void
    {
        $this->seedVpnProduct();

        $this->get(route('services.segment', 'network-services'))
            ->assertOk()
            ->assertSee('Network Services')
            ->assertSee('VPN');
    }

    public function test_type_page_shows_featured_and_products(): void
    {
        $this->seedVpnProduct(featured: true);

        $this->get(route('services.segment', 'vpn'))
            ->assertRedirect('/services/network-services/vpn');

        $this->get('/services/network-services/vpn')
            ->assertOk()
            ->assertSee('Featured')
            ->assertSee('Residential VPN Demo');
    }

    public function test_product_show_url_and_legacy_redirect(): void
    {
        $this->seedVpnProduct('legacy-vpn-slug');

        $this->get(route('services.nested.show', [
            'category' => 'network-services',
            'service' => 'vpn',
            'productSlug' => 'legacy-vpn-slug',
        ]))
            ->assertOk()
            ->assertSee('Residential VPN Demo');

        $this->get(route('services.show', ['type' => 'vpn', 'productSlug' => 'legacy-vpn-slug']))
            ->assertRedirect('/services/network-services/vpn/legacy-vpn-slug');

        $this->get('/services/legacy-vpn-slug')
            ->assertRedirect('/services/network-services/vpn/legacy-vpn-slug');
    }

    public function test_services_search_finds_products(): void
    {
        $this->seedVpnProduct();

        $this->get(route('services', ['q' => 'Residential VPN']))
            ->assertOk()
            ->assertSee('Search results')
            ->assertSee('Residential VPN Demo');
    }

    public function test_unknown_segment_returns_404(): void
    {
        Artisan::call('catalog:backfill-hierarchy');

        $this->get('/services/not-a-real-group-or-type')
            ->assertNotFound();
    }

    public function test_legacy_division_redirects_to_group(): void
    {
        Artisan::call('catalog:backfill-hierarchy');

        $this->get('/services/digital-services')
            ->assertRedirect('/services/network-services');
    }

    public function test_trust_and_escrow_routes_to_services(): void
    {
        Artisan::call('catalog:backfill-hierarchy');

        $this->get('/services/trust-escrow')
            ->assertRedirect(route('services'));
    }

    public function test_wrong_type_in_product_url_redirects_to_canonical(): void
    {
        $this->seedVpnProduct('canonical-vpn-slug');

        $this->get('/services/email/canonical-vpn-slug')
            ->assertRedirect('/services/network-services/vpn/canonical-vpn-slug');
    }
}
