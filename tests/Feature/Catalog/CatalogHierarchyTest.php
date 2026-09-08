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

        return ProductType::query()->where('slug', 'social_service')->firstOrFail();
    }

    private function seedYoutubeProduct(string $slug = 'youtube-views-lite', bool $featured = false): PlatformProduct
    {
        $service = $this->seedHierarchy();
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
            'sort_order' => 0,
        ]);

        return $product;
    }

    public function test_backfill_is_idempotent_and_links_products(): void
    {
        $this->forceCreatePlatformProduct([
            'product_type' => PlatformProductType::SocialService,
            'title' => 'Instagram Growth Pack',
            'slug' => 'instagram-growth-pack',
            'status' => PlatformProductStatus::Draft,
            'base_price' => 1000,
        ]);

        Artisan::call('catalog:backfill-hierarchy');
        $first = ServiceCategory::count();
        $services = ProductType::count();

        Artisan::call('catalog:backfill-hierarchy');

        $this->assertSame($first, ServiceCategory::count());
        $this->assertSame($services, ProductType::count());
        $this->assertGreaterThanOrEqual(5, $first);

        $product = PlatformProduct::where('slug', 'instagram-growth-pack')->first();
        $this->assertNotNull($product->product_type_id);
        $this->assertSame('social_service', ProductType::find($product->product_type_id)?->slug);
        $this->assertSame('instagram', ServiceCategory::find($product->service_category_id)?->slug);
        $this->assertSame('manual', $product->provider);
        $this->assertSame('manual', $product->fulfillment_mode);
        $this->assertFalse($product->auto_renew);
    }

    public function test_services_index_uses_platform_categories(): void
    {
        $this->seedYoutubeProduct();

        $this->get(route('services'))
            ->assertOk()
            ->assertSee('YouTube')
            ->assertSee('Available campaign services')
            ->assertSee('YouTube Views Lite');
    }

    public function test_group_page_lists_products_by_category(): void
    {
        $this->seedYoutubeProduct();

        $this->get(route('services.segment', 'youtube'))
            ->assertOk()
            ->assertSee('YouTube')
            ->assertSee('YouTube Views Lite');
    }

    public function test_visibility_uses_direct_service_category(): void
    {
        $product = $this->seedYoutubeProduct();

        $this->assertTrue($product->isVisibleToPublic());

        $product->serviceCategory->forceFill(['is_active' => false])->save();

        $this->assertFalse($product->fresh()->isVisibleToPublic());
    }

    public function test_visibility_dual_reads_product_type_when_category_fk_null(): void
    {
        $product = $this->seedYoutubeProduct();
        $product->forceFill(['service_category_id' => null])->save();

        $this->assertTrue($product->fresh(['productType.serviceCategory'])->isVisibleToPublic());
        $this->assertTrue(
            PlatformProduct::query()->visibleToPublic()->where('id', $product->id)->exists()
        );
    }

    public function test_service_category_rename_and_toggle_smoke(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $category = ServiceCategory::query()->where('key', 'youtube')->firstOrFail();
        $slug = $category->slug;

        $this->actingAs($admin)
            ->put(route('admin.service-categories.update', $category), [
                'name' => 'YouTube Hub Renamed',
                'is_active' => 1,
                'sort_order' => $category->sort_order,
            ])
            ->assertRedirect(route('admin.service-categories'));

        $category->refresh();
        $this->assertSame('YouTube Hub Renamed', $category->name);
        $this->assertSame($slug, $category->slug);

        $this->actingAs($admin)
            ->get(route('admin.service-categories'))
            ->assertOk()
            ->assertSee('YouTube Hub Renamed')
            ->assertDontSee('Add category');
    }

    public function test_product_filters_by_category_and_status(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $youtube = $this->seedYoutubeProduct('youtube-views-lite');
        $facebook = ServiceCategory::query()->where('slug', 'facebook')->firstOrFail();
        $service = ProductType::query()->where('slug', 'social_service')->firstOrFail();

        $this->forceCreatePlatformProduct([
            'product_type_id' => $service->id,
            'service_category_id' => $facebook->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'Facebook Growth Pack',
            'slug' => 'facebook-growth-pack',
            'status' => PlatformProductStatus::Draft,
            'base_price' => 2000,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.platform-products', [
                'category' => $youtube->service_category_id,
                'status' => 'published',
            ]))
            ->assertOk()
            ->assertSee('YouTube Views Lite')
            ->assertDontSee('Facebook Growth Pack');
    }

    public function test_admin_can_assign_product_category(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $product = $this->seedYoutubeProduct();
        $tiktok = ServiceCategory::query()->where('slug', 'tiktok')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), [
                'title' => $product->title,
                'short_description' => $product->short_description,
                'description' => $product->description,
                'service_category_id' => $tiktok->id,
                'status' => PlatformProductStatus::Published->value,
                'sort_order' => 1,
                'variants' => [
                    [
                        'id' => $product->variants()->first()->id,
                        'price' => 3500,
                        'description' => null,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $this->assertSame($tiktok->id, $product->fresh()->service_category_id);
        $this->assertNotNull($product->fresh()->product_type_id);
    }

    public function test_legacy_platform_categories_redirect(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.platform-categories'))
            ->assertRedirect(route('admin.service-categories'));
    }
}
