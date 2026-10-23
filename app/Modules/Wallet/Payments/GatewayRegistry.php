<?php

namespace App\Modules\Wallet\Payments;

use App\Models\PayoutConnection;
use App\Models\PayoutCoverage;
use App\Models\PayoutRoute;
use App\Models\SystemSetting;
use App\Modules\Wallet\Payments\Contracts\CollectionGateway;
use App\Modules\Wallet\Payments\Contracts\GatewayDriver;
use App\Modules\Wallet\Payments\Contracts\PayoutGateway;
use App\Modules\Wallet\Payments\Exceptions\GatewayUnavailable;
use Illuminate\Contracts\Container\Container;

/**
 * Every installed payment driver, the admin-chosen checkout order and the payout routes.
 */
class GatewayRegistry
{
    public const COLLECTION_ORDER_SETTING = 'payments_collection_gateways';

    public const PAYOUT_ORDER_SETTING = 'payments_payout_gateways';

    /** @var array<string, GatewayDriver> */
    private array $instances = [];

    /** @var array<string, class-string<GatewayDriver>|GatewayDriver>|null */
    private ?array $overrides = null;

    public function __construct(
        private Container $container,
        private GatewayHealth $health,
    ) {}

    /**
     * Swap the installed drivers (tests and later drivers).
     *
     * @param  array<string, class-string<GatewayDriver>|GatewayDriver>  $drivers
     */
    public function setDrivers(array $drivers): void
    {
        $this->overrides = $drivers;
        $this->instances = [];
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->definitions());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->definitions());
    }

    public function driver(string $key): GatewayDriver
    {
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        $definition = $this->definitions()[$key] ?? null;
        if ($definition === null) {
            throw new GatewayUnavailable('Unknown payment gateway: '.$key);
        }

        return $this->instances[$key] = $definition instanceof GatewayDriver
            ? $definition
            : $this->container->make($definition);
    }

    /** @return array<string, GatewayDriver> */
    public function all(): array
    {
        $drivers = [];
        foreach ($this->keys() as $key) {
            $drivers[$key] = $this->driver($key);
        }

        return $drivers;
    }

    public function health(): GatewayHealth
    {
        return $this->health;
    }

    // ---------------------------------------------------------------- collections

    /** @return list<string> admin order, collection-capable drivers only */
    public function collectionOrder(): array
    {
        return $this->orderedKeys(
            self::COLLECTION_ORDER_SETTING,
            (array) config('payments.default_collection_order', []),
            fn (GatewayDriver $d) => $d instanceof CollectionGateway,
        );
    }

    /**
     * Gateways a creator may pick right now: configured, healthy and able to charge the site currency.
     *
     * @return array<string, CollectionGateway>
     */
    public function availableCollectionGateways(): array
    {
        $currency = strtoupper((string) config('platform.currency', 'USD'));
        $available = [];

        foreach ($this->collectionOrder() as $key) {
            $driver = $this->driver($key);
            if (! $driver instanceof CollectionGateway || ! $driver->isConfigured()) {
                continue;
            }
            if (! $this->health->isHealthy($key)) {
                continue;
            }
            if (! in_array($currency, array_map('strtoupper', $driver->currencies()), true)) {
                continue;
            }
            $available[$key] = $driver;
        }

        return $available;
    }

    public function hasCollectionGateway(): bool
    {
        return $this->availableCollectionGateways() !== [];
    }

    public function defaultCollectionKey(): ?string
    {
        return array_key_first($this->availableCollectionGateways());
    }

    /**
     * A gateway for a new checkout. Null picks the default (first available).
     */
    public function collection(?string $key = null): CollectionGateway
    {
        $available = $this->availableCollectionGateways();
        $key ??= array_key_first($available);

        if ($key === null || ! isset($available[$key])) {
            throw new GatewayUnavailable('This payment option is not available right now. Choose another one.');
        }

        return $available[$key];
    }

    /**
     * The driver that handled an existing order or deposit, for verify and webhooks,
     * whether or not it is still offered at checkout.
     */
    public function collectionDriver(?string $key): ?CollectionGateway
    {
        if ($key === null || ! $this->has($key)) {
            return null;
        }
        $driver = $this->driver($key);

        return $driver instanceof CollectionGateway ? $driver : null;
    }

    // ---------------------------------------------------------------- payouts

    /** @return list<string> */
    public function payoutOrder(): array
    {
        return $this->orderedKeys(
            self::PAYOUT_ORDER_SETTING,
            (array) config('payments.default_payout_order', []),
            fn (GatewayDriver $d) => $d instanceof PayoutGateway,
        );
    }

    public function payoutDriver(?string $key): ?PayoutGateway
    {
        if ($key === null || ! $this->has($key)) {
            return null;
        }
        $driver = $this->driver($key);

        return $driver instanceof PayoutGateway ? $driver : null;
    }

    /**
     * Route order before coverage filtering: country+method route, then country route, then default.
     *
     * @return list<string>
     */
    public function payoutRouteKeys(string $country, string $method): array
    {
        $country = strtoupper($country);

        $route = PayoutRoute::query()->where('country', $country)->where('method', $method)->first()
            ?? PayoutRoute::query()->where('country', $country)->whereNull('method')->first();

        $keys = $route ? (array) $route->gateway_keys : $this->payoutOrder();

        return array_values(array_filter(
            array_unique(array_map('strval', $keys)),
            fn (string $key) => $this->payoutDriver($key) !== null,
        ));
    }

    /**
     * Gateways that may pay this destination, in route order. A gateway is skipped when it
     * isn't configured or the route isn't verified for it in payout coverage.
     *
     * When $userId is set and that agent has an active Bachs Connect account, Bachs is included
     * even if coverage has not been synced yet — same rule as live fee quotes on withdraw.
     *
     * @return array<string, PayoutGateway>
     */
    public function payoutRoute(string $country, string $method, ?int $userId = null): array
    {
        $route = [];
        foreach ($this->payoutRouteKeys($country, $method) as $key) {
            $driver = $this->payoutDriver($key);
            if (! $driver || ! $driver->isConfigured()) {
                continue;
            }
            if (! PayoutCoverage::isUsable($key, $country, $method)) {
                continue;
            }
            $route[$key] = $driver;
        }

        if ($userId !== null && ! isset($route['bachs'])) {
            $bachs = $this->connectBachsIfReady($userId, $country, $method);
            if ($bachs !== null) {
                $route = ['bachs' => $bachs] + $route;
            }
        }

        return $route;
    }

    /**
     * Active Bachs Connect recipient may be paid before payout_coverage marks the route verified.
     */
    private function connectBachsIfReady(int $userId, string $country, string $method): ?PayoutGateway
    {
        if (config('payout_methods.countries.'.strtoupper($country).'.methods.'.$method) === null) {
            return null;
        }
        $user = \App\Models\User::query()->find($userId);
        $connection = $user
            ? app(\App\Modules\Wallet\Payments\Bachs\BachsConnectService::class)->connection($user)
            : PayoutConnection::for($userId, 'bachs');
        if (! $connection?->isActive()) {
            return null;
        }
        $driver = $this->payoutDriver('bachs');

        return $driver && $driver->isConfigured() ? $driver : null;
    }

    /**
     * Name lookup: first configured driver that supports it for this country and method,
     * whether or not it handles the payout.
     */
    public function resolverFor(string $country, string $method): ?PayoutGateway
    {
        foreach ($this->payoutOrder() as $key) {
            $driver = $this->payoutDriver($key);
            if ($driver && $driver->isConfigured() && $driver->supportsResolve(strtoupper($country), $method)) {
                return $driver;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------- settings

    /**
     * @param  list<string>  $keys
     */
    public function setCollectionOrder(array $keys): void
    {
        SystemSetting::set(self::COLLECTION_ORDER_SETTING, json_encode(array_values(array_filter(
            $keys,
            fn ($k) => $this->has((string) $k) && $this->driver((string) $k) instanceof CollectionGateway,
        ))));
    }

    /**
     * @param  list<string>  $keys
     */
    public function setPayoutOrder(array $keys): void
    {
        SystemSetting::set(self::PAYOUT_ORDER_SETTING, json_encode(array_values(array_filter(
            $keys,
            fn ($k) => $this->has((string) $k) && $this->driver((string) $k) instanceof PayoutGateway,
        ))));
    }

    /**
     * @param  list<string>  $defaults
     * @param  callable(GatewayDriver): bool  $capable
     * @return list<string>
     */
    private function orderedKeys(string $setting, array $defaults, callable $capable): array
    {
        $stored = SystemSetting::get($setting);
        $keys = is_string($stored) && $stored !== '' ? (json_decode($stored, true) ?: []) : $defaults;

        return array_values(array_filter(
            array_unique(array_map('strval', (array) $keys)),
            fn (string $key) => $this->has($key) && $capable($this->driver($key)),
        ));
    }

    /** @return array<string, class-string<GatewayDriver>|GatewayDriver> */
    private function definitions(): array
    {
        return $this->overrides ?? (array) config('payments.drivers', []);
    }
}
