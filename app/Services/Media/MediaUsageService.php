<?php

namespace App\Services\Media;

use App\Models\CatalogPageContent;
use App\Models\MediaAsset;
use App\Models\MediaUsage;
use App\Models\PlatformProduct;
use App\Models\PlatformProductImage;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use App\Models\SystemSetting;
use App\Services\Branding\SiteBrandingRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MediaUsageService
{
    public function __construct(
        private MediaPathService $paths,
    ) {}

    /**
     * Replace media usages for the given model fields.
     *
     * @param  array<string, int|list<int>|null>  $fieldToMediaIdMap
     */
    public function syncUsages(Model $model, array $fieldToMediaIdMap): void
    {
        $usableType = $model->getMorphClass();
        $usableId = $model->getKey();
        $fields = array_keys($fieldToMediaIdMap);

        DB::transaction(function () use ($usableType, $usableId, $fields, $fieldToMediaIdMap): void {
            MediaUsage::query()
                ->where('usable_type', $usableType)
                ->where('usable_id', $usableId)
                ->whereIn('field', $fields)
                ->delete();

            foreach ($fieldToMediaIdMap as $field => $mediaId) {
                if ($mediaId === null) {
                    continue;
                }

                $ids = is_array($mediaId) ? $mediaId : [$mediaId];
                foreach ($ids as $id) {
                    if ($id === null || $id === '') {
                        continue;
                    }

                    MediaUsage::query()->create([
                        'media_asset_id' => (int) $id,
                        'usable_type' => $usableType,
                        'usable_id' => $usableId,
                        'field' => (string) $field,
                    ]);
                }
            }
        });
    }

    /**
     * Remove all usage rows for a model (call before/after deleting the usable).
     */
    public function detachAllFor(Model $model): void
    {
        MediaUsage::query()
            ->where('usable_type', $model->getMorphClass())
            ->where('usable_id', $model->getKey())
            ->delete();
    }

    /**
     * Remap usages and rewrite catalog FKs + legacy path columns + gallery image paths.
     */
    public function replaceAsset(int $oldId, int $newId): int
    {
        if ($oldId === $newId) {
            return 0;
        }

        $old = MediaAsset::query()->with('variants')->findOrFail($oldId);
        $new = MediaAsset::query()->with('variants')->findOrFail($newId);

        return (int) DB::transaction(function () use ($old, $new): int {
            $usages = MediaUsage::query()
                ->where('media_asset_id', $old->id)
                ->get();

            foreach ($usages as $usage) {
                $this->rewriteUsableReference($usage, $old, $new);
            }

            $updated = MediaUsage::query()
                ->where('media_asset_id', $old->id)
                ->update(['media_asset_id' => $new->id]);

            Log::info('media.replace', [
                'old_media_id' => $old->id,
                'new_media_id' => $new->id,
                'usages_updated' => $updated,
            ]);

            return $updated;
        });
    }

    public function usageCount(int $mediaId): int
    {
        return MediaUsage::query()->where('media_asset_id', $mediaId)->count();
    }

    protected function rewriteUsableReference(MediaUsage $usage, MediaAsset $old, MediaAsset $new): void
    {
        $type = $usage->usable_type;
        $id = $usage->usable_id;
        $field = $usage->field;

        if ($type === ServiceCategory::class || $type === (new ServiceCategory)->getMorphClass()) {
            $model = ServiceCategory::query()->find($id);
            if ($model) {
                $this->rewriteBannerCard($model, $field, $new);
            }

            return;
        }

        if ($type === ProductType::class || $type === (new ProductType)->getMorphClass()) {
            $model = ProductType::query()->find($id);
            if ($model) {
                $this->rewriteBannerCard($model, $field, $new);
            }

            return;
        }

        if ($type === CatalogPageContent::class || $type === (new CatalogPageContent)->getMorphClass()) {
            $model = CatalogPageContent::query()->find($id);
            if ($model) {
                $this->rewriteBannerCard($model, $field, $new, bannerVariant: 'large');
            }

            return;
        }

        if ($type === PlatformProduct::class || $type === (new PlatformProduct)->getMorphClass()) {
            $model = PlatformProduct::query()->find($id);
            if (! $model) {
                return;
            }

            if ($field === 'hero') {
                $model->forceFill([
                    'hero_media_id' => $new->id,
                    'hero_image' => $this->paths->legacyPathFromMediaId($new->id, 'medium'),
                ])->save();

                return;
            }

            if ($field === 'gallery') {
                $this->rewriteGalleryPaths($model, $old, $new);
            }

            return;
        }

        if ($type === 'site_branding') {
            $settingKey = match ($field) {
                'favicon' => 'favicon_media_id',
                'logo_light' => 'logo_light_media_id',
                'logo_dark' => 'logo_dark_media_id',
                default => null,
            };
            if ($settingKey === null) {
                return;
            }
            SystemSetting::set($settingKey, (string) $new->id);
            app(SiteBrandingRepository::class)->flush();
        }
    }

    /**
     * @param  ServiceCategory|ProductType|CatalogPageContent  $model
     */
    protected function rewriteBannerCard(Model $model, string $field, MediaAsset $new, string $bannerVariant = 'medium'): void
    {
        if ($field === 'banner') {
            $model->forceFill([
                'banner_media_id' => $new->id,
                'banner_image' => $this->paths->legacyPathFromMediaId($new->id, $bannerVariant),
            ])->save();

            return;
        }

        if ($field === 'card') {
            $model->forceFill([
                'card_media_id' => $new->id,
                'card_image' => $this->paths->legacyPathFromMediaId($new->id, 'medium'),
            ])->save();
        }
    }

    protected function rewriteGalleryPaths(PlatformProduct $product, MediaAsset $old, MediaAsset $new): void
    {
        $newPath = $this->paths->legacyPathFromMediaId($new->id, 'medium')
            ?? $new->variantStoragePath('medium');

        $updated = PlatformProductImage::query()
            ->where('platform_product_id', $product->id)
            ->where('media_asset_id', $old->id)
            ->update([
                'media_asset_id' => $new->id,
                'path' => $newPath,
                'alt' => $new->alt ?: $product->title,
            ]);

        if ($updated > 0 || ! $newPath) {
            return;
        }

        $oldPaths = collect($old->variants)
            ->map(fn ($v) => [
                'storage/'.$v->path,
                $v->path,
                ltrim((string) parse_url($old->url($v->key) ?? '', PHP_URL_PATH), '/'),
            ])
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        PlatformProductImage::query()
            ->where('platform_product_id', $product->id)
            ->whereIn('path', $oldPaths)
            ->update([
                'media_asset_id' => $new->id,
                'path' => $newPath,
                'alt' => $new->alt ?: $product->title,
            ]);
    }
}
