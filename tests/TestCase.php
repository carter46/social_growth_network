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

        $product = new PlatformProduct;
        $product->forceFill($attrs)->save();

        return $product->fresh();
    }

    /** @return array<string, string> */
    protected function sampleDomainRegistrant(): array
    {
        return [
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'email' => 'jane.buyer@example.com',
            'phone' => '+234.8012345678',
            'address' => '12 Test Street',
            'city' => 'Lagos',
            'state' => 'LA',
            'zip' => '100001',
            'country' => 'NG',
        ];
    }
}
