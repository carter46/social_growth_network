<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    public const PAYMENT_MANUAL_BANK_TRANSFER = 'manual_bank_transfer';

    protected $fillable = [
        'source',
        'user_id',
        'reference',
        'idempotency_key',
        'amount',
        'total_amount',
        'status',
        'payment_method',
        'payment_provider',
        'provider_payment_reference',
        'provider_transaction_reference',
        'checkout_url',
        'checkout_expires_at',
        'payment_submitted_at',
        'payment_confirmed_at',
        'payment_confirmed_by',
        'payment_metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'checkout_expires_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'payment_metadata' => 'array',
        ];
    }

    public function isCheckoutExpired(): bool
    {
        return $this->checkout_expires_at !== null && $this->checkout_expires_at->isPast();
    }

    public function isAwaitingGatewayPayment(): bool
    {
        return $this->payment_method === 'gateway'
            && in_array($this->status, ['pending', 'processing'], true);
    }

    public function isAwaitingManualBankTransfer(): bool
    {
        return $this->payment_method === self::PAYMENT_MANUAL_BANK_TRANSFER
            && $this->status === 'pending';
    }

    public function paymentConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function domainRegistrations(): HasMany
    {
        return $this->hasMany(DomainRegistration::class);
    }
}
