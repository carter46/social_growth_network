<?php

namespace Tests\Feature\Catalog;

use App\Models\MediaAsset;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Modules\Catalog\Services\CatalogContentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogContentImageOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_category_media_library_image_is_used(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.media.store'), [
                'files' => [UploadedFile::fake()->image('new-banner.png', 800, 500)],
            ])
            ->assertCreated();

        $asset = MediaAsset::query()->latest('id')->firstOrFail();

        $category = $this->forceCreateServiceCategory([
            'name' => 'YouTube',
            'slug' => 'youtube',
            'sort_order' => 0,
            'is_active' => true,
            'mode' => 'catalog',
            'banner_media_id' => $asset->id,
            'card_media_id' => $asset->id,
            'banner_image' => 'storage/media/fake/new.webp',
            'card_image' => 'storage/media/fake/new.webp',
        ]);

        $resolved = app(CatalogContentResolver::class)->forServiceCategory(
            $category->fresh(['bannerMedia.variants', 'cardMedia.variants'])
        );

        $this->assertNotNull($resolved['banner_image']);
        $this->assertStringContainsString('storage', $resolved['banner_image']);
    }

    public function test_admin_can_set_service_category_image_via_edit_form(): void
    {
        Storage::fake('public');

        \Illuminate\Support\Facades\Artisan::call('catalog:backfill-hierarchy');

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.media.store'), [
                'files' => [UploadedFile::fake()->image('category-card.png', 800, 500)],
            ])
            ->assertCreated();

        $asset = MediaAsset::query()->latest('id')->firstOrFail();
        $category = ServiceCategory::query()->where('slug', 'youtube')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.service-categories.update', $category), [
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'is_active' => '1',
                'card_media_id' => $asset->id,
                'short_description' => 'YouTube campaign packages',
            ])
            ->assertRedirect(route('admin.service-categories'));

        $category->refresh();
        $this->assertSame($asset->id, $category->card_media_id);
        $this->assertSame('YouTube campaign packages', $category->short_description);
        $this->assertSame($category->name, $category->hero_title);
        $this->assertSame('YouTube campaign packages', $category->hero_subtitle);
        $this->assertSame([], $category->benefits);
    }
}
