<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Withdrawal extends Model
{
    use BelongsToActiveUser;

    protected $guarded = ['id'];

    public const OPEN_STATUSES = ['pending', 'approved', 'processing'];

    public const INTERNAL_AWAITING_PROVIDER_AUTH = 'awaiting_provider_authorization';

    /** Re-quoted fee is above tolerance: the agent must accept the new fee or cancel. */
    public const INTERNAL_AWAITING_AGENT_CONFIRMATION = 'awaiting_agent_confirmation';

    /** A provider confirmed a recoverable or unclassified failure: an admin retries elsewhere or refunds. */
    public const INTERNAL_NEEDS_ADMIN_DECISION = 'needs_admin_decision';

    public const OPEN_INTERNAL = [
        'pending_review',
        'approved',
        'processing',
        self::INTERNAL_AWAITING_PROVIDER_AUTH,
        self::INTERNAL_AWAITING_AGENT_CONFIRMATION,
        self::INTERNAL_NEEDS_ADMIN_DECISION,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'provider_fee' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'absorbed_fee' => 'decimal:2',
            'bounce_fee_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'confirmation_expires_at' => 'datetime',
            'bounce_waived_at' => 'datetime',
            'account_number' => 'encrypted',
            'destination_details' => 'encrypted:array',
            'quote_snapshot' => 'array',
            'requote_snapshot' => 'array',
            'provider_meta' => 'array',
            'provider_auth_attempts' => 'integer',
        ];
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class)->withTrashed();
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(WithdrawalPayoutAttempt::class)->orderBy('attempt_no');
    }

    public function latestAttempt(): HasOne
    {
        return $this->hasOne(WithdrawalPayoutAttempt::class)->latestOfMany('attempt_no');
    }

    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(PayoutFeeRule::class);
    }

    /** Amount sent to the gateway: requested amount minus the agreed fee. */
    public function netPayoutAmount(): string
    {
        if ($this->net_amount !== null) {
            return (string) $this->net_amount;
        }

        return bcsub((string) $this->amount, (string) ($this->fee_amount ?? '0'), 2);
    }

    public function awaitingAgentConfirmation(): bool
    {
        return $this->internal_status === self::INTERNAL_AWAITING_AGENT_CONFIRMATION;
    }

    public function needsAdminDecision(): bool
    {
        return $this->internal_status === self::INTERNAL_NEEDS_ADMIN_DECISION;
    }

    public function destinationLabel(): string
    {
        $country = (string) ($this->destination_country ?: 'NG');
        $method = (string) ($this->method ?: 'bank_account');

        return (string) (config('payout_methods.countries.'.$country.'.name') ?? $country)
            .' · '.(string) (config('payout_methods.methods.'.$method) ?? str_replace('_', ' ', $method));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(UserBankAccount::class, 'user_bank_account_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function timelineEvents(): MorphMany
    {
        return $this->morphMany(PaymentTimelineEvent::class, 'subject')->orderBy('occurred_at');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true)
            || in_array((string) $this->internal_status, self::OPEN_INTERNAL, true);
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing' || $this->internal_status === 'processing';
    }

    public function needsProviderAuthorization(): bool
    {
        if ($this->isProviderAuthorizationExpired()) {
            return false;
        }

        if ($this->internal_status === self::INTERNAL_AWAITING_PROVIDER_AUTH) {
            return true;
        }

        if ($this->isTerminal()) {
            return false;
        }

        return strtoupper((string) $this->provider_status) === 'PENDING_AUTHORIZATION';
    }

    public function isProviderAuthorizationExpired(): bool
    {
        return strtoupper((string) $this->provider_status) === 'EXPIRED';
    }

    public function canBeRejectedByAdmin(): bool
    {
        if ($this->isTerminal()) {
            return false;
        }

        if (in_array($this->internal_status, [
            self::INTERNAL_AWAITING_PROVIDER_AUTH,
            self::INTERNAL_AWAITING_AGENT_CONFIRMATION,
            self::INTERNAL_NEEDS_ADMIN_DECISION,
        ], true)) {
            return true;
        }

        return in_array($this->status, ['pending', 'approved'], true)
            || in_array($this->internal_status, ['pending_review', 'approved'], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'rejected', 'failed'], true)
            || in_array($this->internal_status, ['completed', 'failed'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function providerInitiateSnapshot(): array
    {
        return (array) (($this->provider_meta ?? [])['initiate'] ?? []);
    }

    public function accountNumber(): string
    {
        return (string) ($this->account_number
            ?: ($this->destination_details['fields']['account_number']
                ?? $this->destination_details['fields']['iban']
                ?? $this->destination_details['fields']['phone_number']
                ?? ''));
    }

    public function maskedAccountNumber(): string
    {
        $num = $this->accountNumber();
        if (strlen($num) <= 4) {
            return str_repeat('*', strlen($num));
        }

        return str_repeat('*', max(0, strlen($num) - 4)).substr($num, -4);
    }
}
