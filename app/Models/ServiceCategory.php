<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
        'banner_image',
        'card_image',
        'banner_media_id',
        'card_media_id',
        'short_description',
        'hero_title',
        'hero_subtitle',
        'benefits',
        'faq',
        'mode',
        'cta_label',
    ];

    /** Identity fields (key, slug) are not mass-assignable — set via forceFill/backfill only. */

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'benefits' => 'array',
            'faq' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** @return list<string> */
    public static function registryKeys(): array
    {
        return array_keys(config('platform_categories', []));
    }

    public function isSystem(): bool
    {
        $key = (string) ($this->key ?? '');

        return $key !== '' && array_key_exists($key, config('platform_categories', []));
    }

    public function scopeSystem(Builder $query): Builder
    {
        $keys = self::registryKeys();
        if ($keys === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('key', $keys);
    }

    public static function findByKey(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }

    public function bannerMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'banner_media_id');
    }

    public function cardMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'card_media_id');
    }

    /** Landscape thumb for admin lists — card image is source of truth. */
    public function listThumbnailUrl(): ?string
    {
        $media = $this->cardMedia ?? $this->bannerMedia;
        if ($media) {
            // Prefer uncropped variants so the full image shows in a wide cell.
            return $media->url('medium') ?? $media->url('small') ?? $media->thumbnailUrl();
        }

        return media_url(null, $this->card_image ?: $this->banner_image, 'medium');
    }

    /** Services (product_types) under this category — optional CMS / legacy. */
    public function services(): HasMany
    {
        return $this->hasMany(ProductType::class)->orderBy('sort_order');
    }

    public function productTypes(): HasMany
    {
        return $this->services();
    }

    /** Products owned directly by this category (Category → Product). */
    public function products(): HasMany
    {
        return $this->hasMany(PlatformProduct::class, 'service_category_id')->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Categories that own at least one publicly visible product. */
    public function scopeWithPublicProducts(Builder $query): Builder
    {
        return $query->whereHas('products', fn (Builder $q) => $q->visibleToPublic());
    }

    public function isMarketplaceLink(): bool
    {
        return $this->mode === 'marketplace_link';
    }
}
