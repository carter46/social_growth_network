<?php

namespace App\Models;

use App\Observers\CampaignObserver;
use App\Support\ProductTaskProfile;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'status_before_hold',
        'account_hold_reason',
        'estimated_minutes',
        'meta',
        'required_tasks',
        'task_targets',
        'channel_url',
        'channel_id',
        'video_id',
        'comment_instructions',
        'completion_minutes',
    ];

    protected function casts(): array
    {
        return [
            'required_tasks' => 'array',
            'task_targets' => 'array',
            'completion_minutes' => 'integer',
            'quantity' => 'integer',
            'completed_count' => 'integer',
            'baseline_count' => 'integer',
            'last_verified_count' => 'integer',
            'baseline_captured_at' => 'datetime',
            'locked_creator_price' => 'decimal:6',
            'locked_agent_reward' => 'decimal:4',
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

    public function budget(): HasOne
    {
        return $this->hasOne(CampaignBudget::class);
    }

    public function taskProfile(): ProductTaskProfile
    {
        return ProductTaskProfile::forCampaign($this);
    }

    /** Bundled step flow (snapshot of a multi-task product). */
    public function usesStepFlow(): bool
    {
        return $this->taskProfile()->usesStepFlow();
    }

    /** Participation statuses that hold a slot (and the steps it was given). */
    public const SLOT_STATUSES = [
        CampaignParticipation::STATUS_STARTED,
        CampaignParticipation::STATUS_SUBMITTED,
        CampaignParticipation::STATUS_UNDER_REVIEW,
        CampaignParticipation::STATUS_VERIFYING,
        CampaignParticipation::STATUS_APPROVED,
        CampaignParticipation::STATUS_PAID,
    ];

    /**
     * Campaign bought with a target per service. Campaigns without targets (older orders and
     * single-service products) give every agent every step.
     */
    public function usesTaskTargets(): bool
    {
        return $this->usesStepFlow() && $this->taskTargets() !== [];
    }

    /** @return array<string, int> task value => agents needed for that step */
    public function taskTargets(): array
    {
        $targets = [];
        foreach ((array) ($this->task_targets ?? []) as $task => $target) {
            if (\App\Enums\CampaignTask::tryFrom((string) $task) !== null && (int) $target > 0) {
                $targets[(string) $task] = (int) $target;
            }
        }

        return $targets;
    }

    /**
     * Steps held by agents per task: given to a participation that is in progress, waiting or paid.
     *
     * @return array<string, int>
     */
    public function reservedStepCounts(): array
    {
        return $this->stepCounts(self::SLOT_STATUSES);
    }

    /** @return array<string, int> steps delivered (participation paid) per task */
    public function deliveredStepCounts(): array
    {
        return $this->stepCounts([CampaignParticipation::STATUS_PAID]);
    }

    /**
     * Steps a new agent would be given now, in campaign order. Campaigns without targets give every step.
     *
     * @return list<\App\Enums\CampaignTask>
     */
    public function openTasks(): array
    {
        $tasks = $this->taskProfile()->tasks();
        if (! $this->usesTaskTargets()) {
            return $tasks;
        }

        $targets = $this->taskTargets();
        $reserved = $this->reservedStepCounts();

        return array_values(array_filter(
            $tasks,
            fn (\App\Enums\CampaignTask $task) => ($reserved[$task->value] ?? 0) < ($targets[$task->value] ?? 0),
        ));
    }

    /**
     * Per-step progress for creator and admin views.
     *
     * @return list<array{task: \App\Enums\CampaignTask, label: string, target: int, delivered: int, in_progress: int}>
     */
    public function stepProgress(): array
    {
        if (! $this->usesTaskTargets()) {
            return [];
        }

        $profile = $this->taskProfile();
        $targets = $this->taskTargets();
        $reserved = $this->reservedStepCounts();
        $delivered = $this->deliveredStepCounts();
        $rows = [];
        foreach ($profile->tasks() as $task) {
            if (! isset($targets[$task->value])) {
                continue;
            }
            $done = min($targets[$task->value], $delivered[$task->value] ?? 0);
            $rows[] = [
                'task' => $task,
                'label' => $profile->quantityLabel($task),
                'target' => $targets[$task->value],
                'delivered' => $done,
                'in_progress' => max(0, ($reserved[$task->value] ?? 0) - ($delivered[$task->value] ?? 0)),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $participationStatuses
     * @return array<string, int>
     */
    private function stepCounts(array $participationStatuses): array
    {
        if (! $this->exists) {
            return [];
        }

        return CampaignParticipationTask::query()
            ->join('campaign_participations as p', 'p.id', '=', 'campaign_participation_tasks.participation_id')
            ->where('campaign_participation_tasks.campaign_id', $this->id)
            ->whereIn('p.status', $participationStatuses)
            ->groupBy('campaign_participation_tasks.task')
            ->selectRaw('campaign_participation_tasks.task as task, COUNT(*) as aggregate')
            ->pluck('aggregate', 'task')
            ->map(fn ($count) => (int) $count)
            ->all();
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
        if (in_array($this->status, self::WORKABLE_STATUSES, true)) {
            return true;
        }

        // A cancelled campaign with a held budget lets tasks already in progress finish or expire.
        return $this->status === self::STATUS_CANCELLED
            && \Illuminate\Support\Facades\Schema::hasTable('campaign_budgets')
            && $this->budget()->where('status', CampaignBudget::STATUS_HELD)->exists();
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

    /** New agents may only join campaigns for platforms that are still offered. */
    public function isOpenForAgents(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->remainingSlots() > 0
            && $this->availableStartSlots() > 0
            && ($this->platform_product_id === null || (bool) $this->product?->isOffered())
            && (! $this->usesTaskTargets() || $this->openTasks() !== []);
    }

    public function scopeOpenForAgents($query)
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('platform_product_id')
                ->orWhereHas('product', fn ($product) => $product->offered()))
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
