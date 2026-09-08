<?php

namespace Tests\Feature\Dashboard;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Enums\UserToolStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\User;
use App\Models\UserTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MyToolsShowTest extends TestCase
{
    use RefreshDatabase;

    private function websiteProduct(): PlatformProduct
    {
        \Illuminate\Support\Facades\Artisan::call('catalog:backfill-hierarchy');
        $service = \App\Models\ProductType::query()->where('slug', 'social_service')->firstOrFail();
        $category = \App\Models\ServiceCategory::query()->where('slug', 'youtube')->firstOrFail();

        $product = $this->forceCreatePlatformProduct([
            'title' => 'Online Banking website',
            'slug' => 'online-banking-'.Str::lower(Str::random(5)),
            'product_type' => PlatformProductType::SocialService,
            'product_type_id' => $service->id,
            'service_category_id' => $category->id,
            'status' => PlatformProductStatus::Published,
            'base_price' => 10000,
            'sort_order' => 1,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
        ]);

        PlatformProductVariant::query()->create([
            'platform_product_id' => $product->id,
            'name' => '3 Months',
            'label' => '3 Months',
            'sku' => $product->slug.'-3m',
            'duration_months' => 3,
            'price' => 27000,
            'sort_order' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);

        return $product->fresh('activeVariants');
    }

    public function test_pending_tool_shows_exact_setup_message_and_pending_access_fields(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        $product = $this->websiteProduct();
        $variant = $product->activeVariants->first();

        $order = Order::factory()->platform()->create([
            'user_id' => $user->id,
            'status' => 'paid',
            'total_amount' => 27000,
        ]);

        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'platform_product',
            'item_id' => $product->id,
            'platform_product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 27000,
            'line_total' => 27000,
            'options' => ['product_title' => $product->title],
        ]);

        $tool = UserTool::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'display_name' => $product->title,
            'status' => UserToolStatus::PendingSetup,
            'purchased_at' => now(),
            'duration_months' => 3,
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard.my-tools.show', $tool));

        $response->assertOk()
            ->assertSee('Our team is configuring your service. Your admin login details will appear once done.', false)
            ->assertDontSee('Setup in progress')
            ->assertSee('₦27,000.00')
            ->assertSee('Site URL')
            ->assertSee('Admin login email')
            ->assertSee('Password')
            ->assertSee('Pending');
    }

    public function test_active_tool_shows_configured_access_details(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        $product = $this->websiteProduct();

        $tool = UserTool::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'display_name' => $product->title,
            'status' => UserToolStatus::Active,
            'site_url' => 'https://bank.example.test',
            'admin_email' => 'admin@bank.example.test',
            'admin_password' => 'secret-pass',
            'purchased_at' => now()->subDay(),
            'expires_at' => now()->addMonths(3),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.my-tools.show', $tool))
            ->assertOk()
            ->assertSee('https://bank.example.test')
            ->assertSee('admin@bank.example.test')
            ->assertSee('Copy password')
            ->assertDontSee('Our team is configuring your service')
            ->assertDontSee('secret-pass');
    }

    public function test_user_cannot_view_another_users_tool(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $owner->assignRole('user');
        $intruder = User::factory()->create(['email_verified_at' => now()]);
        $intruder->assignRole('user');
        $product = $this->websiteProduct();

        $tool = UserTool::query()->create([
            'user_id' => $owner->id,
            'platform_product_id' => $product->id,
            'display_name' => $product->title,
            'status' => UserToolStatus::Active,
            'purchased_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->get(route('dashboard.my-tools.show', $tool))
            ->assertNotFound();
    }

    public function test_copy_password_returns_password_for_owner(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        $product = $this->websiteProduct();

        $tool = UserTool::query()->create([
            'user_id' => $user->id,
            'platform_product_id' => $product->id,
            'display_name' => $product->title,
            'status' => UserToolStatus::Active,
            'admin_password' => 'MySecretPass99',
            'purchased_at' => now(),
            'expires_at' => now()->addMonths(3),
        ]);

        $this->actingAs($user)
            ->postJson(route('dashboard.my-tools.password', $tool))
            ->assertOk()
            ->assertJson(['password' => 'MySecretPass99']);
    }
}
