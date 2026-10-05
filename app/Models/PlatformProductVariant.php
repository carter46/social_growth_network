<?php

namespace App\Models;

use App\Enums\EngagementMetric;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformProductVariant extends Model
{
    public const PRICING_FIXED = 'fixed';

    public const PRICING_PER_UNIT = 'per_unit';

    /** Default pricing unit ("per 1,000") when a plan has none set. */
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
        'pricing_units',
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
            'pricing_units' => 'integer',
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
     * unit_price = price per pricing unit ÷ pricing units (never parse the name).
     */
    public static function computeUnitPriceFromPackagePrice(float|string $packagePrice, int $pricingUnits = self::REFERENCE_UNITS): string
    {
        return bcdiv(number_format((float) $packagePrice, 2, '.', ''), (string) max(1, $pricingUnits), 4);
    }

    public function effectivePricingUnits(): int
    {
        return max(1, (int) ($this->pricing_units ?: self::REFERENCE_UNITS));
    }

    public function billingUnitPrice(): string
    {
        if ($this->isPerUnit()) {
            if ($this->unit_price !== null) {
                return number_format((float) $this->unit_price, 4, '.', '');
            }

            return self::computeUnitPriceFromPackagePrice((float) $this->price, $this->effectivePricingUnits());
        }

        return number_format((float) $this->price, 2, '.', '');
    }

    /**
     * Agent earning for one completed unit, rounded down to the kobo.
     */
    public static function agentRewardForUnitPrice(float|string|null $unitPrice, float|string|null $percent): string
    {
        $unit = number_format(max(0, (float) $unitPrice), 4, '.', '');
        $pct = number_format(min(100, max(0, (float) $percent)), 2, '.', '');

        return bcdiv(bcmul($unit, $pct, 6), '100', 2);
    }

    public function agentRewardPerUnit(float|string|null $percent): string
    {
        return self::agentRewardForUnitPrice($this->billingUnitPrice(), $percent);
    }

    /** e.g. "₦8,000 per 1,000 views" or "₦8 per view". */
    public function pricingLabel(?EngagementMetric $metric = null): string
    {
        $units = $this->effectivePricingUnits();
        $price = (float) $this->price;
        $amount = '₦'.number_format($price, fmod($price, 1.0) === 0.0 ? 0 : 2);

        if ($units === 1) {
            return $amount.' per '.$this->resolveUnitLabel($metric);
        }

        return $amount.' per '.number_format($units).' '.$this->resolveUnitLabelPlural($metric);
    }

    /**
     * Lowest charge a creator would pay for this variant (card “from” / starting total).
     */
    public function startingFromAmount(): float
    {
        if ($this->isPerUnit()) {
            return round((float) $this->billingUnitPrice() * $this->effectiveMinUnits(), 2);
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
            'pricing_units' => $this->effectivePricingUnits(),
            'pricing_label' => $this->pricingLabel($metric),
            'unit_label' => $this->resolveUnitLabel($metric),
            'unit_label_plural' => $this->resolveUnitLabelPlural($metric),
            'starting_from' => $this->startingFromAmount(),
        ];
    }
}
