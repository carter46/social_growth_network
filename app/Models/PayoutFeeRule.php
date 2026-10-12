<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Optional platform fee, verified provider fee entry and per-route limits.
 * Null country / method / gateway means "any".
 */
class PayoutFeeRule extends Model
{
    public const SOURCE_LIVE_QUOTE = 'live_quote';

    public const SOURCE_PROVIDER_INVOICE = 'provider_invoice';

    public const SOURCE_TEST_PAYOUT = 'test_payout';

    protected $fillable = [
        'country',
        'method',
        'gateway_key',
        'flat_fee',
        'percent_fee',
        'min_fee',
        'max_fee',
        'verified_provider_fee_flat',
        'verified_provider_fee_percent',
        'verified_source',
        'verified_reference',
        'verified_at',
        'verified_by',
        'min_withdrawal',
        'max_withdrawal',
        'active',
        'drift_note',
        'drift_flagged_at',
    ];

    protected function casts(): array
    {
        return [
            'flat_fee' => 'decimal:2',
            'percent_fee' => 'decimal:3',
            'min_fee' => 'decimal:2',
            'max_fee' => 'decimal:2',
            'verified_provider_fee_flat' => 'decimal:2',
            'verified_provider_fee_percent' => 'decimal:3',
            'verified_at' => 'datetime',
            'min_withdrawal' => 'decimal:2',
            'max_withdrawal' => 'decimal:2',
            'active' => 'boolean',
            'drift_flagged_at' => 'datetime',
        ];
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function hasVerifiedProviderFee(): bool
    {
        return $this->verified_at !== null
            && ($this->verified_provider_fee_flat !== null || $this->verified_provider_fee_percent !== null);
    }

    public function verifiedFeeIsStale(): bool
    {
        if (! $this->hasVerifiedProviderFee()) {
            return true;
        }

        return $this->verified_at->lt(now()->subDays((int) config('payments.payout.verified_fee_max_age_days', 90)));
    }

    /**
     * Most specific active rule for a route: exact country+method+gateway first, then wider rules.
     */
    public static function bestMatch(string $country, string $method, ?string $gatewayKey = null, ?callable $filter = null): ?self
    {
        return static::query()
            ->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('country')->orWhere('country', strtoupper($country)))
            ->where(fn (Builder $q) => $q->whereNull('method')->orWhere('method', $method))
            ->where(fn (Builder $q) => $gatewayKey
                ? $q->whereNull('gateway_key')->orWhere('gateway_key', $gatewayKey)
                : $q->whereNull('gateway_key'))
            ->get()
            ->filter(fn (self $rule) => $filter === null || $filter($rule))
            ->sortByDesc(fn (self $rule) => ($rule->country ? 4 : 0) + ($rule->method ? 2 : 0) + ($rule->gateway_key ? 1 : 0))
            ->first();
    }

    public function routeLabel(): string
    {
        return ($this->country ?: 'All countries').' · '.($this->method ? str_replace('_', ' ', $this->method) : 'all methods')
            .($this->gateway_key ? ' · '.$this->gateway_key : '');
    }
}
