<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whether a gateway may pay a country and method. Only verified_api and (unexpired)
 * verified_manual rows are usable; disabled always wins.
 */
class PayoutCoverage extends Model
{
    public const VERIFIED_API = 'verified_api';

    public const VERIFIED_MANUAL = 'verified_manual';

    public const UNAVAILABLE = 'unavailable';

    public const DISABLED = 'disabled';

    protected $table = 'payout_coverage';

    protected $fillable = [
        'gateway_key',
        'country',
        'method',
        'currency',
        'resolve_supported',
        'provider_min',
        'provider_max',
        'state',
        'verified_at',
        'verified_by',
        'test_payout_reference',
        'manual_expires_at',
        'note',
        'last_error',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'resolve_supported' => 'boolean',
            'provider_min' => 'decimal:2',
            'provider_max' => 'decimal:2',
            'verified_at' => 'datetime',
            'manual_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isUsableState(): bool
    {
        if ($this->state === self::VERIFIED_API) {
            return true;
        }

        return $this->state === self::VERIFIED_MANUAL
            && ($this->manual_expires_at === null || $this->manual_expires_at->isFuture());
    }

    public static function isUsable(string $gatewayKey, string $country, string $method): bool
    {
        $row = static::query()
            ->where('gateway_key', $gatewayKey)
            ->where('country', strtoupper($country))
            ->where('method', $method)
            ->first();

        return $row?->isUsableState() ?? false;
    }

    /**
     * Country + method pairs with at least one usable gateway row.
     *
     * @return array<string, list<string>> country => methods
     */
    public static function usableRoutes(): array
    {
        $routes = [];
        static::query()
            ->whereIn('state', [self::VERIFIED_API, self::VERIFIED_MANUAL])
            ->get()
            ->filter(fn (self $row) => $row->isUsableState())
            ->each(function (self $row) use (&$routes) {
                $routes[$row->country][] = $row->method;
            });

        return array_map(fn ($methods) => array_values(array_unique($methods)), $routes);
    }

    /** Highest provider minimum across usable gateways for a route. */
    public static function providerMinimum(string $country, string $method, ?string $gatewayKey = null): ?string
    {
        $query = static::query()
            ->where('country', strtoupper($country))
            ->where('method', $method)
            ->whereIn('state', [self::VERIFIED_API, self::VERIFIED_MANUAL]);
        if ($gatewayKey) {
            $query->where('gateway_key', $gatewayKey);
        }
        $min = $query->max('provider_min');

        return $min !== null ? (string) $min : null;
    }
}
