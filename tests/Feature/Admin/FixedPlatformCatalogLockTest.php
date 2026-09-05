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

        $vpn = ProductType::query()->where('slug', 'vpn')->firstOrFail();
        $product = $this->forceCreatePlatformProduct([
            'product_type_id' => $vpn->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'Residential VPN',
            'slug' => 'residential-vpn-lock-test',
            'short_description' => 'Test VPN',
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
            'name' => '1 Month',
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

    public function test_categories_receive_permanent_keys_from_backfill(): void
    {
        $this->seedCatalog();

        $this->assertDatabaseHas('service_categories', [
            'id' => 1,
            'slug' => 'network-services',
            'key' => 'network',
        ]);
        $this->assertDatabaseHas('service_categories', [
            'id' => 4,
            'slug' => 'website-services',
            'key' => 'website',
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

        $category = ServiceCategory::query()->where('key', 'network')->firstOrFail();
        $this->actingAs($admin)
            ->delete('/admin/service-categories/'.$category->id)
            ->assertNotFound();

        $this->assertDatabaseHas('service_categories', ['id' => $category->id]);
    }

    public function test_admin_can_rename_and_toggle_category(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $category = ServiceCategory::query()->where('key', 'network')->firstOrFail();
        $slug = $category->slug;

        $this->actingAs($admin)
            ->put(route('admin.service-categories.update', $category), [
                'name' => 'Network Hub',
                'is_active' => '1',
                'sort_order' => $category->sort_order,
            ])
            ->assertRedirect(route('admin.service-categories'));

        $category->refresh();
        $this->assertSame('Network Hub', $category->name);
        $this->assertSame($slug, $category->slug);
        $this->assertSame('network', $category->key);

        $this->actingAs($admin)
            ->post(route('admin.service-categories.toggle', $category))
            ->assertRedirect();

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_admin_can_rename_and_toggle_service(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $service = ProductType::query()->where('slug', 'vpn')->firstOrFail();
        $categoryId = $service->service_category_id;

        $this->actingAs($admin)
            ->put(route('admin.services.update', $service), [
                'name' => 'VPN Renamed',
                'is_active' => '1',
                'sort_order' => $service->sort_order,
                'slug' => 'hacked-slug',
                'service_category_id' => 999,
            ])
            ->assertRedirect(route('admin.services'));

        $service->refresh();
        $this->assertSame('VPN Renamed', $service->name);
        $this->assertSame('vpn', $service->slug);
        $this->assertSame($categoryId, $service->service_category_id);

        $this->actingAs($admin)
            ->post(route('admin.services.toggle', $service))
            ->assertRedirect();

        $this->assertFalse($service->fresh()->is_active);
    }

    public function test_admin_cannot_create_or_delete_service_or_product(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $service = ProductType::query()->where('slug', 'vpn')->firstOrFail();
        $product = PlatformProduct::query()->where('slug', 'residential-vpn-lock-test')->firstOrFail();

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
        $product = PlatformProduct::query()->where('slug', 'residential-vpn-lock-test')->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), [
                'title' => 'Residential VPN Plus',
                'short_description' => 'Updated short',
                'description' => 'Updated long',
                'status' => 'published',
                'sort_order' => $product->sort_order,
                'variants' => [
                    ['id' => $variant->id, 'price' => 5500, 'description' => 'Best for home use'],
                ],
            ])
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $product->refresh();
        $this->assertSame('Residential VPN Plus', $product->title);
        $this->assertEquals(5500.0, (float) $product->base_price);
        $this->assertEquals(5500.0, (float) $variant->fresh()->price);
        $this->assertSame('Best for home use', $variant->fresh()->description);
        $this->assertSame('residential-vpn-lock-test', $product->slug);
    }

    public function test_admin_can_toggle_product_featured_and_deactivate(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $product = PlatformProduct::query()->where('slug', 'residential-vpn-lock-test')->firstOrFail();
        $product->update(['is_featured' => true]);
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), [
                'title' => $product->title,
                'short_description' => $product->short_description,
                'description' => $product->description,
                'status' => 'published',
                'sort_order' => $product->sort_order,
                // is_featured omitted = unchecked on edit form
                'variants' => [
                    ['id' => $variant->id, 'price' => $variant->price],
                ],
            ])
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $this->assertFalse($product->fresh()->is_featured);

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product->fresh()), [
                'title' => $product->title,
                'short_description' => $product->short_description,
                'description' => $product->description,
                'status' => 'published',
                'sort_order' => $product->fresh()->sort_order,
                'is_featured' => '1',
                'variants' => [
                    ['id' => $variant->id, 'price' => $variant->price],
                ],
            ])
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

    public function test_admin_cannot_change_provider_or_reparent_product(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $product = PlatformProduct::query()->where('slug', 'residential-vpn-lock-test')->firstOrFail();
        $email = ProductType::query()->where('slug', 'email')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $variantName = $variant->name;

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), [
                'title' => $product->title,
                'short_description' => $product->short_description,
                'description' => $product->description,
                'status' => 'published',
                'sort_order' => $product->sort_order,
                'provider' => 'hacked-provider',
                'provider_sku' => 'HACK',
                'fulfillment_mode' => 'api',
                'auto_renew' => '1',
                'product_type_id' => $email->id,
                'slug' => 'hacked-slug',
                'variants' => [
                    ['id' => $variant->id, 'price' => 5000, 'name' => 'Hacked Name'],
                ],
            ])
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $product->refresh();
        $this->assertSame('manual', $product->provider);
        $this->assertNull($product->provider_sku);
        $this->assertSame('manual', $product->fulfillment_mode);
        $this->assertFalse((bool) $product->auto_renew);
        $this->assertSame('residential-vpn-lock-test', $product->slug);
        $this->assertNotSame($email->id, $product->product_type_id);
        $this->assertSame($variantName, $variant->fresh()->name);
    }

    public function test_unknown_variant_id_is_rejected(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $product = PlatformProduct::query()->where('slug', 'residential-vpn-lock-test')->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.platform-products.edit', $product))
            ->put(route('admin.platform-products.update', $product), [
                'title' => $product->title,
                'status' => 'published',
                'sort_order' => $product->sort_order,
                'variants' => [
                    ['id' => $variant->id, 'price' => 5000],
                    ['id' => 999999, 'price' => 100],
                ],
            ])
            ->assertSessionHasErrors('variants');

        $this->assertSame(1, $product->variants()->count());
    }

    public function test_inactive_category_hides_products_from_public(): void
    {
        $this->seedCatalog();
        $category = ServiceCategory::query()->where('key', 'network')->firstOrFail();
        $product = PlatformProduct::query()->where('slug', 'residential-vpn-lock-test')->firstOrFail();

        $this->get(route('services.show', [
            'type' => 'vpn',
            'productSlug' => $product->slug,
        ]))->assertOk();

        $category->update(['is_active' => false]);

        $this->get(route('services.segment', 'network-services'))->assertNotFound();
        $this->get(route('services.show', [
            'type' => 'vpn',
            'productSlug' => $product->slug,
        ]))->assertNotFound();
    }

    public function test_inactive_service_hides_products_while_category_stays_active(): void
    {
        $this->seedCatalog();
        $service = ProductType::query()->where('slug', 'vpn')->firstOrFail();
        $product = PlatformProduct::query()->where('slug', 'residential-vpn-lock-test')->firstOrFail();

        $service->update(['is_active' => false]);

        $this->get(route('services.segment', 'network-services'))->assertOk();
        $this->get(route('services.type', [
            'category' => 'network-services',
            'service' => 'vpn',
        ]))->assertNotFound();
        $this->get(route('services.show', [
            'type' => 'vpn',
            'productSlug' => $product->slug,
        ]))->assertNotFound();
    }

    public function test_draft_product_hidden_from_public_but_listed_in_admin(): void
    {
        $this->seedCatalog();
        $admin = $this->admin();
        $product = PlatformProduct::query()->where('slug', 'residential-vpn-lock-test')->firstOrFail();

        $product->update(['status' => PlatformProductStatus::Draft]);

        $this->get(route('services.show', [
            'type' => 'vpn',
            'productSlug' => $product->slug,
        ]))->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.platform-products', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('Residential VPN');
    }

    public function test_category_reactivation_does_not_reactivate_inactive_children(): void
    {
        $this->seedCatalog();
        $category = ServiceCategory::query()->where('key', 'network')->firstOrFail();
        $service = ProductType::query()->where('slug', 'vpn')->firstOrFail();
        $product = PlatformProduct::query()->where('slug', 'residential-vpn-lock-test')->firstOrFail();

        $service->update(['is_active' => false]);
        $product->update(['status' => PlatformProductStatus::Draft]);
        $category->update(['is_active' => false]);

        $category->update(['is_active' => true]);

        $this->assertFalse($service->fresh()->is_active);
        $this->assertSame(PlatformProductStatus::Draft, $product->fresh()->status);

        $this->get(route('services.show', [
            'type' => 'vpn',
            'productSlug' => $product->slug,
        ]))->assertNotFound();

        $service->update(['is_active' => true]);
        $this->get(route('services.show', [
            'type' => 'vpn',
            'productSlug' => $product->slug,
        ]))->assertNotFound();

        $product->update(['status' => PlatformProductStatus::Published]);
        $this->get(route('services.show', [
            'type' => 'vpn',
            'productSlug' => $product->slug,
        ]))->assertOk();
    }

    public function test_backfill_preserves_category_and_service_cms_names(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $category = ServiceCategory::query()->where('key', 'network')->firstOrFail();
        $service = ProductType::query()->where('slug', 'vpn')->firstOrFail();

        $category->update(['name' => 'Custom Network Label']);
        $service->update(['name' => 'Custom VPN Label']);

        Artisan::call('catalog:backfill-hierarchy');

        $this->assertSame('Custom Network Label', $category->fresh()->name);
        $this->assertSame('Custom VPN Label', $service->fresh()->name);
        $this->assertSame('network', $category->fresh()->key);
        $this->assertSame('vpn', $service->fresh()->slug);
    }

    public function test_key_migration_aborts_on_slug_mismatch(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        DB::table('service_categories')->where('id', 1)->update(['slug' => 'tampered-slug']);

        $migration = require database_path('migrations/2026_08_29_000100_add_key_to_service_categories.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('preflight failed');
        $migration->up();
    }

    public function test_trust_escrow_redirects_to_services_when_active(): void
    {
        Artisan::call('catalog:backfill-hierarchy');

        $this->get(route('services.segment', 'trust-escrow'))
            ->assertRedirect(route('services'));
    }

    public function test_mass_assignment_ignores_locked_identity_fields(): void
    {
        $this->seedCatalog();
        $product = PlatformProduct::query()->where('slug', 'residential-vpn-lock-test')->firstOrFail();
        $email = ProductType::query()->where('slug', 'email')->firstOrFail();

        $product->update([
            'title' => 'Still VPN',
            'slug' => 'should-not-change',
            'product_type_id' => $email->id,
            'provider' => 'evil',
        ]);

        $product->refresh();
        $this->assertSame('Still VPN', $product->title);
        $this->assertSame('residential-vpn-lock-test', $product->slug);
        $this->assertNotSame($email->id, $product->product_type_id);
        $this->assertSame('manual', $product->provider);
    }

    public function test_service_sort_shift_and_rejects_out_of_range(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $admin = $this->admin();

        $vpn = ProductType::query()->where('slug', 'vpn')->firstOrFail();
        $proxy = ProductType::query()->where('slug', 'proxy')->firstOrFail();

        \App\Support\SortOrder::normalize(
            ProductType::query()->whereHas('serviceCategory', fn ($q) => $q->system())
        );
        $vpn->refresh();
        $proxy->refresh();

        $vpnOrder = (int) $vpn->sort_order;
        $proxyOrder = (int) $proxy->sort_order;
        $this->assertNotSame($vpnOrder, $proxyOrder);

        $siblingMax = ProductType::query()
            ->whereHas('serviceCategory', fn ($q) => $q->system())
            ->count();

        $orders = ProductType::query()
            ->whereHas('serviceCategory', fn ($q) => $q->system())
            ->pluck('sort_order')
            ->map(fn ($v) => (int) $v)
            ->sort()
            ->values()
            ->all();
        $this->assertSame(range(1, $siblingMax), $orders);

        $this->actingAs($admin)
            ->put(route('admin.services.update', $vpn), [
                'name' => $vpn->name,
                'is_active' => '1',
                'sort_order' => $proxyOrder,
            ])
            ->assertRedirect(route('admin.services'));

        $this->assertSame($proxyOrder, (int) $vpn->fresh()->sort_order);
        $this->assertSame($vpnOrder, (int) $proxy->fresh()->sort_order);

        $this->actingAs($admin)
            ->from(route('admin.services.edit', $vpn))
            ->put(route('admin.services.update', $vpn), [
                'name' => $vpn->name,
                'is_active' => '1',
                'sort_order' => $siblingMax + 5,
            ])
            ->assertSessionHasErrors('sort_order');
    }

    public function test_product_sort_is_globally_unique_after_normalize(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $vpn = ProductType::query()->where('slug', 'vpn')->firstOrFail();
        $email = ProductType::query()->where('slug', 'email')->firstOrFail();

        $this->forceCreatePlatformProduct([
            'product_type_id' => $vpn->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'VPN A',
            'slug' => 'vpn-a-global-sort',
            'status' => PlatformProductStatus::Published,
            'base_price' => 1000,
            'sort_order' => 0,
        ]);
        $this->forceCreatePlatformProduct([
            'product_type_id' => $email->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'Email A',
            'slug' => 'email-a-global-sort',
            'status' => PlatformProductStatus::Published,
            'base_price' => 1000,
            'sort_order' => 0,
        ]);

        Artisan::call('catalog:backfill-hierarchy');

        $orders = PlatformProduct::query()
            ->whereHas('productType.serviceCategory', fn ($q) => $q->system())
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
        $admin = $this->admin();

        \App\Support\SortOrder::normalize(ServiceCategory::query()->system());

        $website = ServiceCategory::query()->where('key', 'website')->firstOrFail();
        $max = ServiceCategory::query()->system()->count();

        $this->actingAs($admin)
            ->put(route('admin.service-categories.update', $website), [
                'name' => $website->name,
                'is_active' => '1',
                'sort_order' => $max,
            ])
            ->assertRedirect(route('admin.service-categories'));

        $ordered = ServiceCategory::query()->system()->orderBy('sort_order')->pluck('key')->all();
        $this->assertSame('website', end($ordered));

        $response = $this->get(route('services'));
        $response->assertOk();
        $html = $response->getContent();
        $posNetwork = strpos($html, 'Network Services');
        $posWebsite = strpos($html, 'Website Services');
        $this->assertNotFalse($posNetwork);
        $this->assertNotFalse($posWebsite);
        $this->assertLessThan($posWebsite, $posNetwork);
    }
}