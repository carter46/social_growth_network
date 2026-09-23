<?php

namespace Tests\Feature;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Campaigns\CampaignFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class VariantUnitPricingTest extends TestCase
{
    use RefreshDatabase;

    private function seedProduct(): PlatformProduct
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
            'description' => 'Views pack',
            'status' => PlatformProductStatus::Published,
            'base_price' => 20000,
            'sort_order' => 1,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
            'auto_renew' => false,
            'is_campaign' => true,
            'agent_reward_per_completion' => 5,
            'estimated_minutes' => 3,
        ]);

        PlatformProductVariant::query()->create([
            'platform_product_id' => $product->id,
            'name' => '1,000 Views',
            'label' => '1,000 Views',
            'price' => 20000,
            'pricing_mode' => PlatformProductVariant::PRICING_FIXED,
            'included_units' => 1000,
            'duration_months' => null,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
            'sku' => 'yt-views-std',
        ]);

        return $product->fresh(['variants']);
    }

    public function test_compute_unit_price_uses_reference_thousand(): void
    {
        $this->assertSame('20.0000', PlatformProductVariant::computeUnitPriceFromPackagePrice(20000));
        $this->assertSame(1000, PlatformProductVariant::REFERENCE_UNITS);
    }

    public function test_admin_per_unit_computes_unit_price_from_reference_thousand(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->seedProduct();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), [
                'title' => $product->title,
                'description' => $product->description,
                'status' => 'published',
                'is_campaign' => '1',
                'agent_reward_per_completion' => 5,
                'estimated_minutes' => 3,
                'variants' => [
                    [
                        'id' => $variant->id,
                        'name' => '1,000 Views',
                        'price' => 20000,
                        'description' => 'Reference pack',
                        'per_unit' => '1',
                        'min_units' => 200,
                        'max_units' => 100000,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $variant->refresh();
        $this->assertTrue($variant->isPerUnit());
        $this->assertSame('20.0000', $variant->billingUnitPrice());
        $this->assertSame(200, (int) $variant->min_units);
    }

    public function test_admin_rejects_per_unit_when_rate_below_agent_reward(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->seedProduct();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.platform-products.edit', $product))
            ->put(route('admin.platform-products.update', $product), [
                'title' => $product->title,
                'description' => $product->description,
                'status' => 'published',
                'is_campaign' => '1',
                'agent_reward_per_completion' => 25,
                'estimated_minutes' => 3,
                'variants' => [
                    [
                        'id' => $variant->id,
                        'name' => '1,000 Views',
                        'price' => 20000,
                        'per_unit' => '1',
                        'min_units' => 200,
                    ],
                ],
            ])
            ->assertSessionHasErrors('variants');
    }

    public function test_checkout_rejects_per_unit_without_valid_units_query(): void
    {
        $creator = User::factory()->creator()->create();
        $product = $this->seedProduct();
        $variant = $product->variants()->firstOrFail();
        $variant->update([
            'pricing_mode' => PlatformProductVariant::PRICING_PER_UNIT,
            'unit_price' => 20,
            'min_units' => 200,
            'max_units' => 100000,
            'price' => 20000,
        ]);

        $this->actingAs($creator)
            ->get(route('dashboard.services.checkout', [
                'slug' => $product->slug,
                'variant' => $variant->id,
                'units' => 50,
            ]))
            ->assertRedirect(route('dashboard.services.product', $product->slug));
    }

    public function test_per_unit_fulfillment_sets_campaign_quantity_from_engagement_quantity(): void
    {
        $creator = User::factory()->creator()->create();
        $product = $this->seedProduct();
        $variant = $product->variants()->firstOrFail();
        $variant->update([
            'pricing_mode' => PlatformProductVariant::PRICING_PER_UNIT,
            'unit_price' => 20,
            'min_units' => 200,
            'max_units' => 100000,
            'price' => 20000,
            'included_units' => null,
        ]);

        $order = Order::query()->create([
            'source' => 'platform',
            'user_id' => $creator->id,
            'reference' => 'PLT-TEST1',
            'amount' => 4000,
            'total_amount' => 4000,
            'status' => 'paid',
            'payment_method' => 'wallet',
        ]);

        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'platform_product',
            'item_id' => $product->id,
            'quantity' => 200,
            'unit_price' => 20,
            'line_total' => 4000,
            'platform_product_variant_id' => $variant->id,
            'options' => [
                'target_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'pricing_mode' => 'per_unit',
                'engagement_quantity' => 200,
            ],
        ]);

        app(CampaignFulfillmentService::class)->createFromPaidOrder($order->fresh('items'));

        $campaign = Campaign::query()->where('order_item_id', $item->id)->firstOrFail();
        $this->assertSame(200, (int) $campaign->quantity);
        $this->assertEquals(20.0, (float) $campaign->locked_creator_price);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $campaign->target_url);
    }

    public function test_fixed_fulfillment_uses_included_units_via_engagement_quantity(): void
    {
        $creator = User::factory()->creator()->create();
        $product = $this->seedProduct();
        $variant = $product->variants()->firstOrFail();

        $order = Order::query()->create([
            'source' => 'platform',
            'user_id' => $creator->id,
            'reference' => 'PLT-TEST2',
            'amount' => 20000,
            'total_amount' => 20000,
            'status' => 'paid',
            'payment_method' => 'wallet',
        ]);

        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'platform_product',
            'item_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 20000,
            'line_total' => 20000,
            'platform_product_variant_id' => $variant->id,
            'options' => [
                'target_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'pricing_mode' => 'fixed',
                'engagement_quantity' => 1000,
            ],
        ]);

        app(CampaignFulfillmentService::class)->createFromPaidOrder($order->fresh('items'));

        $campaign = Campaign::query()->where('order_item_id', $item->id)->firstOrFail();
        $this->assertSame(1000, (int) $campaign->quantity);
        $this->assertEquals(20000.0, (float) $campaign->locked_creator_price);
    }

    public function test_fixed_purchase_rejects_quantity_greater_than_one(): void
    {
        $creator = User::factory()->creator()->create(['email_verified_at' => now()]);
        $product = $this->seedProduct();
        $variant = $product->variants()->firstOrFail();

        \App\Models\Wallet::factory()->create([
            'user_id' => $creator->id,
            'balance' => 100000,
            'locked_balance' => 0,
        ]);

        $this->actingAs($creator)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'variant_id' => $variant->id,
                'quantity' => 2,
                'target_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
                'payment_method' => 'wallet',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_prepare_main_line_rejects_fixed_campaign_missing_included_units(): void
    {
        $creator = User::factory()->creator()->create(['email_verified_at' => now()]);
        \App\Models\Wallet::factory()->create([
            'user_id' => $creator->id,
            'balance' => 100000,
            'locked_balance' => 0,
        ]);
        $product = $this->seedProduct();
        $variant = $product->variants()->firstOrFail();
        $variant->update(['included_units' => null, 'pricing_mode' => PlatformProductVariant::PRICING_FIXED]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('included units');

        app(\App\Modules\Catalog\Services\PlatformCheckoutService::class)->purchase($creator, $product, [
            'variant_id' => $variant->id,
            'quantity' => 1,
            'target_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'payment_method' => 'wallet',
        ]);
    }
}
