<?php

namespace Tests\Feature\Dashboard;

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

class DiscoverHubsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_services_hub(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('dashboard.services'))
            ->assertOk()
            ->assertSee('Campaign packages')
            ->assertDontSee('>Categories<', false);
    }

    public function test_services_hub_lists_products_not_categories(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $service = ProductType::query()->where('slug', 'social_service')->firstOrFail();
        $category = ServiceCategory::query()->where('slug', 'youtube')->firstOrFail();
        $product = $this->forceCreatePlatformProduct([
            'product_type_id' => $service->id,
            'service_category_id' => $category->id,
            'product_type' => PlatformProductType::SocialService,
            'title' => 'YouTube Views Package',
            'slug' => 'youtube-views-package',
            'short_description' => 'Grow your reach',
            'description' => 'A test package',
            'status' => PlatformProductStatus::Published,
            'is_featured' => true,
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
            'sku' => 'youtube-views-package-std',
            'price' => 3500,
            'is_default' => true,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('dashboard.services'))
            ->assertOk()
            ->assertSee('YouTube Views Package')
            ->assertSee(route('dashboard.services.product', $product->slug), false)
            ->assertDontSee(route('dashboard.services.browse', 'youtube'), false);
    }

    public function test_legacy_discover_and_marketplace_urls_redirect_to_services(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->get('/dashboard/discover/marketplace')
            ->assertRedirect('/dashboard/services');

        $this->actingAs($user)
            ->get('/dashboard/marketplace')
            ->assertRedirect('/dashboard/services');

        $this->actingAs($user)
            ->get('/dashboard/discover/services')
            ->assertRedirect('/dashboard/services');
    }
}
