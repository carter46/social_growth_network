<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Agent budget held from the creator's payment; every reward is paid from it exactly once. */
class CampaignBudget extends Model
{
    public const STATUS_HELD = 'held';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'campaign_id',
        'funded_amount',
        'paid_amount',
        'refunded_amount',
        'released_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'funded_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'released_amount' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** Still held for future payouts. */
    public function remaining(): string
    {
        $used = bcadd(bcadd((string) $this->paid_amount, (string) $this->refunded_amount, 2), (string) $this->released_amount, 2);

        return bccomp((string) $this->funded_amount, $used, 2) > 0
            ? bcsub((string) $this->funded_amount, $used, 2)
            : '0.00';
    }
}
