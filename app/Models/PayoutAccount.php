<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayoutAccount extends Model
{
    use SoftDeletes;

    public const RESOLVED = 'resolved';

    public const PENDING_REVIEW = 'pending_review';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'country',
        'currency',
        'method',
        'bank_code',
        'bank_name',
        'details',
        'display_mask',
        'account_holder_name',
        'verification',
        'verified_at',
        'resolved_by',
        'rejected_reason',
        'provider_beneficiary_ids',
        'bounce_terms_accepted_at',
        'bounce_fee_disclosed',
        'bounce_terms_text',
        'is_primary',
        'active',
        'legacy_user_bank_account_id',
    ];

    protected $hidden = ['details'];

    protected function casts(): array
    {
        return [
            'details' => 'encrypted:array',
            'provider_beneficiary_ids' => 'array',
            'verified_at' => 'datetime',
            'bounce_terms_accepted_at' => 'datetime',
            'bounce_fee_disclosed' => 'decimal:2',
            'is_primary' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isResolved(): bool
    {
        return $this->verification === self::RESOLVED;
    }

    public function isRejected(): bool
    {
        return $this->verification === self::REJECTED;
    }

    public function methodLabel(): string
    {
        return (string) (config('payout_methods.methods.'.$this->method) ?? ucfirst(str_replace('_', ' ', (string) $this->method)));
    }

    public function countryName(): string
    {
        return (string) (config('payout_methods.countries.'.$this->country.'.name') ?? $this->country);
    }

    public function summary(): string
    {
        return trim(($this->bank_name ? $this->bank_name.' · ' : '').$this->display_mask.' ('.$this->countryName().', '.$this->methodLabel().')');
    }

    /** Disclosed bounce fee the agent accepted for this account (0 when never accepted). */
    public function acceptedBounceFee(): string
    {
        return $this->bounce_terms_accepted_at ? (string) ($this->bounce_fee_disclosed ?? '0') : '0';
    }

    /** Whether payouts to this route are currently offered (coverage verified, not disabled). */
    public function routeAvailable(): bool
    {
        return app(\App\Modules\Wallet\Services\PayoutCoverageService::class)
            ->isRouteOffered((string) $this->country, (string) $this->method);
    }
}
