<?php

namespace App\Models;

use App\Enums\PlatformProductStatus;
use App\Enums\PlatformProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;

class PlatformProduct extends Model
{
    protected $fillable = [
        'title',
        'short_description',
        'description',
        'status',
        'is_featured',
        'sort_order',
        'hero_image',
        'hero_media_id',
        'demo_url',
        'demo_username',
        'demo_password',
        'tutorial_url',
        'tutorial_description',
        'industry',
        'framework',
        'is_responsive',
        'is_seo_ready',
        'support_period',
        'features',
        'requirements',
        'whats_included',
        'faqs',
        'support_text',
        'base_price',
        'is_campaign',
        'agent_reward_per_completion',
        'estimated_minutes',
        'meta',
    ];

    /**
     * Locked identity / fulfillment fields are not mass-assignable:
     * product_type_id, product_type, slug, provider*, fulfillment_mode, auto_renew, platform_category_id.
     * Set via forceFill in seeders/backfill only.
     */

    protected function casts(): array
    {
        return [
            'product_type' => PlatformProductType::class,
            'status' => PlatformProductStatus::class,
            'is_featured' => 'boolean',
            'is_responsive' => 'boolean',
            'is_seo_ready' => 'boolean',
            'auto_renew' => 'boolean',
            'features' => 'array',
            'requirements' => 'array',
            'whats_included' => 'array',
            'faqs' => 'array',
            'meta' => 'array',
            'provider_meta' => 'array',
            'base_price' => 'decimal:2',
            'is_campaign' => 'boolean',
            'agent_reward_per_completion' => 'decimal:2',
            'estimated_minutes' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PlatformCategory::class, 'platform_category_id');
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'hero_media_id');
    }

    /** UI alias: Service under Service Category. */
    public function service(): BelongsTo
    {
        return $this->productType();
    }

    public function images(): HasMany
    {
        return $this->hasMany(PlatformProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PlatformProductVariant::class)->orderBy('sort_order');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function siteIntegration(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SiteIntegration::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PlatformProductStatus::Published);
    }

    /**
     * Published and reachable: parent service + category must be active.
     */
    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query->published()->whereHas('productType', function (Builder $service) {
            $service->where('is_active', true)
                ->whereHas('serviceCategory', fn (Builder $cat) => $cat->where('is_active', true));
        });
    }

    public function isVisibleToPublic(): bool
    {
        if ($this->status !== PlatformProductStatus::Published) {
            return false;
        }

        $service = $this->relationLoaded('productType')
            ? $this->productType
            : $this->productType()->with('serviceCategory')->first();

        if (! $service || ! $service->is_active) {
            return false;
        }

        $category = $service->relationLoaded('serviceCategory')
            ? $service->serviceCategory
            : $service->serviceCategory()->first();

        return (bool) ($category && $category->is_active);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOfType(Builder $query, PlatformProductType|string $type): Builder
    {
        $value = $type instanceof PlatformProductType ? $type->value : $type;

        return $query->where(function (Builder $inner) use ($value) {
            $inner->where('product_type', $value);
            if (Schema::hasColumn('platform_products', 'product_type_id')) {
                $inner->orWhereHas('productType', fn (Builder $q) => $q->where('slug', $value));
            }
        });
    }

    /**
     * @param  list<string>  $types
     */
    public function scopeOfTypeMany(Builder $query, array $types): Builder
    {
        if ($types === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($types) {
            $inner->whereIn('product_type', $types);
            if (Schema::hasColumn('platform_products', 'product_type_id')) {
                $inner->orWhereHas('productType', fn (Builder $q) => $q->whereIn('slug', $types));
            }
        });
    }

    public function scopeOfService(Builder $query, int|ProductType $service): Builder
    {
        $id = $service instanceof ProductType ? $service->id : $service;

        return $query->where('product_type_id', $id);
    }

    public function typeSlug(): ?string
    {
        if ($this->relationLoaded('productType') && $this->productType) {
            return $this->productType->slug;
        }

        if ($this->product_type_id) {
            return ProductType::query()->where('id', $this->product_type_id)->value('slug');
        }

        $type = $this->product_type;

        return $type instanceof PlatformProductType ? $type->value : $type;
    }

    public function displayPrice(): float
    {
        $variants = $this->relationLoaded('activeVariants')
            ? $this->activeVariants
            : $this->activeVariants()->get();

        $lowest = $variants->sortBy('price')->first();

        return (float) ($lowest?->price ?? $this->base_price);
    }

    /** Landscape thumb for admin lists — hero image is source of truth. */
    public function listThumbnailUrl(): ?string
    {
        $media = $this->relationLoaded('heroMedia') ? $this->heroMedia : $this->heroMedia()->with('variants')->first();
        if ($media) {
            return $media->url('medium') ?? $media->url('small') ?? $media->thumbnailUrl();
        }

        return media_url(null, $this->hero_image, 'medium');
    }

    public function hasTutorialDetails(): bool
    {
        return filled($this->tutorial_url) || filled($this->tutorial_description);
    }
}
