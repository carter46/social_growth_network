<?php

namespace Tests\Feature\Catalog;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CatalogHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function seedHierarchy(): ProductType
    {
        Artisan::call('catalog:backfill-hierarchy');

        return ProductType::query()->where('slug', 'vpn')->firstOrFail();
    }

    private function seedVpnProduct(string $slug = 'residential-vpn-demo', bool $featured = false): PlatformProduct
    {
        $service = $this->seedHierarchy();

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
            'name' => '1 Month',
            'label' => '1 Month',
            'sku' => $slug.'-1m',
            'price' => 3500,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return $product;
    }

    public function test_backfill_is_idempotent_and_links_products(): void
    {
        $this->forceCreatePlatformProduct([
            'product_type' => PlatformProductType::SocialService,
            'title' => 'Legacy VPN',
            'slug' => 'legacy-vpn-link',
            'status' => PlatformProductStatus::Draft,
            'base_price' => 1000,
        ]);

        Artisan::call('catalog:backfill-hierarchy');
        $first = ServiceCategory::count();
        $services = ProductType::count();

        Artisan::call('catalog:backfill-hierarchy');

        $this->assertSame($first, ServiceCategory::count());
        $this->assertSame($services, ProductType::count());
        $this->assertGreaterThanOrEqual(6, $first);
        $this->assertFalse(ProductType::where('slug', 'escrow_service')->exists());

        $product = PlatformProduct::where('slug', 'legacy-vpn-link')->first();
        $this->assertNotNull($product->product_type_id);
        $this->assertSame('vpn', ProductType::find($product->product_type_id)?->slug);
        $this->assertSame('manual', $product->provider);
        $this->assertSame('manual', $product->fulfillment_mode);
        $this->assertFalse($product->auto_renew);
    }

    public function test_services_index_uses_db_service_categories(): void
    {
        $this->seedVpnProduct();

        $this->get(route('services'))
            ->assertOk()
            ->assertSee('Network Services')
            ->assertSee('Documents & Receipts')
            ->assertSee('Browse Categories')
            ->assertDontSee('Residential VPN Demo');
    }

    public function test_group_page_lists_services(): void
    {
        $this->seedVpnProduct();

        $this->get(route('services.segment', 'network-services'))
            ->assertOk()
            ->assertSee('Network Services')
            ->assertSee('VPN')
            ->assertDontSee('VPS');
    }

    public function test_service_page_lists_products(): void
    {
        $this->seedVpnProduct(featured: true);

        $this->get(route('services.segment', 'vpn'))
            ->assertRedirect('/services/network-services/vpn');

        $this->get(route('services.type', ['category' => 'network-services', 'service' => 'vpn']))
            ->assertOk()
            ->assertSee('Featured')
            ->assertSee('Residential VPN Demo');
    }

    public function test_service_category_rename_and_toggle_smoke(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $category = ServiceCategory::query()->where('key', 'network')->firstOrFail();
        $slug = $category->slug;

        $this->actingAs($admin)
            ->put(route('admin.service-categories.update', $category), [
                'name' => 'Network Hub Renamed',
                'is_active' => 1,
                'sort_order' => $category->sort_order,
            ])
            ->assertRedirect(route('admin.service-categories'));

        $category->refresh();
        $this->assertSame('Network Hub Renamed', $category->name);
        $this->assertSame($slug, $category->slug);

        $this->actingAs($admin)
            ->get(route('admin.service-categories'))
            ->assertOk()
            ->assertSee('Network Hub Renamed')
            ->assertDontSee('Add category');
    }

    public function test_product_filters_by_service_and_status(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $vpn = $this->seedVpnProduct('filter-vpn');
        $emailService = ProductType::query()->where('slug', 'email')->firstOrFail();

        $this->forceCreatePlatformProduct([
            'product_type_id' => $emailService->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'Email Only Product',
            'slug' => 'email-only-filter',
            'status' => PlatformProductStatus::Draft,
            'base_price' => 2000,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.platform-products', [
                'service' => $vpn->product_type_id,
                'status' => 'published',
            ]))
            ->assertOk()
            ->assertSee('Residential VPN Demo')
            ->assertDontSee('Email Only Product');
    }

    public function test_legacy_platform_categories_redirect(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.platform-categories'))
            ->assertRedirect(route('admin.service-categories'));
    }
}
