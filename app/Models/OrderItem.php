<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'item_type',
        'item_id',
        'quantity',
        'unit_price',
        'line_total',
        'platform_product_variant_id',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:6',
            'line_total' => 'decimal:2',
            'options' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PlatformProductVariant::class, 'platform_product_variant_id');
    }
}
