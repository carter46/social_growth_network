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
            'funded_amount' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'refunded_amount' => 'decimal:4',
            'released_amount' => 'decimal:4',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** Still held for future payouts. */
    public function remaining(): string
    {
        $used = bcadd(bcadd((string) $this->paid_amount, (string) $this->refunded_amount, 4), (string) $this->released_amount, 4);

        return bccomp((string) $this->funded_amount, $used, 4) > 0
            ? bcsub((string) $this->funded_amount, $used, 4)
            : '0.0000';
    }
}
