<?php

namespace App\Modules\Wallet\Payments;

use Illuminate\Support\Facades\Cache;

/**
 * Short-window circuit breaker. After repeated connection or 5xx errors a driver is
 * skipped for new checkouts until the cooldown passes.
 */
class GatewayHealth
{
    public function recordFailure(string $key): void
    {
        $window = (int) config('payments.health.window_minutes', 10);
        $threshold = (int) config('payments.health.failure_threshold', 3);
        $counterKey = $this->counterKey($key);

        Cache::add($counterKey, 0, now()->addMinutes($window));
        $failures = (int) Cache::increment($counterKey);

        if ($failures >= $threshold) {
            Cache::put(
                $this->downKey($key),
                now()->addMinutes((int) config('payments.health.cooldown_minutes', 15))->timestamp,
                now()->addMinutes((int) config('payments.health.cooldown_minutes', 15))
            );
        }
    }

    public function recordSuccess(string $key): void
    {
        Cache::forget($this->counterKey($key));
        Cache::forget($this->downKey($key));
    }

    public function isHealthy(string $key): bool
    {
        $until = Cache::get($this->downKey($key));

        return ! $until || (int) $until <= now()->timestamp;
    }

    /** Unix time the driver is skipped until, or null when healthy. */
    public function downUntil(string $key): ?int
    {
        $until = Cache::get($this->downKey($key));

        return $until && (int) $until > now()->timestamp ? (int) $until : null;
    }

    private function counterKey(string $key): string
    {
        return 'payments.health.failures.'.$key;
    }

    private function downKey(string $key): string
    {
        return 'payments.health.down_until.'.$key;
    }
}
