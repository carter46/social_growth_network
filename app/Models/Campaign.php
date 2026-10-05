<?php

namespace App\Models;

use App\Observers\CampaignObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([CampaignObserver::class])]
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
        'engagement_metric',
        'baseline_count',
        'baseline_captured_at',
        'last_verified_count',
        'verification_mode',
        'verification_locked_participation_id',
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
            'baseline_count' => 'integer',
            'last_verified_count' => 'integer',
            'baseline_captured_at' => 'datetime',
            'locked_creator_price' => 'decimal:2',
            'locked_agent_reward' => 'decimal:2',
            'estimated_minutes' => 'integer',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $campaign) {
            if ($campaign->exists && $campaign->isDirty('locked_agent_reward')) {
                $campaign->locked_agent_reward = $campaign->getOriginal('locked_agent_reward');
            }
            if ($campaign->exists && $campaign->isDirty('locked_creator_price')) {
                $campaign->locked_creator_price = $campaign->getOriginal('locked_creator_price');
            }
        });
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

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING_REVIEW => 'Pending review',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_PAUSED => 'Paused',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_SUSPENDED => 'Suspended',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /**
     * Agents who already joined may keep working (submit, watch, get paid) only in these statuses.
     * New agents can join only when the campaign is active.
     */
    public const WORKABLE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
    ];

    /**
     * What the creator paid for this campaign (order line total), as a 2-decimal string.
     */
    public function totalCost(): string
    {
        $item = $this->relationLoaded('orderItem') ? $this->orderItem : $this->orderItem()->first();

        if ($item) {
            return number_format((float) $item->line_total, 2, '.', '');
        }

        return bcmul((string) $this->locked_creator_price, (string) max(0, (int) $this->quantity), 2);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function allowsAgentWork(): bool
    {
        return in_array($this->status, self::WORKABLE_STATUSES, true);
    }

    public function assertAllowsAgentWork(): void
    {
        if (! $this->allowsAgentWork()) {
            throw new \InvalidArgumentException('This campaign is '.strtolower($this->statusLabel()).', so tasks on it cannot be submitted or paid right now.');
        }
    }

    public function remainingSlots(): int
    {
        return max(0, (int) $this->quantity - (int) $this->completed_count);
    }

    /**
     * Participations that occupy a campaign slot before/after payment.
     */
    public function inFlightCount(): int
    {
        return (int) $this->participations()
            ->whereIn('status', [
                CampaignParticipation::STATUS_STARTED,
                CampaignParticipation::STATUS_SUBMITTED,
                CampaignParticipation::STATUS_UNDER_REVIEW,
                CampaignParticipation::STATUS_VERIFYING,
                CampaignParticipation::STATUS_APPROVED,
                CampaignParticipation::STATUS_PAID,
            ])
            ->count();
    }

    public function availableStartSlots(): int
    {
        return max(0, (int) $this->quantity - $this->inFlightCount());
    }

    public function isOpenForAgents(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->remainingSlots() > 0
            && $this->availableStartSlots() > 0;
    }

    public function scopeOpenForAgents($query)
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereColumn('completed_count', '<', 'quantity')
            ->whereRaw(
                '(SELECT COUNT(*) FROM campaign_participations WHERE campaign_participations.campaign_id = campaigns.id AND campaign_participations.status IN (?, ?, ?, ?, ?, ?)) < campaigns.quantity',
                [
                    CampaignParticipation::STATUS_STARTED,
                    CampaignParticipation::STATUS_SUBMITTED,
                    CampaignParticipation::STATUS_UNDER_REVIEW,
                    CampaignParticipation::STATUS_VERIFYING,
                    CampaignParticipation::STATUS_APPROVED,
                    CampaignParticipation::STATUS_PAID,
                ]
            );
    }
}
