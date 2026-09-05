<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A) Create marketplace_products table
        if (! Schema::hasTable('marketplace_products')) {
            Schema::create('marketplace_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('short_description', 500)->nullable();
                $table->string('hero_title')->nullable();
                $table->string('hero_subtitle', 500)->nullable();
                $table->json('benefits')->nullable();
                $table->json('faq')->nullable();
                $table->string('banner_image')->nullable();
                $table->string('card_image')->nullable();
                $table->unsignedBigInteger('banner_media_id')->nullable();
                $table->unsignedBigInteger('card_media_id')->nullable();
                $table->string('icon')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->string('og_title')->nullable();
                $table->text('og_description')->nullable();
                $table->string('og_image')->nullable();
                $table->timestamps();

                $table->index('banner_media_id');
                $table->index('card_media_id');
                $table->index(['category_id', 'is_active']);
            });

            // Add FK to media_assets if table exists
            if (Schema::hasTable('media_assets')) {
                Schema::table('marketplace_products', function (Blueprint $table) {
                    $table->foreign('banner_media_id')
                        ->references('id')
                        ->on('media_assets')
                        ->nullOnDelete();
                    $table->foreign('card_media_id')
                        ->references('id')
                        ->on('media_assets')
                        ->nullOnDelete();
                });
            }
        }

        // B) Add CMS columns to categories if missing
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (! Schema::hasColumn('categories', 'short_description')) {
                    $table->string('short_description', 500)->nullable()->after('sort_order');
                }
                if (! Schema::hasColumn('categories', 'hero_title')) {
                    $table->string('hero_title')->nullable()->after('short_description');
                }
                if (! Schema::hasColumn('categories', 'hero_subtitle')) {
                    $table->string('hero_subtitle', 500)->nullable()->after('hero_title');
                }
                if (! Schema::hasColumn('categories', 'benefits')) {
                    $table->json('benefits')->nullable()->after('hero_subtitle');
                }
                if (! Schema::hasColumn('categories', 'faq')) {
                    $table->json('faq')->nullable()->after('benefits');
                }
                if (! Schema::hasColumn('categories', 'banner_image')) {
                    $table->string('banner_image')->nullable()->after('faq');
                }
                if (! Schema::hasColumn('categories', 'card_image')) {
                    $table->string('card_image')->nullable()->after('banner_image');
                }
                if (! Schema::hasColumn('categories', 'banner_media_id')) {
                    $table->unsignedBigInteger('banner_media_id')->nullable()->after('card_image');
                }
                if (! Schema::hasColumn('categories', 'card_media_id')) {
                    $table->unsignedBigInteger('card_media_id')->nullable()->after('banner_media_id');
                }
                if (! Schema::hasColumn('categories', 'icon')) {
                    $table->string('icon')->nullable()->after('card_media_id');
                }
                if (! Schema::hasColumn('categories', 'seo_title')) {
                    $table->string('seo_title')->nullable()->after('icon');
                }
                if (! Schema::hasColumn('categories', 'seo_description')) {
                    $table->text('seo_description')->nullable()->after('seo_title');
                }
                if (! Schema::hasColumn('categories', 'og_title')) {
                    $table->string('og_title')->nullable()->after('seo_description');
                }
                if (! Schema::hasColumn('categories', 'og_description')) {
                    $table->text('og_description')->nullable()->after('og_title');
                }
                if (! Schema::hasColumn('categories', 'og_image')) {
                    $table->string('og_image')->nullable()->after('og_description');
                }
            });

            // Add indices for media_id columns if they don't exist
            if (Schema::hasColumn('categories', 'banner_media_id') && ! $this->indexExists('categories', 'categories_banner_media_id_index')) {
                Schema::table('categories', function (Blueprint $table) {
                    $table->index('banner_media_id');
                });
            }
            if (Schema::hasColumn('categories', 'card_media_id') && ! $this->indexExists('categories', 'categories_card_media_id_index')) {
                Schema::table('categories', function (Blueprint $table) {
                    $table->index('card_media_id');
                });
            }

            // Add FK to media_assets if table exists
            if (Schema::hasTable('media_assets')) {
                if (Schema::hasColumn('categories', 'banner_media_id') && ! $this->foreignKeyExists('categories', 'categories_banner_media_id_foreign')) {
                    Schema::table('categories', function (Blueprint $table) {
                        $table->foreign('banner_media_id')
                            ->references('id')
                            ->on('media_assets')
                            ->nullOnDelete();
                    });
                }
                if (Schema::hasColumn('categories', 'card_media_id') && ! $this->foreignKeyExists('categories', 'categories_card_media_id_foreign')) {
                    Schema::table('categories', function (Blueprint $table) {
                        $table->foreign('card_media_id')
                            ->references('id')
                            ->on('media_assets')
                            ->nullOnDelete();
                    });
                }
            }
        }

        // C) Add to listings if missing
        if (Schema::hasTable('listings')) {
            Schema::table('listings', function (Blueprint $table) {
                if (! Schema::hasColumn('listings', 'marketplace_product_id')) {
                    $table->foreignId('marketplace_product_id')
                        ->nullable()
                        ->after('category_id')
                        ->constrained('marketplace_products')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('listings', 'deleted_at')) {
                    $table->softDeletes();
                }
            });

            // Expand status column length if needed
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                Schema::getConnection()->statement(
                    'ALTER TABLE listings MODIFY status VARCHAR(32) DEFAULT "published"'
                );
            } elseif (in_array($driver, ['pgsql', 'postgres'], true)) {
                Schema::getConnection()->statement(
                    'ALTER TABLE listings ALTER COLUMN status TYPE VARCHAR(32)'
                );
            }
        }

        // D) DATA MIGRATION (copy, do NOT delete)
        // For each Category where parent_id is not null:
        // Create MarketplaceProduct with same name, slug, sort_order, is_active, category_id = parent_id
        // Then for listings where category_id = that child id, set marketplace_product_id = new product id
        if (Schema::hasTable('marketplace_products') && Schema::hasTable('categories') && Schema::hasTable('listings')) {
            $childCategories = DB::table('categories')->whereNotNull('parent_id')->get();

            foreach ($childCategories as $child) {
                $existingId = DB::table('marketplace_products')->where('slug', $child->slug)->value('id');
                if ($existingId) {
                    $productId = $existingId;
                } else {
                    $productId = DB::table('marketplace_products')->insertGetId([
                        'slug' => $child->slug,
                        'category_id' => $child->parent_id,
                        'name' => $child->name,
                        'sort_order' => $child->sort_order ?? 0,
                        'is_active' => (bool) ($child->is_active ?? true),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('listings')
                    ->where('category_id', $child->id)
                    ->whereNull('marketplace_product_id')
                    ->update(['marketplace_product_id' => $productId]);
            }
        }
    }

    public function down(): void
    {
        // Drop marketplace_product_id from listings
        if (Schema::hasTable('listings') && Schema::hasColumn('listings', 'marketplace_product_id')) {
            Schema::table('listings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('marketplace_product_id');
            });
        }

        // Soft-deleted rows would resurface as live if we drop deleted_at.
        // Purge them first so rollback never republishes tombstones.
        if (Schema::hasTable('listings') && Schema::hasColumn('listings', 'deleted_at')) {
            DB::table('listings')->whereNotNull('deleted_at')->delete();
            Schema::table('listings', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        // Drop CMS columns from categories
        if (Schema::hasTable('categories')) {
            // Drop foreign keys first if they exist
            if (Schema::hasTable('media_assets')) {
                if ($this->foreignKeyExists('categories', 'categories_banner_media_id_foreign')) {
                    Schema::table('categories', function (Blueprint $table) {
                        $table->dropForeign('categories_banner_media_id_foreign');
                    });
                }
                if ($this->foreignKeyExists('categories', 'categories_card_media_id_foreign')) {
                    Schema::table('categories', function (Blueprint $table) {
                        $table->dropForeign('categories_card_media_id_foreign');
                    });
                }
            }

            Schema::table('categories', function (Blueprint $table) {
                $columns = [
                    'short_description', 'hero_title', 'hero_subtitle', 'benefits', 'faq',
                    'banner_image', 'card_image', 'banner_media_id', 'card_media_id', 'icon',
                    'seo_title', 'seo_description', 'og_title', 'og_description', 'og_image',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('categories', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        // Drop marketplace_products table
        Schema::dropIfExists('marketplace_products');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (($foreignKey['name'] ?? '') === $foreignKeyName) {
                return true;
            }
        }

        return false;
    }
};
