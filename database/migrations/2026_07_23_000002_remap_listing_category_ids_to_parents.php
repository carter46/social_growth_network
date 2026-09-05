<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remap listings.category_id to the marketplace product's parent category
 * so child-category cleanup cannot null taxonomy FKs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('listings') || ! Schema::hasColumn('listings', 'marketplace_product_id')) {
            return;
        }

        if (! Schema::hasTable('marketplace_products')) {
            return;
        }

        DB::table('marketplace_products')
            ->orderBy('id')
            ->chunkById(200, function ($products) {
                foreach ($products as $product) {
                    DB::table('listings')
                        ->where('marketplace_product_id', $product->id)
                        ->where(function ($q) use ($product) {
                            $q->whereNull('category_id')
                                ->orWhere('category_id', '!=', $product->category_id);
                        })
                        ->update(['category_id' => $product->category_id]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible data remap — no-op.
    }
};
