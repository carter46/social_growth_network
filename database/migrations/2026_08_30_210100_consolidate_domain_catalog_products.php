<?php

use App\Enums\PlatformProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_products')) {
            return;
        }

        $retireSlugs = ['com-domain-registration', 'io-domain-registration', 'co-domain-registration'];

        DB::table('platform_products')
            ->where('product_type', 'domain')
            ->whereIn('slug', $retireSlugs)
            ->update(['status' => PlatformProductStatus::Draft->value]);

        $existing = DB::table('platform_products')
            ->where('slug', 'domain-registration')
            ->first();

        if ($existing) {
            DB::table('platform_products')
                ->where('id', $existing->id)
                ->update([
                    'title' => 'Domain Registration',
                    'status' => PlatformProductStatus::Published->value,
                    'updated_at' => now(),
                ]);

            return;
        }

        $template = DB::table('platform_products')
            ->where('product_type', 'domain')
            ->orderBy('id')
            ->first();

        if (! $template) {
            return;
        }

        DB::table('platform_products')->insert([
            'title' => 'Domain Registration',
            'slug' => 'domain-registration',
            'product_type' => 'domain',
            'product_type_id' => $template->product_type_id,
            'platform_category_id' => $template->platform_category_id ?? null,
            'short_description' => 'Search and register your domain name.',
            'description' => 'Search availability, get a live price, and register your domain.',
            'status' => PlatformProductStatus::Published->value,
            'is_featured' => false,
            'sort_order' => 0,
            'base_price' => 0,
            'provider' => 'domain_provider',
            'fulfillment_mode' => 'manual',
            'meta' => json_encode([
                'domain_markup_percent' => 15,
                'domain_fx_policy' => ['usd_ngn_rate' => 1600],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::getPdo()->lastInsertId();

        if ($productId > 0 && Schema::hasTable('platform_product_variants')) {
            DB::table('platform_product_variants')->insert([
                'platform_product_id' => $productId,
                'name' => 'Standard',
                'slug' => 'domain-registration-std',
                'price' => 0,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive: do not delete consolidated product or restore deleted rows.
    }
};
