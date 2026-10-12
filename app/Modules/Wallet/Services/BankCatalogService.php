<?php

namespace App\Modules\Wallet\Services;

use App\Modules\Wallet\Payments\GatewayRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * Bank and mobile money network lists per country, from the first gateway on the payout route.
 */
class BankCatalogService
{
    public function __construct(
        private GatewayRegistry $registry,
    ) {}

    /**
     * @return list<array{name: string, code: string}>
     */
    public function options(string $country, string $method = 'bank_account'): array
    {
        $country = strtoupper($country);
        $driver = array_values($this->registry->payoutRoute($country, $method))[0]
            ?? $this->registry->resolverFor($country, $method);
        if (! $driver) {
            return [];
        }

        $banks = Cache::remember(
            'payout_banks:'.$driver->key().':'.$country.':'.$method,
            now()->addHours(6),
            function () use ($driver, $country, $method) {
                try {
                    return $driver->listBanks($country, $method);
                } catch (\Throwable) {
                    return null;
                }
            },
        );
        if ($banks === null) {
            Cache::forget('payout_banks:'.$driver->key().':'.$country.':'.$method);

            return [];
        }

        return $country === 'NG' && $method === 'bank_account'
            ? $this->filterAllowed($banks)
            : $this->clean($banks);
    }

    /** @return array{name: string, code: string}|null */
    public function find(string $country, string $method, string $code): ?array
    {
        foreach ($this->options($country, $method) as $bank) {
            if ((string) $bank['code'] === $code) {
                return $bank;
            }
        }

        return null;
    }

    /**
     * @param  list<array{name: string, code: string}>  $banks
     * @return list<array{name: string, code: string}>
     */
    public function filterAllowed(array $banks): array
    {
        $allow = collect(config('wallet.allowed_bank_patterns', []))
            ->map(fn ($p) => mb_strtolower(trim((string) $p)))
            ->filter()
            ->values()
            ->all();

        $deny = collect(config('wallet.excluded_bank_patterns', []))
            ->map(fn ($p) => mb_strtolower(trim((string) $p)))
            ->filter()
            ->values()
            ->all();

        return collect($this->clean($banks))
            ->filter(function (array $bank) use ($allow, $deny) {
                $name = mb_strtolower($bank['name']);
                foreach ($deny as $pattern) {
                    if (str_contains($name, $pattern)) {
                        return false;
                    }
                }
                if ($allow === []) {
                    return true;
                }
                foreach ($allow as $pattern) {
                    if (str_contains($name, $pattern)) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{name: string, code: string}>  $banks
     * @return list<array{name: string, code: string}>
     */
    private function clean(array $banks): array
    {
        return collect($banks)
            ->map(fn ($b) => ['name' => trim((string) ($b['name'] ?? '')), 'code' => trim((string) ($b['code'] ?? ''))])
            ->filter(fn ($b) => $b['name'] !== '' && $b['code'] !== '')
            ->unique('code')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
