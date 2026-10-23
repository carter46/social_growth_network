<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One provider-side connected account per user (Bachs recipient account). Readiness is
 * derived only from the provider's account object and capability statuses.
 */
class PayoutConnection extends Model
{
    public const NOT_STARTED = 'not_started';

    public const REQUIREMENTS_DUE = 'requirements_due';

    public const UNDER_REVIEW = 'under_review';

    public const RESTRICTED = 'restricted';

    public const ACTIVE = 'active';

    public const REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::NOT_STARTED => 'Not connected',
        self::REQUIREMENTS_DUE => 'Setup incomplete',
        self::UNDER_REVIEW => 'Under review',
        self::RESTRICTED => 'Restricted',
        self::ACTIVE => 'Connected',
        self::REJECTED => 'Rejected',
    ];

    /** Capabilities that must both be active before money can move to the account. */
    public const REQUIRED_CAPABILITIES = ['transfers', 'payouts'];

    protected $fillable = [
        'user_id',
        'provider',
        'environment',
        'account_id',
        'status',
        'capabilities',
        'requirements',
        'status_reason',
        'last_event_at',
        'last_synced_at',
    ];

    /** Rows created before environment was tracked are treated as sandbox cutover leftovers. */
    public function recordedEnvironment(): string
    {
        return ($this->environment ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    public function matchesEnvironment(string $environment): bool
    {
        return $this->recordedEnvironment() === (($environment === 'live') ? 'live' : 'sandbox');
    }

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'requirements' => 'array',
            'last_event_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function for(int $userId, string $provider): ?self
    {
        return self::query()->where('user_id', $userId)->where('provider', $provider)->first();
    }

    public function capabilitiesActive(): bool
    {
        $caps = (array) ($this->capabilities ?? []);
        foreach (self::REQUIRED_CAPABILITIES as $name) {
            if (strtolower((string) ($caps[$name] ?? '')) !== 'active') {
                return false;
            }
        }

        return true;
    }

    /** Money may move to the account. The destination's own is_usable is checked per payout. */
    public function isActive(): bool
    {
        return filled($this->account_id) && $this->status === self::ACTIVE && $this->capabilitiesActive();
    }

    public function needsSetup(): bool
    {
        return in_array($this->status, [self::NOT_STARTED, self::REQUIREMENTS_DUE], true);
    }

    public function label(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    /** @return list<string> */
    public function dueRequirements(): array
    {
        $req = (array) ($this->requirements ?? []);

        return array_values(array_unique(array_map('strval', array_merge(
            (array) ($req['past_due'] ?? []),
            (array) ($req['currently_due'] ?? []),
        ))));
    }
}
