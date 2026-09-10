<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class FixedPlatformCatalogLockTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): void
    {
        Artisan::call('catalog:backfill-hierarchy');

        $service = ProductType::query()->where('slug', 'social_service')->firstOrFail();
        $youtube = ServiceCategory::query()->where('slug', 'youtube')->firstOrFail();
        $product = $this->forceCreatePlatformProduct([
            'product_type_id' => $service->id,
            'service_category_id' => $youtube->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'YouTube Views',
            'slug' => 'youtube-views',
            'short_description' => 'Test YouTube pack',
            'description' => 'Long description',
            'status' => PlatformProductStatus::Published,
            'base_price' => 5000,
            'sort_order' => 1,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
            'auto_renew' => false,
        ]);
        PlatformProductVariant::query()->create([
            'platform_product_id' => $product->id,
            'name' => 'Standard',
            'price' => 5000,
            'duration_months' => 1,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        return $admin;
    }

    /** @return array<string, mixed> */
    private function productUpdatePayload(PlatformProduct $product, array $overrides = []): array
    {
        $variant = $product->variants()->firstOrFail();

        return array_merge([
            'title' => $product->title,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'status' => 'published',
            'variants' => [
                [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'price' => (float) $variant->price,
                    'description' => $variant->description,
                ],
            ],
        ], $overrides);
    }

    public function test_categories_receive_permanent_keys_from_backfill(): void
    {
        $this->seedCatalog();

        $this->assertDatabaseHas('service_categories', [
            'slug' => 'youtube',
            'key' => 'youtube',
        ]);
        $this->assertDatabaseHas('service_categories', [
            'slug' => 'social-media',
            'key' => 'social',
        ]);
        $this->assertSame(6, ServiceCategory::query()->system()->count());
    }

    public function test_admin_cannot_create_or_delete_category(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/service-categories', [
                'name' => 'Fake Category',
                'mode' => 'catalog',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('service_categories', ['name' => 'Fake Category']);

        $category = ServiceCategory::query()->where('key', 'youtube')->firstOrFail();
        $this->actingAs($admin)
            ->delete('/admin/service-categories/'.$category->id)
            ->assertNotFound();

        $this->assertDatabaseHas('service_categories', ['id' => $category->id]);
    }

    public function test_admin_can_rename_and_toggle_category(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $category = ServiceCategory::query()->where('key', 'youtube')->firstOrFail();
        $slug = $category->slug;

        $this->actingAs($admin)
            ->put(route('admin.service-categories.update', $category), [
                'name' => 'YouTube Hub',
                'is_active' => '1',
                'sort_order' => $category->sort_order,
            ])
            ->assertRedirect(route('admin.service-categories'));

        $category->refresh();
        $this->assertSame('YouTube Hub', $category->name);
        $this->assertSame($slug, $category->slug);
        $this->assertSame('youtube', $category->key);

        $this->actingAs($admin)
            ->post(route('admin.service-categories.toggle', $category))
            ->assertRedirect();

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_admin_can_rename_and_toggle_service(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $service = ProductType::query()->where('slug', 'social_service')->firstOrFail();
        $categoryId = $service->service_category_id;

        $this->actingAs($admin)
            ->put(route('admin.services.update', $service), [
                'name' => 'Social Renamed',
                'is_active' => '1',
                'sort_order' => $service->sort_order,
                'slug' => 'hacked-slug',
                'service_category_id' => 999,
            ])
            ->assertRedirect(route('admin.service-categories'));

        $service->refresh();
        $this->assertSame('Social Renamed', $service->name);
        $this->assertSame('social_service', $service->slug);
        $this->assertSame($categoryId, $service->service_category_id);

        $this->actingAs($admin)
            ->post(route('admin.services.toggle', $service))
            ->assertRedirect(route('admin.service-categories'));

        $this->assertFalse($service->fresh()->is_active);
    }

    public function test_admin_cannot_create_or_delete_service_or_product(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $service = ProductType::query()->where('slug', 'social_service')->firstOrFail();
        $product = PlatformProduct::query()->where('slug', 'youtube-views')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/services', [
                'name' => 'Fake Service',
                'service_category_id' => 1,
            ])
            ->assertNotFound();
        $this->assertDatabaseMissing('product_types', ['name' => 'Fake Service']);

        $this->actingAs($admin)
            ->delete('/admin/services/'.$service->id)
            ->assertNotFound();
        $this->assertDatabaseHas('product_types', ['id' => $service->id]);

        $this->actingAs($admin)
            ->post('/admin/platform-products', [
                'title' => 'Fake Product',
            ])
            ->assertNotFound();
        $this->assertDatabaseMissing('platform_products', ['title' => 'Fake Product']);

        $this->actingAs($admin)
            ->delete('/admin/platform-products/'.$product->id)
            ->assertNotFound();
        $this->assertDatabaseHas('platform_products', ['id' => $product->id]);
    }

    public function test_admin_can_update_product_title_price_and_variant_price(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $product = PlatformProduct::query()->where('slug', 'youtube-views')->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->productUpdatePayload($product, [
                'title' => 'YouTube Views Plus',
                'short_description' => 'Updated short',
                'description' => 'Updated long',
                'variants' => [
                    ['id' => $variant->id, 'price' => 5500, 'name' => $variant->name, 'description' => 'Best starter pack'],
                ],
            ]))
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $product->refresh();
        $this->assertSame('YouTube Views Plus', $product->title);
        $this->assertEquals(5500.0, (float) $product->base_price);
        $this->assertEquals(5500.0, (float) $variant->fresh()->price);
        $this->assertSame('Best starter pack', $variant->fresh()->description);
        $this->assertSame('youtube-views', $product->slug);
    }

    public function test_admin_cannot_change_product_category(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $product = PlatformProduct::query()->where('slug', 'youtube-views')->firstOrFail();
        $youtubeId = $product->service_category_id;
        $tiktok = ServiceCategory::query()->where('slug', 'tiktok')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->productUpdatePayload($product, [
                'service_category_id' => $tiktok->id,
            ]))
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $this->assertSame($youtubeId, $product->fresh()->service_category_id);
    }

    public function test_admin_can_toggle_product_featured_and_deactivate(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $product = PlatformProduct::query()->where('slug', 'youtube-views')->firstOrFail();
        $product->update(['is_featured' => true]);

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->productUpdatePayload($product))
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $this->assertFalse($product->fresh()->is_featured);

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product->fresh()), $this->productUpdatePayload($product->fresh(), [
                'is_featured' => '1',
            ]))
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $this->assertTrue($product->fresh()->is_featured);

        $this->actingAs($admin)
            ->post(route('admin.platform-products.toggle', $product))
            ->assertRedirect();

        $this->assertSame(PlatformProductStatus::Draft, $product->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.platform-products.toggle', $product->fresh()))
            ->assertRedirect();

        $this->assertSame(PlatformProductStatus::Published, $product->fresh()->status);
    }

    public function test_admin_can_rename_variant_via_form(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $product = PlatformProduct::query()->where('slug', 'youtube-views')->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->productUpdatePayload($product, [
                'provider' => 'hacked-provider',
                'provider_sku' => 'HACK',
                'fulfillment_mode' => 'api',
                'auto_renew' => '1',
                'product_type_id' => 999,
                'slug' => 'hacked-slug',
                'variants' => [
                    ['id' => $variant->id, 'price' => 5000, 'name' => 'Premium'],
                ],
            ]))
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $product->refresh();
        $this->assertSame('manual', $product->provider);
        $this->assertNull($product->provider_sku);
        $this->assertSame('manual', $product->fulfillment_mode);
        $this->assertFalse((bool) $product->auto_renew);
        $this->assertSame('youtube-views', $product->slug);
        $this->assertSame('Premium', $variant->fresh()->name);
    }

    public function test_admin_can_add_and_remove_variants(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $product = PlatformProduct::query()->where('slug', 'youtube-views')->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->productUpdatePayload($product, [
                'variants' => [
                    ['id' => $variant->id, 'name' => 'Standard', 'price' => 5000],
                    ['name' => 'Plus', 'price' => 8000, 'description' => 'Extra reach'],
                ],
            ]))
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $this->assertSame(2, $product->variants()->count());
        $this->assertDatabaseHas('platform_product_variants', [
            'platform_product_id' => $product->id,
            'name' => 'Plus',
        ]);

        $keep = $product->variants()->where('name', 'Plus')->firstOrFail();
        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->productUpdatePayload($product, [
                'variants' => [
                    ['id' => $keep->id, 'name' => 'Plus', 'price' => 8000],
                ],
            ]))
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $this->assertSame(1, $product->variants()->count());
        $this->assertSame('Plus', $product->variants()->first()->name);
    }

    public function test_unknown_variant_id_is_rejected(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $product = PlatformProduct::query()->where('slug', 'youtube-views')->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.platform-products.edit', $product))
            ->put(route('admin.platform-products.update', $product), $this->productUpdatePayload($product, [
                'variants' => [
                    ['id' => $variant->id, 'name' => $variant->name, 'price' => 5000],
                    ['id' => 999999, 'name' => 'Ghost', 'price' => 100],
                ],
            ]))
            ->assertSessionHasErrors('variants');

        $this->assertSame(1, $product->variants()->count());
    }

    public function test_inactive_category_hides_products_from_public(): void
    {
        $this->seedCatalog();
        $category = ServiceCategory::query()->where('key', 'youtube')->firstOrFail();
        $product = PlatformProduct::query()->where('slug', 'youtube-views')->firstOrFail();

        $this->get(route('services.show', [
            'type' => 'youtube',
            'productSlug' => $product->slug,
        ]))->assertOk();

        $category->update(['is_active' => false]);

        $this->get(route('services.segment', 'youtube'))->assertNotFound();
        $this->get(route('services.show', [
            'type' => 'youtube',
            'productSlug' => $product->slug,
        ]))->assertNotFound();
    }

    public function test_inactive_product_type_does_not_hide_category_owned_product(): void
    {
        $this->seedCatalog();
        $service = ProductType::query()->where('slug', 'social_service')->firstOrFail();
        $product = PlatformProduct::query()->where('slug', 'youtube-views')->firstOrFail();

        $service->update(['is_active' => false]);

        $this->get(route('services.segment', 'youtube'))->assertOk();
        $this->get(route('services.show', [
            'type' => 'youtube',
            'productSlug' => $product->slug,
        ]))->assertOk();
    }

    public function test_draft_product_hidden_from_public_but_listed_in_admin(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $product = PlatformProduct::query()->where('slug', 'youtube-views')->firstOrFail();

        $product->update(['status' => PlatformProductStatus::Draft]);

        $this->get(route('services.show', [
            'type' => 'youtube',
            'productSlug' => $product->slug,
        ]))->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.platform-products', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('YouTube Views');
    }

    public function test_backfill_preserves_category_and_service_cms_names(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $category = ServiceCategory::query()->where('key', 'youtube')->firstOrFail();
        $service = ProductType::query()->where('slug', 'social_service')->firstOrFail();

        $category->update(['name' => 'Custom YouTube Label']);
        $service->update(['name' => 'Custom Social Label']);

        Artisan::call('catalog:backfill-hierarchy');

        $this->assertSame('Custom YouTube Label', $category->fresh()->name);
        $this->assertSame('Custom Social Label', $service->fresh()->name);
        $this->assertSame('youtube', $category->fresh()->key);
        $this->assertSame('social_service', $service->fresh()->slug);
    }

    public function test_key_migration_aborts_on_slug_mismatch(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        DB::table('service_categories')->where('id', 10)->update(['slug' => 'tampered-slug']);

        $migration = require database_path('migrations/2026_08_29_000100_add_key_to_service_categories.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('preflight failed');
        $migration->up();
    }

    public function test_mass_assignment_ignores_locked_identity_fields(): void
    {
        $this->seedCatalog();
        $product = PlatformProduct::query()->where('slug', 'youtube-views')->firstOrFail();
        $typeId = $product->product_type_id;

        $product->update([
            'title' => 'Still YouTube',
            'slug' => 'should-not-change',
            'product_type_id' => 999,
            'provider' => 'evil',
        ]);

        $product->refresh();
        $this->assertSame('Still YouTube', $product->title);
        $this->assertSame('youtube-views', $product->slug);
        $this->assertSame($typeId, $product->product_type_id);
        $this->assertSame('manual', $product->provider);
    }

    public function test_product_sort_is_globally_unique_after_normalize(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $service = ProductType::query()->where('slug', 'social_service')->firstOrFail();
        $youtube = ServiceCategory::query()->where('slug', 'youtube')->firstOrFail();
        $facebook = ServiceCategory::query()->where('slug', 'facebook')->firstOrFail();

        $this->forceCreatePlatformProduct([
            'product_type_id' => $service->id,
            'service_category_id' => $youtube->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'YouTube A',
            'slug' => 'youtube-a-global-sort',
            'status' => PlatformProductStatus::Published,
            'base_price' => 1000,
            'sort_order' => 0,
        ]);
        $this->forceCreatePlatformProduct([
            'product_type_id' => $service->id,
            'service_category_id' => $facebook->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'Facebook A',
            'slug' => 'facebook-a-global-sort',
            'status' => PlatformProductStatus::Published,
            'base_price' => 1000,
            'sort_order' => 0,
        ]);

        Artisan::call('catalog:backfill-hierarchy');

        $orders = PlatformProduct::query()
            ->where(function ($q) {
                $q->whereHas('serviceCategory', fn ($cat) => $cat->system())
                    ->orWhereHas('productType.serviceCategory', fn ($cat) => $cat->system());
            })
            ->pluck('sort_order')
            ->map(fn ($v) => (int) $v)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(range(1, count($orders)), $orders);
        $this->assertSame(count($orders), count(array_unique($orders)));
    }

    public function test_category_sort_shift_updates_public_hub_order(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');
        $admin = $this->admin();

        \App\Support\SortOrder::normalize(ServiceCategory::query()->system());

        $twitter = ServiceCategory::query()->where('key', 'twitter')->firstOrFail();
        $max = ServiceCategory::query()->system()->count();

        $this->actingAs($admin)
            ->put(route('admin.service-categories.update', $twitter), [
                'name' => $twitter->name,
                'is_active' => '1',
                'sort_order' => $max,
            ])
            ->assertRedirect(route('admin.service-categories'));

        $ordered = ServiceCategory::query()->system()->orderBy('sort_order')->pluck('key')->all();
        $this->assertSame('twitter', end($ordered));

        $response = $this->get(route('services'));
        $response->assertOk();
        $html = $response->getContent();
        $posYoutube = strpos($html, 'YouTube');
        $posTwitter = strpos($html, 'Twitter');
        $this->assertNotFalse($posYoutube);
        $this->assertNotFalse($posTwitter);
        $this->assertLessThan($posTwitter, $posYoutube);
    }
}
