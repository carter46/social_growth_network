<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
        self::STATUS_COMPLETED,
        self::STATUS_REJECTED,
        self::STATUS_SUSPENDED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'creator_id',
        'order_id',
        'order_item_id',
        'platform_product_id',
        'platform_product_variant_id',
        'title',
        'target_url',
        'quantity',
        'completed_count',
        'locked_creator_price',
        'locked_agent_reward',
        'status',
        'estimated_minutes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'completed_count' => 'integer',
            'locked_creator_price' => 'decimal:2',
            'locked_agent_reward' => 'decimal:2',
            'estimated_minutes' => 'integer',
            'meta' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(PlatformProduct::class, 'platform_product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PlatformProductVariant::class, 'platform_product_variant_id');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(CampaignParticipation::class);
    }

    public function remainingSlots(): int
    {
        return max(0, (int) $this->quantity - (int) $this->completed_count);
    }

    public function isOpenForAgents(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->remainingSlots() > 0;
    }

    public function scopeOpenForAgents($query)
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereColumn('completed_count', '<', 'quantity');
    }
}
