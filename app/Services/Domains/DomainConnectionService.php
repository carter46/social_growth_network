<?php

namespace App\Services\Domains;

use App\Models\DomainConnection;
use App\Models\DomainRegistration;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Domains\DomainFqdn;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DomainConnectionService
{
    public function __construct(
        private DomainDnsLookupService $dns,
    ) {}

    /**
     * Safe customer-facing scan for connect-existing checkout.
     *
     * @return array{
     *     fqdn: string,
     *     registered: bool,
     *     status: string,
     *     nameservers: list<string>,
     *     required_nameservers: list<string>,
     *     already_connected: bool,
     *     message: string|null
     * }
     */
    public function scanForUser(User $user, string $input): array
    {
        $this->dns->assertPlatformNameserversConfigured();

        try {
            $lookup = $this->dns->lookup($input);
        } catch (InvalidArgumentException $e) {
            return [
                'fqdn' => '',
                'registered' => false,
                'status' => 'invalid',
                'nameservers' => [],
                'required_nameservers' => $this->dns->platformNameservers(),
                'already_connected' => false,
                'message' => $e->getMessage(),
            ];
        }

        $alreadyOther = $this->isClaimedByAnotherUser($lookup['fqdn'], $user->id);
        $alreadyOwn = $this->isClaimedByUser($lookup['fqdn'], $user->id);

        if (! $lookup['registered']) {
            return [
                ...$lookup,
                'required_nameservers' => $this->dns->platformNameservers(),
                'already_connected' => $alreadyOther || $alreadyOwn,
                'message' => 'We could not find nameservers for this domain. Confirm it is registered and try again.',
            ];
        }

        if ($alreadyOther) {
            return [
                ...$lookup,
                'required_nameservers' => $this->dns->platformNameservers(),
                'already_connected' => true,
                'message' => 'This domain is already connected to another account on '.config('app.name', 'this platform').'.',
            ];
        }

        if ($alreadyOwn) {
            return [
                ...$lookup,
                'required_nameservers' => $this->dns->platformNameservers(),
                'already_connected' => true,
                'message' => 'This domain is already connected on your account.',
            ];
        }

        return [
            ...$lookup,
            'required_nameservers' => $this->dns->platformNameservers(),
            'already_connected' => false,
            'message' => null,
        ];
    }

    public function isClaimedByAnotherUser(string $fqdn, int $userId): bool
    {
        $fqdn = DomainFqdn::normalizeFqdn($fqdn, apexOnly: false);

        $connected = DomainConnection::query()
            ->activeClaim()
            ->where('fqdn', $fqdn)
            ->where('user_id', '!=', $userId)
            ->exists();

        if ($connected) {
            return true;
        }

        return DomainRegistration::query()
            ->where('fqdn', $fqdn)
            ->where('status', DomainRegistration::STATUS_REGISTERED)
            ->whereHas('order', fn ($q) => $q->where('user_id', '!=', $userId)->where('status', 'paid'))
            ->exists();
    }

    public function isClaimedByUser(string $fqdn, int $userId, ?int $exceptOrderItemId = null): bool
    {
        $fqdn = DomainFqdn::normalizeFqdn($fqdn, apexOnly: false);

        $query = DomainConnection::query()
            ->activeClaim()
            ->where('fqdn', $fqdn)
            ->where('user_id', $userId);

        if ($exceptOrderItemId !== null) {
            $query->where('order_item_id', '!=', $exceptOrderItemId);
        }

        return $query->exists();
    }

    public function isActivelyClaimed(string $fqdn, ?int $exceptOrderItemId = null): bool
    {
        $fqdn = DomainFqdn::normalizeFqdn($fqdn, apexOnly: false);

        $query = DomainConnection::query()
            ->activeClaim()
            ->where('fqdn', $fqdn);

        if ($exceptOrderItemId !== null) {
            $query->where('order_item_id', '!=', $exceptOrderItemId);
        }

        if ($query->exists()) {
            return true;
        }

        return DomainRegistration::query()
            ->where('fqdn', $fqdn)
            ->where('status', DomainRegistration::STATUS_REGISTERED)
            ->whereHas('order', fn ($q) => $q->where('status', 'paid'))
            ->exists();
    }

    /**
     * @param  list<string>  $nameserversAtScan
     */
    public function createFromOrderItem(
        User $user,
        Order $order,
        OrderItem $item,
        string $fqdn,
        array $nameserversAtScan,
        bool $acknowledged,
    ): DomainConnection {
        if ($order->status !== 'paid') {
            throw new InvalidArgumentException('Domain connections can only be created for paid orders.');
        }

        $fqdn = DomainFqdn::normalizeFqdn($fqdn, apexOnly: false);
        $required = $this->dns->platformNameservers();

        if (count($required) < 2) {
            throw new InvalidArgumentException('Platform nameservers are not configured.');
        }

        try {
            return DB::transaction(function () use ($user, $order, $item, $fqdn, $nameserversAtScan, $acknowledged, $required) {
                // Serialize claim checks for this FQDN across concurrent checkouts.
                DomainConnection::query()
                    ->activeClaim()
                    ->where('fqdn', $fqdn)
                    ->lockForUpdate()
                    ->get();

                if ($this->isActivelyClaimed($fqdn, $item->id)) {
                    if ($this->isClaimedByAnotherUser($fqdn, $user->id)) {
                        throw new InvalidArgumentException('This domain is already connected to another account.');
                    }

                    throw new InvalidArgumentException('This domain is already connected on your account.');
                }

                return DomainConnection::query()->updateOrCreate(
                    ['order_item_id' => $item->id],
                    [
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'fqdn' => $fqdn,
                        'claim_key' => $fqdn,
                        'nameservers_at_scan' => $this->dns->normalizeList($nameserversAtScan),
                        'nameservers_last_seen' => $this->dns->normalizeList($nameserversAtScan),
                        'required_nameservers' => $required,
                        'verification_status' => DomainConnection::STATUS_PENDING,
                        'acknowledged_at' => $acknowledged ? now() : null,
                        'verified_at' => null,
                        'last_checked_at' => now(),
                    ],
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('This domain is already connected on '.config('app.name', 'this platform').'.');
        }
    }

    /**
     * @return array{ok: bool, message: string, connection: DomainConnection}
     */
    public function checkStatus(DomainConnection $connection): array
    {
        $this->dns->assertPlatformNameserversConfigured();

        $lookup = $this->dns->lookup($connection->fqdn);
        $detected = $lookup['nameservers'];
        $required = $connection->requiredNameserverList() ?: $this->dns->platformNameservers();
        $matched = $lookup['registered'] && $this->dns->matchesPlatformDefaults($detected, $required);

        DB::transaction(function () use ($connection, $detected, $matched) {
            $connection->refresh();
            $connection->update([
                'nameservers_last_seen' => $detected,
                'last_checked_at' => now(),
                'verification_status' => $matched
                    ? DomainConnection::STATUS_VERIFIED
                    : DomainConnection::STATUS_PENDING,
                'claim_key' => $connection->fqdn,
                'verified_at' => $matched ? now() : null,
            ]);
        });

        $connection = $connection->fresh();

        if ($matched) {
            return [
                'ok' => true,
                'message' => 'Nameservers verified. This domain is correctly configured.',
                'connection' => $connection,
            ];
        }

        return [
            'ok' => false,
            'message' => 'We can still see different nameservers. DNS changes can take time to propagate. Please update the nameservers and try again.',
            'connection' => $connection,
        ];
    }

    /**
     * Admin override: mark a domain connection as verified without DNS check.
     */
    public function approveManually(DomainConnection $connection): DomainConnection
    {
        DB::transaction(function () use ($connection) {
            $connection->refresh();
            $connection->update([
                'verification_status' => DomainConnection::STATUS_VERIFIED,
                'verified_at' => now(),
                'last_checked_at' => now(),
            ]);
        });

        return $connection->fresh();
    }

    public function attachUserTool(OrderItem $item, int $userToolId): void
    {
        DomainConnection::query()
            ->where('order_item_id', $item->id)
            ->whereNull('user_tool_id')
            ->update(['user_tool_id' => $userToolId]);
    }
}
