<?php

use App\Support\PlatformCatalogTrim;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        PlatformCatalogTrim::apply();

        // Delete any platform products whose type is not social_service
        if (Schema::hasTable('platform_products')) {
            $ids = DB::table('platform_products')
                ->where('product_type', '!=', 'social_service')
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                if (Schema::hasTable('domain_quotes')) {
                    DB::table('domain_quotes')->whereIn('platform_product_id', $ids)->delete();
                }
                if (Schema::hasTable('domain_registrations')) {
                    // Registrations link via orders/quotes, not products; clear all leftover domain ops.
                    DB::table('domain_registrations')->delete();
                }
                if (Schema::hasTable('domain_connections')) {
                    DB::table('domain_connections')->delete();
                }
                if (Schema::hasTable('site_integrations')) {
                    DB::table('site_integrations')->whereIn('platform_product_id', $ids)->delete();
                }
                if (Schema::hasTable('user_tools')) {
                    DB::table('user_tools')->whereIn('platform_product_id', $ids)->delete();
                }
                if (Schema::hasTable('platform_product_variants')) {
                    DB::table('platform_product_variants')->whereIn('platform_product_id', $ids)->delete();
                }
                if (Schema::hasTable('platform_product_images')) {
                    DB::table('platform_product_images')->whereIn('platform_product_id', $ids)->delete();
                }
                if (Schema::hasTable('favorites')) {
                    DB::table('favorites')
                        ->where('favoritable_type', 'App\\Models\\PlatformProduct')
                        ->whereIn('favoritable_id', $ids)
                        ->delete();
                }
                DB::table('platform_products')->whereIn('id', $ids)->delete();
            }
        }

        // Keep only social_service product types
        if (Schema::hasTable('product_types')) {
            $keepTypeIds = DB::table('product_types')
                ->where('slug', 'social_service')
                ->pluck('id');

            DB::table('product_types')
                ->where('slug', '!=', 'social_service')
                ->delete();

            // Relink remaining products if needed
            if (Schema::hasColumn('platform_products', 'product_type_id') && $keepTypeIds->isNotEmpty()) {
                DB::table('platform_products')
                    ->where('product_type', 'social_service')
                    ->where(function ($q) use ($keepTypeIds) {
                        $q->whereNull('product_type_id')
                            ->orWhereNotIn('product_type_id', $keepTypeIds);
                    })
                    ->update(['product_type_id' => $keepTypeIds->first()]);
            }
        }

        // Keep only social-media service category
        if (Schema::hasTable('service_categories')) {
            $socialId = DB::table('service_categories')
                ->where(function ($q) {
                    $q->where('slug', 'social-media')
                        ->orWhere('key', 'social');
                })
                ->value('id');

            if ($socialId && Schema::hasColumn('product_types', 'service_category_id')) {
                DB::table('product_types')
                    ->where('slug', 'social_service')
                    ->update(['service_category_id' => $socialId]);
            }

            DB::table('service_categories')
                ->where(function ($q) {
                    $q->where('slug', '!=', 'social-media')
                        ->where(function ($q2) {
                            $q2->whereNull('key')->orWhere('key', '!=', 'social');
                        });
                })
                ->where(function ($q) {
                    $q->where('slug', '!=', 'social-media');
                })
                ->delete();

            // Safer: delete by slug not in social-media
            DB::table('service_categories')
                ->where('slug', '!=', 'social-media')
                ->delete();
        }

        // Legacy platform categories: keep only social-*
        if (Schema::hasTable('platform_categories')) {
            DB::table('platform_categories')
                ->whereNotIn('slug', ['social-growth', 'social-engagement'])
                ->delete();
        }
    }

    public function down(): void
    {
        // Irreversible data trim
    }
};
