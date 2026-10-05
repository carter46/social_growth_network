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
            'title' => 'YouTube Promo Views',
            'slug' => 'youtube-promo-views',
            'description' => 'Views',
            'status' => PlatformProductStatus::Published,
            'base_price' => 8000,
            'sort_order' => 1,
            'provider' => 'manual',
            'fulfillment_mode' => 'manual',
            'auto_renew' => false,
            'is_campaign' => true,
            'agent_reward_percent' => 50,
            'estimated_minutes' => 3,
        ]);

        PlatformProductVariant::query()->create([
            'platform_product_id' => $product->id,
            'name' => 'Standard',
            'label' => 'Standard',
            'price' => 8000,
            'pricing_mode' => PlatformProductVariant::PRICING_PER_UNIT,
            'pricing_units' => 1000,
            'unit_price' => 8,
            'min_units' => 1000,
            'max_units' => 100000,
            'duration_months' => null,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
            'sku' => 'yt-promo-views-std',
        ]);

        return $product->fresh(['variants']);
    }

    public function test_unit_price_and_agent_reward_math(): void
    {
        $this->assertSame('8.0000', PlatformProductVariant::computeUnitPriceFromPackagePrice(8000, 1000));
        $this->assertSame('16.0000', PlatformProductVariant::computeUnitPriceFromPackagePrice(8000, 500));
        $this->assertSame('12000.00', bcmul('8.0000', '1500', 2));
        $this->assertSame('400000.00', bcmul('8.0000', '50000', 2));
        $this->assertSame('4.00', PlatformProductVariant::agentRewardForUnitPrice('8.0000', 50));
        $this->assertSame('5.00', PlatformProductVariant::agentRewardForUnitPrice('20.0000', 25));
    }

    public function test_pricing_label(): void
    {
        $variant = $this->seedProduct()->pricingVariant();

        $this->assertSame('₦8,000 per 1,000 views', $variant->pricingLabel());
    }

    public function test_admin_saves_single_pricing_and_reward_percent(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->seedProduct();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), [
                'title' => $product->title,
                'description' => $product->description,
                'status' => 'published',
                'estimated_minutes' => 3,
                'agent_reward_percent' => 25,
                'pricing' => [
                    'min_units' => 500,
                    'max_units' => 50000,
                    'pricing_units' => 500,
                    'price' => 8000,
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.platform-products.edit', $product));

        $product->refresh();
        $variant = $product->pricingVariant();
        $this->assertTrue($variant->isPerUnit());
        $this->assertSame(500, (int) $variant->pricing_units);
        $this->assertSame('16.0000', $variant->billingUnitPrice());
        $this->assertSame(500, (int) $variant->min_units);
        $this->assertSame(50000, (int) $variant->max_units);
        $this->assertEquals(25.0, (float) $product->agent_reward_percent);
        $this->assertEquals(8000.0, (float) $product->base_price);
        $this->assertSame(1, $product->activeVariants()->count());
    }

    public function test_admin_rejects_max_below_min(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->seedProduct();

        $this->actingAs($admin)
            ->from(route('admin.platform-products.edit', $product))
            ->put(route('admin.platform-products.update', $product), [
                'title' => $product->title,
                'status' => 'published',
                'estimated_minutes' => 3,
                'agent_reward_percent' => 50,
                'pricing' => [
                    'min_units' => 1000,
                    'max_units' => 500,
                    'pricing_units' => 1000,
                    'price' => 8000,
                ],
            ])
            ->assertSessionHasErrors('pricing.max_units');
    }

    public function test_checkout_rejects_units_below_minimum(): void
    {
        $creator = User::factory()->creator()->create();
        $product = $this->seedProduct();

        $this->actingAs($creator)
            ->get(route('dashboard.services.checkout', [
                'slug' => $product->slug,
                'units' => 50,
            ]))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_fulfillment_sets_quantity_and_percentage_reward(): void
    {
        $creator = User::factory()->creator()->create();
        $product = $this->seedProduct();
        $variant = $product->pricingVariant();

        $order = Order::query()->create([
            'source' => 'platform',
            'user_id' => $creator->id,
            'reference' => 'PLT-TEST1',
            'amount' => 12000,
            'total_amount' => 12000,
            'status' => 'paid',
            'payment_method' => 'wallet',
        ]);

        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'platform_product',
            'item_id' => $product->id,
            'quantity' => 1500,
            'unit_price' => 8,
            'line_total' => 12000,
            'platform_product_variant_id' => $variant->id,
            'options' => [
                'target_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'pricing_mode' => 'per_unit',
                'engagement_quantity' => 1500,
            ],
        ]);

        app(CampaignFulfillmentService::class)->createFromPaidOrder($order->fresh('items'));

        $campaign = Campaign::query()->where('order_item_id', $item->id)->firstOrFail();
        $this->assertSame(1500, (int) $campaign->quantity);
        $this->assertEquals(4.0, (float) $campaign->locked_agent_reward);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $campaign->target_url);
    }

    public function test_purchase_rejects_quantity_outside_range(): void
    {
        $creator = User::factory()->creator()->create(['email_verified_at' => now()]);
        $product = $this->seedProduct();

        \App\Models\Wallet::factory()->create([
            'user_id' => $creator->id,
            'balance' => 100000,
            'locked_balance' => 0,
        ]);

        $this->actingAs($creator)
            ->post(route('dashboard.services.purchase', $product->slug), [
                'quantity' => 999,
                'target_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
                'payment_method' => 'wallet',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
