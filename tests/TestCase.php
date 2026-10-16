<?php

namespace Tests;

use App\Models\PlatformProduct;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('roles')) {
            $this->seed(RoleSeeder::class);
        }

        if (Schema::hasTable('permissions')) {
            $this->seed(PermissionSeeder::class);
        }

        if (Schema::hasTable('integration_providers')) {
            $this->seed(\Database\Seeders\CommunicationsSeeder::class);
        }

        // SQLite rebuilds of the transactions table still reference the dropped escrows table.
        if (Schema::hasTable('transactions') && ! Schema::hasTable('escrows')) {
            Schema::create('escrows', fn (\Illuminate\Database\Schema\Blueprint $table) => $table->id());
        }
    }

    /** @param  array<string, mixed>  $attrs */
    protected function forceCreateServiceCategory(array $attrs): ServiceCategory
    {
        $category = new ServiceCategory;
        $category->forceFill($attrs)->save();

        return $category->fresh();
    }

    /** @param  array<string, mixed>  $attrs */
    protected function forceCreateProductType(array $attrs): ProductType
    {
        $service = new ProductType;
        $service->forceFill($attrs)->save();

        return $service->fresh();
    }

    /** @param  array<string, mixed>  $attrs */
    protected function forceCreatePlatformProduct(array $attrs): PlatformProduct
    {
        if (empty($attrs['service_category_id']) && ! empty($attrs['slug']) && Schema::hasColumn('platform_products', 'service_category_id')) {
            foreach (config('platform_categories', []) as $meta) {
                if (! is_array($meta)) {
                    continue;
                }
                $products = $meta['products'] ?? [];
                if (is_array($products) && in_array($attrs['slug'], $products, true)) {
                    $categoryId = ServiceCategory::query()->where('slug', $meta['slug'] ?? '')->value('id');
                    if ($categoryId) {
                        $attrs['service_category_id'] = $categoryId;
                    }
                    break;
                }
            }
        }

        if (empty($attrs['service_category_id']) && ! empty($attrs['product_type_id']) && Schema::hasColumn('platform_products', 'service_category_id')) {
            $fromType = ProductType::query()->whereKey($attrs['product_type_id'])->value('service_category_id');
            if ($fromType) {
                $attrs['service_category_id'] = $fromType;
            }
        }

        // Data migrations already insert the fixed catalog, so reuse a row with the same slug.
        $product = (! empty($attrs['slug']) ? PlatformProduct::query()->where('slug', $attrs['slug'])->first() : null)
            ?? new PlatformProduct;
        $product->forceFill($attrs)->save();

        return $product->fresh();
    }

    /** Agents need a verified YouTube channel before they can start YouTube tasks. */
    protected function linkVerifiedYoutubeChannel(\App\Models\User $agent, ?string $channelId = null): \App\Models\AgentYoutubeIdentity
    {
        $channelId ??= 'UC'.str_pad(substr(md5('agent-'.$agent->id), 0, 22), 22, 'x');

        $identity = new \App\Models\AgentYoutubeIdentity;
        $identity->forceFill([
            'user_id' => $agent->id,
            'channel_id' => $channelId,
            'active_channel_id' => $channelId,
            'channel_url' => 'https://www.youtube.com/channel/'.$channelId,
            'title' => 'Agent channel '.$agent->id,
            'method' => \App\Models\AgentYoutubeIdentity::METHOD_ADMIN,
            'status' => \App\Models\AgentYoutubeIdentity::STATUS_VERIFIED,
            'verified_at' => now(),
        ])->save();

        return $identity->fresh();
    }
}
