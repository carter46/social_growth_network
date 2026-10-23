<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletFunding extends Model
{
    /** @use HasFactory<\Database\Factories\WalletFundingFactory> */
    use BelongsToActiveUser;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'reversed_at' => 'datetime',
            'checkout_expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function timelineEvents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PaymentTimelineEvent::class, 'subject')->orderBy('occurred_at');
    }

    public function isCheckoutExpired(): bool
    {
        return $this->checkout_expires_at !== null && $this->checkout_expires_at->isPast();
    }
}
