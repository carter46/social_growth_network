<?php

namespace App\Models;

use App\Enums\EngagementMetric;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformProductVariant extends Model
{
    public const PRICING_FIXED = 'fixed';

    public const PRICING_PER_UNIT = 'per_unit';

    /** Platform-wide reference package size for per-unit rate (price ÷ REFERENCE_UNITS). */
    public const REFERENCE_UNITS = 1000;

    public const DEFAULT_MAX_UNITS = 100000;

    protected $fillable = [
        'platform_product_id',
        'name',
        'label',
        'description',
        'sku',
        'duration_months',
        'price',
        'pricing_mode',
        'unit_price',
        'min_units',
        'max_units',
        'unit_label',
        'included_units',
        'sort_order',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'min_units' => 'integer',
            'max_units' => 'integer',
            'included_units' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(PlatformProduct::class, 'platform_product_id');
    }

    public function displayLabel(): string
    {
        return $this->label ?: $this->name;
    }

    public function isPerUnit(): bool
    {
        return ($this->pricing_mode ?? self::PRICING_FIXED) === self::PRICING_PER_UNIT;
    }

    public function isFixedPricing(): bool
    {
        return ! $this->isPerUnit();
    }

    /**
     * unit_price = package price ÷ REFERENCE_UNITS (never parse the name).
     */
    public static function computeUnitPriceFromPackagePrice(float|string $packagePrice): string
    {
        return bcdiv((string) $packagePrice, (string) self::REFERENCE_UNITS, 4);
    }

    public function billingUnitPrice(): string
    {
        if ($this->isPerUnit()) {
            if ($this->unit_price !== null) {
                return number_format((float) $this->unit_price, 4, '.', '');
            }

            return self::computeUnitPriceFromPackagePrice((float) $this->price);
        }

        return number_format((float) $this->price, 2, '.', '');
    }

    /**
     * Lowest charge a creator would pay for this variant (card “from” / starting total).
     */
    public function startingFromAmount(): float
    {
        if ($this->isPerUnit()) {
            $min = max(1, (int) ($this->min_units ?: 1));
            $rate = (float) $this->billingUnitPrice();

            return round($rate * $min, 2);
        }

        return (float) $this->price;
    }

    public function effectiveMinUnits(): int
    {
        return max(1, (int) ($this->min_units ?: 1));
    }

    public function effectiveMaxUnits(): int
    {
        $max = (int) ($this->max_units ?: self::DEFAULT_MAX_UNITS);
        $min = $this->effectiveMinUnits();

        return max($min, $max);
    }

    public function resolveUnitLabel(?EngagementMetric $metric = null): string
    {
        if (filled($this->unit_label)) {
            return (string) $this->unit_label;
        }

        $metric ??= EngagementMetric::fromProductSlug($this->product?->slug);

        return match ($metric) {
            EngagementMetric::Likes => 'like',
            EngagementMetric::Comments => 'comment',
            EngagementMetric::Views => 'view',
            EngagementMetric::WatchHours => 'watch hour',
            default => 'unit',
        };
    }

    public function resolveUnitLabelPlural(?EngagementMetric $metric = null): string
    {
        $singular = $this->resolveUnitLabel($metric);

        return match ($singular) {
            'like' => 'likes',
            'comment' => 'comments',
            'view' => 'views',
            'watch hour' => 'watch hours',
            default => $singular.'s',
        };
    }

    /**
     * JSON payload for product/checkout Alpine UIs.
     *
     * @return array<string, mixed>
     */
    public function storefrontPayload(?EngagementMetric $metric = null): array
    {
        $metric ??= EngagementMetric::fromProductSlug($this->product?->slug);

        return [
            'id' => $this->id,
            'label' => $this->displayLabel(),
            'price' => (float) $this->price,
            'description' => (string) ($this->description ?? ''),
            'pricing_mode' => $this->pricing_mode ?? self::PRICING_FIXED,
            'per_unit' => $this->isPerUnit(),
            'unit_price' => $this->isPerUnit() ? (float) $this->billingUnitPrice() : null,
            'min_units' => $this->isPerUnit() ? $this->effectiveMinUnits() : null,
            'max_units' => $this->isPerUnit() ? $this->effectiveMaxUnits() : null,
            'unit_label' => $this->resolveUnitLabel($metric),
            'unit_label_plural' => $this->resolveUnitLabelPlural($metric),
            'included_units' => $this->isFixedPricing()
                ? ($this->included_units !== null ? (int) $this->included_units : null)
                : null,
            'starting_from' => $this->startingFromAmount(),
        ];
    }
}
