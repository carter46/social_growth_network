<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCommission extends Model
{
    public const KIND_AGENT_EARNING = 'agent_earning';

    public const KIND_CREATOR_SPEND = 'creator_spend';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CREDITED = 'credited';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'referrer_id',
        'referred_user_id',
        'kind',
        'source_type',
        'source_id',
        'base_amount',
        'rate',
        'amount',
        'status',
        'transaction_id',
        'reversal_transaction_id',
        'credited_at',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'credited_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
