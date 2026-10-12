<?php

namespace App\Models;

use App\Enums\WalletHoldReason;
use App\Enums\WalletHoldStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletHold extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'reason_type' => WalletHoldReason::class,
            'status' => WalletHoldStatus::class,
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function isActive(): bool
    {
        return $this->status === WalletHoldStatus::Active;
    }
}
