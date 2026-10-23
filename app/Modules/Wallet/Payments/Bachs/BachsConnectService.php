<?php

namespace App\Modules\Wallet\Payments\Bachs;

use App\Models\PayoutAccount;
use App\Models\PayoutConnection;
use App\Models\User;
use App\Modules\Wallet\Payments\Data\PaymentEvent;
use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Modules\Wallet\Services\PayoutCoverageService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/**
 * One Bachs recipient account per agent. Primary setup is on-site API onboarding
 * (docs.bachs.io/connect/guides/api-onboarding). Readiness comes from the account object
 * and capability statuses (API sync or Connect webhooks).
 */
class BachsConnectService
{
    public const PROVIDER = BachsDriver::KEY;

    /** Link errors the agent cannot fix by retrying. */
    public const LINK_ERRORS = [
        'CONNECTED_ACCOUNT_NOT_ELIGIBLE_FOR_LINK' => 'Your payout account cannot be set up online right now. Our team has been told and will contact you.',
        'CONNECTED_ACCOUNT_PLATFORM_SUSPENDED' => 'Payout account setup is paused for now. Our team has been told and will contact you.',
    ];

    public function __construct(
        private GatewayRegistry $registry,
    ) {}

    /** Bachs is installed, enabled and configured, so the connect flow can be offered. */
    public function available(): bool
    {
        if (! $this->registry->has(self::PROVIDER)) {
            return false;
        }
        $driver = $this->registry->driver(self::PROVIDER);

        return $driver instanceof BachsDriver && $driver->isConfigured();
    }

    public function driver(): BachsDriver
    {
        $driver = $this->registry->driver(self::PROVIDER);
        if (! $driver instanceof BachsDriver) {
            throw new GatewayException('Bachs is not available.', 'UNAUTHORIZED', 401);
        }

        return $driver;
    }

    public function currentEnvironment(): string
    {
        return $this->available() ? $this->driver()->environment() : 'sandbox';
    }

    public function connection(User $user): ?PayoutConnection
    {
        $connection = PayoutConnection::for((int) $user->id, self::PROVIDER);
        if (! $connection) {
            return null;
        }

        if ($this->available() && filled($connection->account_id) && ! $connection->matchesEnvironment($this->currentEnvironment())) {
            $this->invalidate($connection, 'Provider environment changed ('.$connection->recordedEnvironment().' → '.$this->currentEnvironment().').');
            $connection = $connection->fresh() ?? $connection;
        }

        return $connection;
    }

    /**
     * Clear a local Connect row so the agent can onboard again on the current Bachs environment.
     * Keeps profile data and local bank accounts; strips stale Bachs destination ids.
     */
    public function invalidate(PayoutConnection $connection, string $reason = 'Connect account reset.'): void
    {
        if ((string) $connection->provider !== self::PROVIDER) {
            return;
        }

        $previousAccountId = (string) ($connection->account_id ?? '');
        $userId = (int) $connection->user_id;

        DB::transaction(function () use ($connection, $reason, $previousAccountId, $userId) {
            $locked = PayoutConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'account_id' => null,
                'environment' => null,
                'status' => PayoutConnection::NOT_STARTED,
                'capabilities' => null,
                'requirements' => null,
                'status_reason' => mb_substr($reason, 0, 255),
                'last_event_at' => null,
                'last_synced_at' => null,
            ])->save();

            $this->clearBachsBeneficiaryIds($userId);
        });

        Log::info('bachs.connect.invalidated', [
            'user_id' => $userId,
            'previous_account_id' => $previousAccountId !== '' ? $previousAccountId : null,
            'reason' => $reason,
        ]);
    }

    /** Reset every Bachs Connect row that belongs to a different environment than the one configured now. */
    public function invalidateMismatched(?string $currentEnvironment = null): int
    {
        $current = ($currentEnvironment ?? $this->currentEnvironment()) === 'live' ? 'live' : 'sandbox';
        $count = 0;

        PayoutConnection::query()
            ->where('provider', self::PROVIDER)
            ->whereNotNull('account_id')
            ->orderBy('id')
            ->each(function (PayoutConnection $connection) use ($current, &$count) {
                if ($connection->matchesEnvironment($current)) {
                    return;
                }
                $this->invalidate(
                    $connection,
                    'Provider switched to '.$current.'; previous '.$connection->recordedEnvironment().' Connect account cleared.'
                );
                $count++;
            });

        return $count;
    }

    /** @param  positive-int  $userId */
    private function clearBachsBeneficiaryIds(int $userId): void
    {
        PayoutAccount::query()
            ->where('user_id', $userId)
            ->where('active', true)
            ->orderBy('id')
            ->each(function (PayoutAccount $account) {
                $ids = (array) ($account->provider_beneficiary_ids ?? []);
                if (! array_key_exists(self::PROVIDER, $ids)) {
                    return;
                }
                unset($ids[self::PROVIDER]);
                $account->provider_beneficiary_ids = $ids === [] ? null : $ids;
                $account->save();
            });
    }

    /** Create (once) the agent's recipient account and the local row that tracks it. */
    public function ensureAccount(User $user, ?string $country = null): PayoutConnection
    {
        $country = $country !== null && preg_match('/^[A-Za-z]{2}$/', $country)
            ? strtoupper($country)
            : $this->countryFor($user);

        $connection = PayoutConnection::query()->firstOrCreate(
            ['user_id' => $user->id, 'provider' => self::PROVIDER],
            ['status' => PayoutConnection::NOT_STARTED],
        );

        if (filled($connection->account_id) && ! $connection->matchesEnvironment($this->currentEnvironment())) {
            $this->invalidate($connection, 'Provider environment changed; creating a new Connect account.');
            $connection = $connection->fresh() ?? $connection;
        }

        if (filled($connection->account_id)) {
            if ($country !== null) {
                try {
                    $this->driver()->updateAccount((string) $connection->account_id, [
                        'country' => $country,
                        'entity_type' => 'individual',
                    ]);
                } catch (GatewayException $e) {
                    Log::info('bachs.connect.country_update_skipped', [
                        'user_id' => $user->id,
                        'code' => $e->providerCode,
                    ]);
                }
            }

            return $connection->fresh() ?? $connection;
        }

        $account = $this->driver()->createRecipientAccount((int) $user->id, (string) $user->email, (string) $user->name, $country);
        $accountId = (string) $account['id'];

        return DB::transaction(function () use ($connection, $accountId, $account) {
            $locked = PayoutConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            if (blank($locked->account_id)) {
                $locked->account_id = $accountId;
                $locked->environment = $this->currentEnvironment();
                $this->applyState($locked, $account, BachsDriver::normalizeCapabilities($account));
                $locked->save();
            }

            return $locked;
        });
    }

    /**
     * Countries agents can pick for on-site setup. Prefers coverage; falls back to Bachs
     * documented payout routes in config when coverage has not been synced yet.
     *
     * @return array<string, array{name: string, currency: string, methods: array<string, string>}>
     */
    public function offerableCountries(): array
    {
        $offered = app(PayoutCoverageService::class)->offeredCountries();
        if ($offered !== []) {
            return $this->onlyInlineDestinationCountries($offered);
        }

        $driver = $this->driver();
        $fallback = [];
        try {
            $routes = $driver->coverage();
        } catch (GatewayException) {
            $routes = [];
            foreach ((array) config('payout_methods.countries', []) as $code => $meta) {
                foreach (array_keys((array) ($meta['methods'] ?? [])) as $method) {
                    if ($driver->usesInlinePayoutDestination((string) $code, (string) $method)) {
                        $routes[] = new \App\Modules\Wallet\Payments\Data\CoverageRoute(
                            strtoupper((string) $code),
                            (string) $method,
                            (string) ($meta['currency'] ?? ''),
                        );
                    }
                }
            }
        }
        foreach ($routes as $route) {
            $code = strtoupper($route->country);
            if (! $driver->usesInlinePayoutDestination($code, $route->method)) {
                continue;
            }
            $meta = config('payout_methods.countries.'.$code);
            if (! is_array($meta)) {
                continue;
            }
            $fallback[$code] ??= [
                'name' => (string) $meta['name'],
                'currency' => (string) $meta['currency'],
                'methods' => [],
            ];
            $fallback[$code]['methods'][$route->method] = (string) config('payout_methods.methods.'.$route->method, $route->method);
        }
        uasort($fallback, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $fallback;
    }

    /**
     * International routing schemas cannot use inline payout_destination (Bachs docs).
     * Those agents complete identity on the hosted partner flow, then add a bank on-site.
     */
    public function prefersHostedOnboarding(?string $country): bool
    {
        if ($country === null || ! preg_match('/^[A-Za-z]{2}$/', $country)) {
            return false;
        }
        $country = strtoupper($country);
        $methods = array_keys((array) config('payout_methods.countries.'.$country.'.methods', []));
        if ($methods === []) {
            return false;
        }
        foreach ($methods as $method) {
            if (! $this->driver()->usesInlinePayoutDestination($country, (string) $method)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array{name: string, currency: string, methods: array<string, string>}>  $countries
     * @return array<string, array{name: string, currency: string, methods: array<string, string>}>
     */
    private function onlyInlineDestinationCountries(array $countries): array
    {
        $driver = $this->driver();
        $filtered = [];
        foreach ($countries as $code => $row) {
            $methods = [];
            foreach (array_keys((array) ($row['methods'] ?? [])) as $method) {
                if ($driver->usesInlinePayoutDestination((string) $code, (string) $method)) {
                    $methods[$method] = $row['methods'][$method];
                }
            }
            if ($methods !== []) {
                $filtered[$code] = $row;
                $filtered[$code]['methods'] = $methods;
            }
        }

        return $filtered;
    }

    /**
     * On-site recipient onboarding: individual only, prefilled identity + bank destination.
     * Does not redirect to Bachs hosted UI.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws GatewayException
     */
    public function submitRecipientOnboarding(User $user, array $input, ?UploadedFile $idDocument = null): PayoutConnection
    {
        $country = strtoupper((string) ($input['country'] ?? ''));
        $currency = (string) config('payout_methods.countries.'.$country.'.currency', '');
        if ($currency === '') {
            throw ValidationException::withMessages(['country' => __('Choose a supported payout country.')]);
        }

        if (filled($user->country) === false || strtoupper((string) $user->country) !== $country) {
            $user->forceFill(['country' => $country])->save();
        }
        if (filled($input['phone'] ?? null) && blank($user->phone)) {
            $user->forceFill(['phone' => (string) $input['phone']])->save();
        }

        $connection = $this->ensureAccount($user, $country);
        $accountId = (string) $connection->account_id;
        $driver = $this->driver();

        $phone = (string) ($input['phone'] ?? $user->phone ?? '');
        if ($phone !== '' && ! PhoneNumber::isE164($phone)) {
            throw ValidationException::withMessages([
                'phone' => __('Enter a valid phone number for your country.'),
            ]);
        }

        $person = [
            'first_name' => (string) $input['first_name'],
            'last_name' => (string) $input['last_name'],
            'dob' => (string) $input['dob'],
            'email' => (string) ($input['email'] ?? $user->email),
            'phone' => $phone,
            'relationship' => ['representative' => true, 'owner' => true, 'percent_ownership' => 100],
            'address' => array_filter([
                'line1' => (string) ($input['address_line1'] ?? $user->address_line1 ?? ''),
                'city' => (string) ($input['address_city'] ?? $user->address_city ?? ''),
                'state' => (string) ($input['address_state'] ?? $user->address_state ?? ''),
                'postal_code' => (string) ($input['address_postal_code'] ?? $user->address_postal_code ?? ''),
                'country' => $country,
            ], fn ($v) => $v !== ''),
        ];
        if ($person['phone'] === '') {
            unset($person['phone']);
        }
        if (count($person['address']) < 4) {
            unset($person['address']);
        }

        // Bachs id schemes (docs): passport, id_card, drivers_license, residence_permit, nin, bvn.
        $idType = strtolower((string) ($input['id_type'] ?? ''));
        $idValue = trim((string) ($input['id_number'] ?? ''));
        $allowedIdTypes = ['passport', 'id_card', 'drivers_license', 'residence_permit', 'nin', 'bvn'];
        if ($idValue !== '' && in_array($idType, $allowedIdTypes, true)) {
            $person['id_numbers'] = [[
                'type' => $idType,
                'value' => $idValue,
                'issuing_country' => $country,
            ]];
        }

        $existing = collect($driver->listPersons($accountId))
            ->first(fn ($row) => (bool) data_get($row, 'relationship.representative'));

        try {
            if (is_array($existing) && filled($existing['id'] ?? null)) {
                $personRow = $driver->updatePerson($accountId, (string) $existing['id'], $person);
            } else {
                $personRow = $driver->createPerson($accountId, $person);
            }
        } catch (GatewayException $e) {
            throw ValidationException::withMessages([
                'identity' => $e->getMessage() ?: __('We could not save your identity details. Check the fields and try again.'),
            ]);
        }

        $personId = (string) ($personRow['id'] ?? $existing['id'] ?? '');
        if ($personId !== '' && $idDocument) {
            try {
                $upload = $driver->uploadIdentityDocument($idDocument, $idDocument->getClientOriginalName());
                $uploadId = (string) ($upload['upload_id'] ?? $upload['id'] ?? '');
                if ($uploadId !== '') {
                    $driver->attachPersonDocument($accountId, $personId, $uploadId);
                }
            } catch (GatewayException $e) {
                throw ValidationException::withMessages([
                    'id_document' => $e->getMessage() ?: __('We could not upload your ID. Try a clearer image (JPG or PNG).'),
                ]);
            }
        }

        $accountName = trim((string) ($input['account_name'] ?? ($person['first_name'].' '.$person['last_name'])));
        $method = (string) ($input['method'] ?? 'bank_account');
        $destination = [
            'currency' => $currency,
            'type' => $method === 'mobile_money' ? 'mobile_money' : 'bank_account',
        ];
        if ($destination['type'] === 'mobile_money') {
            $destination['phone_number'] = (string) ($input['phone_number'] ?? '');
            $destination['mobile_provider'] = (string) ($input['mobile_provider'] ?? $input['bank_code'] ?? '');
        } else {
            $destination['account_number'] = (string) ($input['account_number'] ?? '');
            $destination['account_name'] = $accountName;
            $destination['bank_code'] = (string) ($input['bank_code'] ?? '');
        }

        if (! $driver->usesInlinePayoutDestination($country, $method)) {
            throw ValidationException::withMessages([
                'country' => __('This country needs the secure partner setup. Use Continue setup from your wallet.'),
            ]);
        }

        try {
            $driver->updateAccount($accountId, [
                'entity_type' => 'individual',
                'country' => $country,
                'contact_email' => (string) ($input['email'] ?? $user->email),
                'display_name' => mb_substr(trim($person['first_name'].' '.$person['last_name']), 0, 120),
                'fields' => ['payout_destination' => $destination],
            ]);
        } catch (GatewayException $e) {
            throw ValidationException::withMessages([
                'bank' => $e->getMessage() ?: __('We could not save your bank details. Check the account number and bank, then try again.'),
            ]);
        }

        $connection = $this->sync($connection->fresh() ?? $connection);

        $due = array_values(array_filter(array_merge(
            (array) data_get($connection->requirements, 'currently_due', []),
            (array) data_get($connection->requirements, 'past_due', []),
        )));
        if ($due !== [] && $connection->needsSetup()) {
            throw ValidationException::withMessages([
                'identity' => __('Details saved, but more information is still required (:fields). Update the form and try again, or upload your ID if you have not.', [
                    'fields' => implode(', ', array_slice($due, 0, 5)),
                ]),
            ]);
        }

        return $connection;
    }

    /**
     * Split a profile display name into first / last for prefill.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return ['', ''];
        }
        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? $parts[0]];
    }

    /**
     * Legacy hosted link (refresh URLs only). Prefer submitRecipientOnboarding.
     *
     * @throws ConnectLinkException
     * @throws GatewayException
     */
    public function onboardingLink(User $user): string
    {
        $connection = $this->ensureAccount($user);
        $type = $connection->status === PayoutConnection::ACTIVE ? 'update' : 'onboarding';

        try {
            return $this->driver()->createAccountLink(
                (string) $connection->account_id,
                $type,
                $this->url('agent.payouts.connect.return'),
                $this->url('agent.payouts.connect.refresh'),
            );
        } catch (GatewayException $e) {
            $message = self::LINK_ERRORS[(string) $e->providerCode] ?? null;
            if ($message === null) {
                throw $e;
            }
            $this->alertLinkRefused($user, $connection, (string) $e->providerCode);

            throw new ConnectLinkException($message, $e->providerCode);
        }
    }

    /** Re-read the account from Bachs. Never trusts a redirect. */
    public function sync(PayoutConnection $connection): PayoutConnection
    {
        if (blank($connection->account_id)) {
            return $connection;
        }

        $driver = $this->driver();
        $account = $driver->retrieveAccount((string) $connection->account_id);
        $caps = $driver->accountCapabilities((string) $connection->account_id) + BachsDriver::normalizeCapabilities($account);

        $connection = DB::transaction(function () use ($connection, $account, $caps) {
            $locked = PayoutConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            $this->applyState($locked, $account, $caps);
            $locked->last_synced_at = now();
            $locked->save();

            return $locked;
        });

        // Hosted onboarding may have added a bank destination after capabilities flipped active.
        if ($connection->isActive()) {
            $this->syncDestinations((int) $connection->user_id);
        }

        return $connection;
    }

    /**
     * Apply an account.updated, capability.updated or transfer.created event.
     *
     * @return string processed|ignored
     */
    public function applyEvent(PaymentEvent $event): string
    {
        $type = (string) ($event->raw['type'] ?? '');
        $accountId = (string) $event->providerReference;
        if ($accountId === '') {
            return 'ignored';
        }

        $connection = PayoutConnection::query()->where('provider', self::PROVIDER)->where('account_id', $accountId)->first();
        if (! $connection) {
            Log::info('bachs.connect.unknown_account', ['type' => $type, 'account' => $accountId]);

            return 'ignored';
        }

        $object = (array) (($event->raw['data'] ?? [])['object'] ?? $event->raw['data'] ?? []);

        if ($type === 'transfer.created') {
            Log::channel('financial')->info('Bachs transfer to connected account', [
                'user_id' => $connection->user_id,
                'account' => $accountId,
                'transfer' => $object['id'] ?? null,
                'amount' => $event->amount,
                'currency' => $event->currency,
                'reference' => $event->reference,
            ]);

            return 'processed';
        }

        $at = $this->eventTime($event->raw);

        return DB::transaction(function () use ($connection, $type, $object, $at) {
            $locked = PayoutConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            if ($locked->last_event_at !== null && $at->lessThanOrEqualTo($locked->last_event_at)) {
                return 'ignored';
            }

            if ($type === 'account.updated') {
                $caps = BachsDriver::normalizeCapabilities($object) + (array) ($locked->capabilities ?? []);
                $this->applyState($locked, $object, $caps);
            } else {
                $name = (string) ($object['capability'] ?? $object['name'] ?? $object['id'] ?? '');
                $status = strtolower((string) ($object['status'] ?? ''));
                if ($name === '' || $status === '') {
                    return 'ignored';
                }
                $caps = array_replace((array) ($locked->capabilities ?? []), [$name => $status]);
                $requirements = isset($object['requirements']) && is_array($object['requirements'])
                    ? $object['requirements']
                    : (array) ($locked->requirements ?? []);
                $this->applyState($locked, [
                    'requirements' => $requirements + array_filter(['disabled_reason' => $locked->status_reason]),
                    'details_submitted' => ! $locked->needsSetup(),
                ], $caps);
            }

            $locked->last_event_at = $at;
            $locked->save();

            return 'processed';
        });
    }

    /**
     * Readiness from the account object and capability statuses only.
     *
     * @param  array<string, mixed>  $account
     * @param  array<string, string>  $caps
     * @return array{status: string, reason: ?string}
     */
    public static function deriveStatus(array $account, array $caps): array
    {
        $requirements = (array) ($account['requirements'] ?? []);
        $disabled = strtolower((string) ($requirements['disabled_reason'] ?? $account['disabled_reason'] ?? ''));
        $accountStatus = strtolower((string) ($account['status'] ?? ''));
        $required = array_map(fn (string $name) => $caps[$name] ?? null, PayoutConnection::REQUIRED_CAPABILITIES);
        $due = array_merge((array) ($requirements['past_due'] ?? []), (array) ($requirements['currently_due'] ?? []));
        $pending = (array) ($requirements['pending_verification'] ?? []);
        $reason = $disabled !== '' ? $disabled : null;

        if ($accountStatus === 'rejected' || str_starts_with($disabled, 'rejected')) {
            return ['status' => PayoutConnection::REJECTED, 'reason' => $reason];
        }
        if (count(array_filter($required, fn ($s) => $s === 'active')) === count($required)) {
            return ['status' => PayoutConnection::ACTIVE, 'reason' => null];
        }
        if ($due !== []) {
            return ['status' => PayoutConnection::REQUIREMENTS_DUE, 'reason' => $reason];
        }
        if ($pending !== [] || in_array('pending', $required, true) || in_array($accountStatus, ['pending', 'under_review', 'in_review'], true)) {
            return ['status' => PayoutConnection::UNDER_REVIEW, 'reason' => $reason];
        }
        $submitted = (bool) ($account['details_submitted'] ?? $account['onboarding_completed'] ?? false);
        if ($submitted || array_intersect($required, ['restricted', 'disabled']) !== []) {
            return ['status' => PayoutConnection::RESTRICTED, 'reason' => $reason];
        }

        return ['status' => PayoutConnection::REQUIREMENTS_DUE, 'reason' => $reason];
    }

    /**
     * @param  array<string, mixed>  $account
     * @param  array<string, string>  $caps
     */
    private function applyState(PayoutConnection $connection, array $account, array $caps): void
    {
        $previous = $connection->status;
        $derived = self::deriveStatus($account, $caps);

        $connection->environment = $this->currentEnvironment();
        $connection->capabilities = $caps;
        $connection->requirements = array_intersect_key((array) ($account['requirements'] ?? []), array_flip([
            'currently_due', 'past_due', 'eventually_due', 'pending_verification', 'disabled_reason', 'current_deadline',
        ]));
        $connection->status = $derived['status'];
        $connection->status_reason = $derived['reason'] !== null ? mb_substr($derived['reason'], 0, 255) : null;

        if ($previous !== $derived['status']) {
            Log::info('bachs.connect.status_changed', ['user_id' => $connection->user_id, 'from' => $previous, 'to' => $derived['status']]);
        }

        if ($previous !== PayoutConnection::ACTIVE && $derived['status'] === PayoutConnection::ACTIVE) {
            $userId = (int) $connection->user_id;
            DB::afterCommit(fn () => $this->syncDestinations($userId));
        }
    }

    /**
     * Bachs hosted onboarding collects the bank destination on Bachs (docs: Connect onboarding /
     * payouts). Mirror usable destinations into local payout accounts for withdrawals, then
     * register any site-added accounts that still need an API destination (e.g. US/GB/SEPA).
     */
    public function syncDestinations(int $userId): void
    {
        $this->importDestinations($userId);
        $this->registerDestinations($userId);
    }

    /**
     * Create or refresh local payout accounts from destinations already on the Bachs Connect account.
     */
    public function importDestinations(int $userId): void
    {
        $connection = PayoutConnection::for($userId, self::PROVIDER);
        if (! $connection || blank($connection->account_id) || ! $connection->isActive()) {
            return;
        }

        $accountId = (string) $connection->account_id;
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        try {
            $rows = $this->driver()->listDestinations($accountId);
        } catch (\Throwable $e) {
            Log::warning('bachs.connect.import_list_failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return;
        }

        $hintCountry = $this->countryFor($user);
        $bounceFee = \App\Modules\Wallet\Services\PayoutAccountService::bounceFee();
        $terms = app(\App\Modules\Wallet\Services\PayoutAccountService::class)
            ->termsText((string) $user->name, $bounceFee);

        foreach ($rows as $row) {
            if (! ($row['is_usable'] ?? false)) {
                continue;
            }
            $destinationId = (string) ($row['id'] ?? $row['destination_id'] ?? '');
            if ($destinationId === '') {
                continue;
            }

            $currency = strtoupper((string) ($row['currency'] ?? ''));
            $type = strtolower((string) ($row['type'] ?? 'bank_account'));
            $method = $type === 'mobile_money' ? 'mobile_money' : 'bank_account';
            $country = $this->countryForDestination($currency, $hintCountry, $row);
            if ($country === null || config("payout_methods.countries.$country.methods.$method") === null) {
                continue;
            }

            $last4 = preg_replace('/\D/', '', (string) ($row['last4'] ?? $row['account_number_last4'] ?? $row['account_number'] ?? $row['phone_number'] ?? ''));
            $last4 = $last4 !== '' ? substr($last4, -4) : '****';
            $holder = (string) ($row['account_name'] ?? $row['account_holder_name'] ?? $row['name'] ?? $user->name);
            $bankName = (string) ($row['bank_name'] ?? $row['bank'] ?? ($method === 'mobile_money' ? 'Mobile money' : 'Bank account'));
            $bankCode = $this->strOrNull($row['bank_code'] ?? $row['mobile_provider'] ?? null);
            $fields = $method === 'mobile_money'
                ? array_filter([
                    'mobile_provider' => $bankCode,
                    'phone_number' => (string) ($row['phone_number'] ?? $last4),
                ])
                : array_filter([
                    'account_number' => (string) ($row['account_number'] ?? $last4),
                    'bank_code' => $bankCode,
                ]);

            $existing = PayoutAccount::query()->where('user_id', $userId)->where('active', true)->get()
                ->first(fn (PayoutAccount $a) => (string) data_get($a->provider_beneficiary_ids, self::PROVIDER.'.destination') === $destinationId);

            $ids = [
                self::PROVIDER => [
                    'account' => $accountId,
                    'destination' => $destinationId,
                    'fingerprint' => 'hosted:'.$destinationId,
                ],
            ];

            if ($existing) {
                $existing->fill([
                    'country' => $country,
                    'currency' => $currency,
                    'method' => $method,
                    'bank_code' => $bankCode ?? $existing->bank_code,
                    'bank_name' => $bankName !== '' ? $bankName : $existing->bank_name,
                    'details' => $fields !== [] ? $fields : $existing->details,
                    'display_mask' => '****'.$last4,
                    'account_holder_name' => $holder !== '' ? mb_substr($holder, 0, 120) : $existing->account_holder_name,
                    'verification' => PayoutAccount::RESOLVED,
                    'verified_at' => $existing->verified_at ?? now(),
                    'provider_beneficiary_ids' => array_replace((array) $existing->provider_beneficiary_ids, $ids),
                ])->save();

                continue;
            }

            $hasPrimary = PayoutAccount::query()->where('user_id', $userId)->where('active', true)->where('is_primary', true)->exists();
            $atLimit = PayoutAccount::query()->where('user_id', $userId)->where('active', true)->count()
                >= app(\App\Modules\Wallet\Services\PayoutAccountService::class)->maxAccounts();
            if ($atLimit) {
                Log::info('bachs.connect.import_skipped_limit', ['user_id' => $userId, 'destination' => $destinationId]);

                continue;
            }

            PayoutAccount::query()->create([
                'user_id' => $userId,
                'country' => $country,
                'currency' => $currency,
                'method' => $method,
                'bank_code' => $bankCode,
                'bank_name' => $bankName,
                'details' => $fields,
                'display_mask' => '****'.$last4,
                'account_holder_name' => mb_substr($holder !== '' ? $holder : (string) $user->name, 0, 120),
                'verification' => PayoutAccount::RESOLVED,
                'verified_at' => now(),
                'resolved_by' => 'bachs_connect',
                'provider_beneficiary_ids' => $ids,
                'bounce_terms_accepted_at' => now(),
                'bounce_fee_disclosed' => $bounceFee,
                'bounce_terms_text' => $terms,
                'is_primary' => ! $hasPrimary,
                'active' => true,
            ]);
        }
    }

    /** Put every active payout account of the agent under their Bachs account, primary first. */
    public function registerDestinations(int $userId): void
    {
        $accounts = PayoutAccount::query()->where('user_id', $userId)->where('active', true)
            ->where('verification', '!=', PayoutAccount::REJECTED)
            ->orderByDesc('is_primary')->orderBy('id')->get();

        foreach ($accounts as $account) {
            if (filled(data_get($account->provider_beneficiary_ids, self::PROVIDER.'.destination'))) {
                continue;
            }
            try {
                app(\App\Modules\Wallet\Services\PayoutAccountService::class)->prepareBeneficiaries($account);
            } catch (\Throwable $e) {
                Log::warning('bachs.connect.destination_failed', ['user_id' => $userId, 'account_id' => $account->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Map a Bachs destination currency to a country we offer (config/payout_methods.php).
     *
     * @param  array<string, mixed>  $row
     */
    private function countryForDestination(string $currency, ?string $hintCountry, array $row): ?string
    {
        $fromRow = strtoupper((string) ($row['country'] ?? $row['country_code'] ?? ''));
        if (preg_match('/^[A-Z]{2}$/', $fromRow) && config("payout_methods.countries.$fromRow.currency") === $currency) {
            return $fromRow;
        }
        if ($hintCountry && config("payout_methods.countries.$hintCountry.currency") === $currency) {
            return $hintCountry;
        }
        foreach ((array) config('payout_methods.countries', []) as $code => $meta) {
            if (strtoupper((string) ($meta['currency'] ?? '')) === $currency) {
                return (string) $code;
            }
        }

        return null;
    }

    private function strOrNull(mixed $value): ?string
    {
        $value = is_string($value) || is_numeric($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    /** @param  array<string, mixed>  $payload */
    private function eventTime(array $payload): CarbonImmutable
    {
        $value = $payload['created_at'] ?? $payload['created'] ?? $payload['timestamp'] ?? null;
        try {
            if (is_numeric($value)) {
                return CarbonImmutable::createFromTimestamp((int) $value);
            }
            if (is_string($value) && $value !== '') {
                return CarbonImmutable::parse($value);
            }
        } catch (\Throwable) {
            // fall through
        }

        return CarbonImmutable::now();
    }

    private function countryFor(User $user): ?string
    {
        $country = PayoutAccount::query()->where('user_id', $user->id)->where('active', true)
            ->orderByDesc('is_primary')->orderBy('id')->value('country')
            ?? $user->country;

        return is_string($country) && preg_match('/^[A-Za-z]{2}$/', $country) ? strtoupper($country) : null;
    }

    private function url(string $route): string
    {
        return Route::has($route) ? route($route) : url('/');
    }

    private function alertLinkRefused(User $user, PayoutConnection $connection, string $code): void
    {
        Log::warning('bachs.connect.link_refused', ['user_id' => $user->id, 'account' => $connection->account_id, 'code' => $code]);

        try {
            app(NotificationDispatcher::class)->notifyAdmins(new NotificationMessage(
                type: 'payouts.connect_link_refused',
                title: 'Bachs refused an onboarding link',
                body: 'Bachs would not create a setup link for user #'.$user->id.' ('.$code.'). Check the account in the Bachs dashboard.',
                actionUrl: null,
                meta: ['user_id' => $user->id, 'code' => $code],
                dedupeKey: 'connect-link-'.$user->id.'-'.$code,
            ), ['database']);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
