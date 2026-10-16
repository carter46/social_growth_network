<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepositionProductDescriptionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_default_descriptions_are_replaced_and_edited_ones_are_kept(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);

        $set = fn (string $slug, string $description) => DB::table('platform_products')
            ->where('slug', $slug)
            ->update(['description' => $description]);

        $set('youtube-likes', 'Order likes for a public YouTube post or video. Choose a package, add the link at checkout, and track progress from your account.');
        $set('youtube-comments', 'Order comments for a public YouTube video. Choose a package, add the link at checkout, and track progress from your account.');
        $set('youtube-views', 'My own edited description.');

        $migration = require database_path('migrations/2026_10_16_000300_reposition_seeded_product_descriptions.php');
        $migration->up();

        $description = fn (string $slug) => DB::table('platform_products')->where('slug', $slug)->value('description');

        $this->assertStringStartsWith('Boost engagement on a public YouTube video.', $description('youtube-likes'));
        $this->assertStringStartsWith('Promote your video to increase watch time', $description('youtube-comments'));
        $this->assertSame('My own edited description.', $description('youtube-views'));
    }
}
