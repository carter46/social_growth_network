<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_product_variants')) {
            return;
        }

        Schema::table('platform_product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_product_variants', 'pricing_mode')) {
                $table->string('pricing_mode', 16)->default('fixed')->after('price');
            }
            if (! Schema::hasColumn('platform_product_variants', 'unit_price')) {
                $table->decimal('unit_price', 18, 4)->nullable()->after('pricing_mode');
            }
            if (! Schema::hasColumn('platform_product_variants', 'min_units')) {
                $table->unsignedInteger('min_units')->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('platform_product_variants', 'max_units')) {
                $table->unsignedInteger('max_units')->nullable()->after('min_units');
            }
            if (! Schema::hasColumn('platform_product_variants', 'unit_label')) {
                $table->string('unit_label', 32)->nullable()->after('max_units');
            }
            if (! Schema::hasColumn('platform_product_variants', 'included_units')) {
                $table->unsignedInteger('included_units')->nullable()->after('unit_label');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_product_variants')) {
            return;
        }

        Schema::table('platform_product_variants', function (Blueprint $table) {
            foreach (['included_units', 'unit_label', 'max_units', 'min_units', 'unit_price', 'pricing_mode'] as $col) {
                if (Schema::hasColumn('platform_product_variants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
