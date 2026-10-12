<?php

namespace Tests\Feature;

use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Services\Catalog\PackageQuoteException;
use App\Services\Catalog\PackageQuoteService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageQuoteTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'youtube-subscribers-watch-comments';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('escrows')) {
            Schema::create('escrows', fn (Blueprint $table) => $table->id());
        }

        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');
    }

    private function combinedProduct(?array $limits = null): PlatformProduct
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $product->forceFill([
            'estimated_minutes' => 3,
            'task_limits' => $limits ?? [
                'watch' => ['min' => 50, 'max' => 500],
                'comment' => ['min' => 0, 'max' => 200],
            ],
        ])->save();

        $product->pricingVariant()->forceFill([
            'pricing_mode' => PlatformProductVariant::PRICING_PER_UNIT,
            'unit_price' => 100,
            'min_units' => 100,
            'max_units' => 1000,
            'is_active' => true,
        ])->save();

        return $product->fresh();
    }

    public function test_package_price_is_unit_price_times_main_minimum(): void
    {
        $layout = app(PackageQuoteService::class)->layout($this->combinedProduct());

        $this->assertSame('10000.00', $layout['package_price']);
        $this->assertSame(['subscribe', 'watch', 'comment'], array_column($layout['rows'], 'task'));
        $this->assertTrue($layout['rows'][0]['primary']);
        $this->assertSame(50, $layout['rows'][1]['min']);
        $this->assertStringContainsString('not YouTube Analytics watch hours', $layout['rows'][1]['hint']);
    }

    public function test_companions_at_their_minimum_cost_nothing_extra(): void
    {
        $quote = app(PackageQuoteService::class)->quote($this->combinedProduct(), 100);

        $this->assertSame(0, $quote['extra_units']);
        $this->assertSame('10000.00', $quote['total']);
        $this->assertSame(['subscribe' => 100, 'watch' => 50], $quote['task_quantities']);
        $this->assertSame('subscribe', $quote['main_task']);
    }

    public function test_units_above_a_companion_minimum_are_billed_at_the_unit_price(): void
    {
        $quote = app(PackageQuoteService::class)->quote($this->combinedProduct(), 120, ['watch' => '80', 'comment' => '30']);

        $this->assertSame(60, $quote['extra_units']);
        $this->assertSame(180, $quote['billable_units']);
        $this->assertSame('18000.00', $quote['total']);
        $this->assertSame(['subscribe' => 120, 'watch' => 80, 'comment' => 30], $quote['task_quantities']);
    }

    public function test_out_of_range_quantities_are_refused_with_the_field_name(): void
    {
        $quotes = app(PackageQuoteService::class);
        $product = $this->combinedProduct();

        foreach ([
            [99, [], 'units'],
            [100, ['watch' => 501], 'qty.watch'],
            [100, ['comment' => '-1'], 'qty.comment'],
            [100, ['like' => 5], 'qty'],
        ] as [$main, $companions, $field]) {
            try {
                $quotes->quote($product, $main, $companions);
                $this->fail('Expected a quote error for '.$field);
            } catch (PackageQuoteException $e) {
                $this->assertSame($field, $e->field);
            }
        }
    }

    public function test_combined_product_without_companion_limits_cannot_be_quoted(): void
    {
        $product = $this->combinedProduct(['watch' => ['min' => 10, 'max' => 20]]);

        $this->expectException(PackageQuoteException::class);
        $this->expectExceptionMessage('not available to buy yet');

        app(PackageQuoteService::class)->quote($product, 100);
    }

    public function test_single_task_product_has_no_companion_rows(): void
    {
        $product = PlatformProduct::query()->where('slug', 'youtube-likes')->firstOrFail();
        $product->pricingVariant()->forceFill([
            'pricing_mode' => PlatformProductVariant::PRICING_PER_UNIT,
            'unit_price' => 5,
            'min_units' => 100,
            'max_units' => 1000,
        ])->save();

        $quote = app(PackageQuoteService::class)->quote($product->fresh(), 150);

        $this->assertSame(['like' => 150], $quote['task_quantities']);
        $this->assertSame('750.00', $quote['total']);
    }
}
