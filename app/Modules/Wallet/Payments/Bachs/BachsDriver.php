<?php

namespace App\Modules\Wallet\Payments\Bachs;

use App\Models\PayoutConnection;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Wallet\Payments\AbstractGatewayDriver;
use App\Modules\Wallet\Payments\Contracts\CollectionGateway;
use App\Modules\Wallet\Payments\Contracts\PayoutGateway;
use App\Modules\Wallet\Payments\Contracts\PreparesBeneficiaries;
use App\Modules\Wallet\Payments\Contracts\ReclaimsFailedPayouts;
use App\Modules\Wallet\Payments\Data\CheckoutRequest;
use App\Modules\Wallet\Payments\Data\CheckoutSession;
use App\Modules\Wallet\Payments\Data\CoverageRoute;
use App\Modules\Wallet\Payments\Data\PaymentEvent;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Payments\Data\PayoutQuote;
use App\Modules\Wallet\Payments\Data\PayoutRequest;
use App\Modules\Wallet\Payments\Data\PayoutResult;
use App\Modules\Wallet\Payments\Data\ResolvedAccount;
use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use App\Modules\Wallet\Payments\Exceptions\InvalidWebhookSignature;
use App\Modules\Wallet\Payments\FailureClass;
use App\Modules\Wallet\Payments\GatewayHealth;
use App\Modules\Wallet\Payments\PayoutGate;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Bachs (docs.bachs.io): hosted checkout for collections, Connect recipient accounts for payouts.
 *
 * Payout flow per attempt (reference WPO-{withdrawal}-{attempt}):
 *   1. the agent's one connected recipient account (BachsConnectService) has transfers + payouts
 *      active, and the destination registered under it is usable
 *   2. transfer net + fee estimate + buffer from the platform balance  (Idempotency-Key {ref}-transfer)
 *   3. quote when the destination currency is not USD (quotes last 30 seconds)
 *   4. create the payout on the recipient account               (Idempotency-Key {ref})
 *   5. sweep anything left on the recipient account back          (Idempotency-Key {ref}-sweep)
 * A definitive error before the payout exists moves the transfer back ({ref}-reverse), so the
 * attempt can be reported as not created. A timeout on step 4 is never reversed: it is unknown.
 */
class BachsDriver extends AbstractGatewayDriver implements CollectionGateway, PayoutGateway, PreparesBeneficiaries, ReclaimsFailedPayouts
{
    public const KEY = 'bachs';

    private const NOT_CREATED_CODES = [
        'PAYOUTS_NOT_ENABLED', 'PAYOUT_CURRENCY_NOT_ENABLED', 'PAYOUT_SOURCE_CURRENCY_UNSUPPORTED',
        'PAYOUT_RECIPIENT_INCOMPLETE', 'DESTINATION_NOT_FOUND', 'DESTINATION_PENDING_REVIEW',
        'DESTINATION_REJECTED', 'CURRENCY_NOT_SUPPORTED', 'UNAUTHORIZED', 'FORBIDDEN',
        'WITHDRAWAL_LIMIT_EXCEEDED', 'DAILY_WITHDRAWAL_LIMIT_EXCEEDED', 'ACCOUNT_NOT_CONNECTED',
    ];

    private const RECOVERABLE_CODES = ['INSUFFICIENT_BALANCE'];

    public const CONNECT_EVENTS = ['account.updated', 'capability.updated', 'transfer.created'];

    /**
     * Bank schemas that cannot use inline `payout_destination` on the account
     * (docs: register via POST /v1/payouts/destinations; hosted onboarding cannot collect them).
     */
    public const API_ONLY_SCHEMAS = ['us_bank', 'gb_bank', 'sepa', 'ca_bank'];

    private const ACCOUNT_DETAILS_CODES = ['BANK_ROUTING_DETAILS_INVALID'];

    /** Destinations Bachs documents for global payouts. EUR is added from config (SEPA countries). */
    private const PAYOUT_ROUTES = [
        'NG' => ['bank_account'],
        'GH' => ['bank_account', 'mobile_money'],
        'KE' => ['bank_account', 'mobile_money'],
        'UG' => ['bank_account'],
        'TZ' => ['bank_account', 'mobile_money'],
        'US' => ['bank_account'],
        'GB' => ['bank_account'],
        'CA' => ['bank_account'],
    ];

    /** Per-payout mobile money limits, in the currency that arrives. */
    private const MOBILE_LIMITS = [
        'GH' => ['5', '25000'],
        'KE' => ['20', '250000'],
        'TZ' => ['1000', '5000000'],
    ];

    private const CHECKOUT_CURRENCIES = ['USD', 'NGN', 'GHS', 'KES', 'MWK', 'RWF', 'TZS', 'UGX', 'XAF', 'XOF', 'ZMW'];

    private ?BachsClient $client = null;

    public function __construct(private GatewayHealth $health) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function displayName(): string
    {
        return 'Bachs';
    }

    public function credentialFields(): array
    {
        return [
            $this->environmentField(),
            ['name' => 'secret_key', 'label' => 'Secret API key', 'type' => 'secret', 'required' => true, 'store' => 'credentials',
                'hint' => 'Starts with sk_sandbox_ or sk_live_. Needs checkout, payouts, transfers and connected accounts scopes.'],
            ['name' => 'webhook_secret', 'label' => 'Webhook signing secret (Account events)', 'type' => 'secret', 'required' => true, 'store' => 'credentials',
                'hint' => 'From the endpoint with event source "Account events": Checkout Completed, Checkout Expired, Collection Succeeded, Collection Failed, Transfer Created.'],
            ['name' => 'connect_webhook_secret', 'label' => 'Webhook signing secret (Connected account events)', 'type' => 'secret', 'required' => false, 'store' => 'credentials',
                'hint' => 'From a second endpoint with the same URL and event source "Connected account events": Account Updated, Capability Updated, Payout Created, Payout Paid, Payout Failed.'],
            ['name' => 'payout_fee_buffer', 'label' => 'Payout fee buffer (USD)', 'type' => 'text', 'required' => false, 'store' => 'meta',
                'hint' => 'Extra sent to the recipient account to cover the payout fee. Unused funds are swept back. Default 1.00.'],
        ];
    }

    public function testConnection(): array
    {
        if (! $this->credential('secret_key')) {
            return ['ok' => false, 'message' => 'Enter the secret API key first.'];
        }

        try {
            $me = $this->client()->get('/v1/accounts/me');
        } catch (GatewayException $e) {
            return ['ok' => false, 'message' => 'Bachs said: '.$e->getMessage()];
        }

        $name = (string) ($me['name'] ?? $me['display_name'] ?? $me['id'] ?? 'your account');

        return ['ok' => true, 'message' => 'Connected to '.$name.' ('.$this->environment().').'];
    }

    // ---------------------------------------------------------------- collections

    public function currencies(): array
    {
        return self::CHECKOUT_CURRENCIES;
    }

    public function initializeCheckout(CheckoutRequest $request): CheckoutSession
    {
        $customer = ['email' => $request->customerEmail];
        if (trim($request->customerName) !== '') {
            $customer['name'] = mb_substr(trim($request->customerName), 0, 120);
        }

        $body = [
            'pricing' => ['currency' => strtoupper($request->currency), 'amount' => Money::round($request->amount, 2)],
            'customer' => $customer,
            'success_url' => $request->callbackUrl,
            'cancel_url' => $request->cancelUrl ?? $request->callbackUrl,
            'reference' => $request->reference,
            'metadata' => array_slice(array_merge($request->metadata, ['reference' => $request->reference]), 0, 20, true),
            'expires_in_minutes' => max(1, min(1440, $request->expiresInMinutes)),
        ];

        $session = $this->tracked(fn () => $this->client()->post('/v1/checkout-sessions', $body));

        $url = (string) ($session['checkout_url'] ?? '');
        if ($url === '') {
            throw new GatewayException('Bachs did not return a checkout link.', null, null, false, $session);
        }

        return new CheckoutSession(
            $url,
            isset($session['checkout_id']) ? (string) $session['checkout_id'] : null,
            ! empty($session['expires_at']) ? CarbonImmutable::parse($session['expires_at']) : null,
            $session,
        );
    }

    public function verify(string $reference, ?string $providerReference = null): PaymentEvent
    {
        $session = $providerReference
            ? $this->tracked(fn () => $this->client()->get('/v1/checkout-sessions/'.rawurlencode($providerReference)))
            : $this->findSessionByReference($reference);

        if ($session === null) {
            return new PaymentEvent(PaymentEvent::TYPE_COLLECTION, self::KEY, PaymentEvent::STATUS_PENDING, $reference, $providerReference);
        }

        return $this->sessionToEvent($session, $reference);
    }

    // ---------------------------------------------------------------- webhooks

    public function parseWebhook(Request $request): PaymentEvent
    {
        $secrets = array_values(array_filter([
            $this->credential('webhook_secret'),
            $this->credential('connect_webhook_secret'),
        ]));
        if ($secrets === []) {
            throw new InvalidWebhookSignature('Bachs webhook secret is not set.');
        }

        $raw = $request->getContent();
        $timestamp = null;
        $signatures = [];

        $v2 = (string) $request->header('X-Bachs-Signature-V2', '');
        if ($v2 !== '') {
            foreach (explode(',', $v2) as $part) {
                [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
                if ($k === 't') {
                    $timestamp = $v;
                } elseif ($k === 'v1' && $v !== '') {
                    $signatures[] = $v;
                }
            }
        } else {
            $timestamp = (string) $request->header('X-Bachs-Timestamp', '');
            $legacy = (string) $request->header('X-Bachs-Signature', '');
            if ($legacy !== '') {
                $signatures[] = $legacy;
            }
        }

        if (! is_numeric($timestamp) || $signatures === []) {
            throw new InvalidWebhookSignature('Missing Bachs signature headers.');
        }
        if (abs(time() - (int) $timestamp) > 300) {
            throw new InvalidWebhookSignature('Bachs webhook timestamp is outside the allowed window.');
        }

        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', $timestamp.'.'.$raw, $secret);
            foreach ($signatures as $signature) {
                if (hash_equals($expected, strtolower($signature))) {
                    return $this->normalizeWebhookPayload(json_decode($raw, true) ?: []);
                }
            }
        }

        throw new InvalidWebhookSignature('Bachs signature does not match.');
    }

    public function normalizeWebhookPayload(array $payload): PaymentEvent
    {
        $type = (string) ($payload['type'] ?? '');
        $eventId = isset($payload['id']) ? (string) $payload['id'] : null;
        $data = (array) ($payload['data'] ?? []);
        $metadata = (array) ($data['metadata'] ?? []);

        if (in_array($type, ['collection.succeeded', 'checkout.completed', 'collection.failed', 'checkout.expired'], true)) {
            $paymentStatus = strtolower((string) ($data['payment_status'] ?? ''));
            $status = match ($type) {
                'collection.failed', 'checkout.expired' => PaymentEvent::STATUS_FAILED,
                // checkout.completed also fires for free/setup checkouts — only treat as paid when Bachs says so.
                'checkout.completed' => in_array($paymentStatus, ['paid', 'succeeded', 'no_payment_required'], true)
                    ? PaymentEvent::STATUS_SUCCEEDED
                    : PaymentEvent::STATUS_PENDING,
                default => PaymentEvent::STATUS_SUCCEEDED,
            };

            return new PaymentEvent(
                PaymentEvent::TYPE_COLLECTION,
                self::KEY,
                $status,
                $this->str($data['reference'] ?? $metadata['reference'] ?? null),
                $this->str($data['checkout_id'] ?? null),
                $this->str($data['amount'] ?? null),
                $this->str($data['currency'] ?? null),
                $eventId,
                $type === 'collection.failed' ? $this->str($data['failure_code'] ?? 'COLLECTION_FAILED') : null,
                $this->str($data['failure_reason'] ?? null),
                $payload,
            );
        }

        if (in_array($type, ['payout.created', 'payout.paid', 'payout.failed'], true)) {
            $status = match ($type) {
                'payout.paid' => PaymentEvent::STATUS_SUCCEEDED,
                'payout.failed' => PaymentEvent::STATUS_FAILED,
                default => PaymentEvent::STATUS_PENDING,
            };

            return new PaymentEvent(
                PaymentEvent::TYPE_PAYOUT,
                self::KEY,
                $status,
                $this->str($data['reference'] ?? null),
                $this->str($data['id'] ?? $data['payout_id'] ?? null),
                $this->str($data['amount'] ?? null),
                $this->str($data['currency'] ?? null),
                $eventId,
                $type === 'payout.failed' ? $this->str($data['failure_code'] ?? 'PAYOUT_FAILED') : null,
                $this->str($data['failure_reason'] ?? null),
                $payload,
            );
        }

        if (in_array($type, self::CONNECT_EVENTS, true)) {
            $object = (array) ($data['object'] ?? $data);
            $accountId = $payload['account'] ?? $object['account'] ?? $object['account_id'] ?? null;
            if ($type === 'account.updated') {
                $accountId ??= $object['id'] ?? null;
            } elseif ($type === 'transfer.created') {
                $accountId = $object['destination'] ?? $accountId;
            }

            return new PaymentEvent(
                PaymentEvent::TYPE_CONNECT,
                self::KEY,
                PaymentEvent::STATUS_PENDING,
                $this->str($object['transfer_group'] ?? $object['metadata']['reference'] ?? null),
                $this->str($accountId),
                $this->str($object['amount'] ?? null),
                $this->str($object['currency'] ?? null),
                $eventId,
                raw: $payload,
            );
        }

        return PaymentEvent::ignored(self::KEY, $eventId, $payload);
    }

    // ---------------------------------------------------------------- connected accounts

    /**
     * Create the agent's single recipient account. The Idempotency-Key is per user, so a
     * retry or a second click returns the same account instead of a duplicate.
     *
     * @return array<string, mixed>
     */
    public function createRecipientAccount(int $userId, string $email, string $displayName, ?string $country): array
    {
        $body = [
            'contact_email' => $email,
            'display_name' => mb_substr($displayName, 0, 120),
            'entity_type' => 'individual',
            'configuration' => ['recipient' => ['capabilities' => [
                'transfers' => ['requested' => true],
                'payouts' => ['requested' => true],
            ]]],
        ];
        if ($country !== null && preg_match('/^[A-Z]{2}$/', strtoupper($country))) {
            $body['country'] = strtoupper($country);
        }

        $account = $this->client()->post('/v1/accounts', $body, null, 'acct-user-'.$userId);
        if ((string) ($account['id'] ?? '') === '') {
            throw new GatewayException('Bachs did not return a recipient account id.', null, null, false, $account);
        }

        return $account;
    }

    /** @return array<string, mixed> */
    public function retrieveAccount(string $accountId): array
    {
        return $this->client()->get('/v1/accounts/'.rawurlencode($accountId));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function updateAccount(string $accountId, array $body): array
    {
        return $this->client()->post('/v1/accounts/'.rawurlencode($accountId), $body);
    }

    /** @return array<string, string> capability name => lower-case status */
    public function accountCapabilities(string $accountId): array
    {
        return self::normalizeCapabilities($this->client()->get('/v1/accounts/'.rawurlencode($accountId).'/capabilities'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPersons(string $accountId): array
    {
        $body = $this->client()->get('/v1/accounts/'.rawurlencode($accountId).'/persons');
        $rows = array_is_list($body) ? $body : (array) ($body['items'] ?? $body['data'] ?? $body['persons'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    public function createPerson(string $accountId, array $person): array
    {
        return $this->client()->post('/v1/accounts/'.rawurlencode($accountId).'/persons', $person);
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    public function updatePerson(string $accountId, string $personId, array $person): array
    {
        return $this->client()->post('/v1/accounts/'.rawurlencode($accountId).'/persons/'.rawurlencode($personId), $person);
    }

    /** @return array<string, mixed> */
    public function uploadIdentityDocument(mixed $file, ?string $filename = null): array
    {
        return $this->client()->postMultipart('/v1/utilities/uploads', [
            'scope' => 'identity_document',
        ], 'file', $file, $filename);
    }

    /**
     * @return array<string, mixed>
     */
    public function attachPersonDocument(string $accountId, string $personId, string $uploadId, string $document = 'primary_verification', string $side = 'front'): array
    {
        return $this->client()->post('/v1/accounts/'.rawurlencode($accountId).'/persons/'.rawurlencode($personId).'/documents', [
            'file' => $uploadId,
            'document' => $document,
            'side' => $side,
        ]);
    }

    /**
     * A single-use hosted onboarding (or update) link. Kept for legacy refresh URLs; primary setup is API onboarding.
     */
    public function createAccountLink(string $accountId, string $type, string $returnUrl, string $refreshUrl): string
    {
        $link = $this->client()->post('/v1/accounts/'.rawurlencode($accountId).'/account-links', [
            'type' => $type,
            'return_url' => $returnUrl,
            'refresh_url' => $refreshUrl,
        ]);

        $url = (string) ($link['url'] ?? $link['link'] ?? '');
        if ($url === '') {
            throw new GatewayException('Bachs did not return an onboarding link.', null, null, false, ['keys' => array_keys($link)]);
        }

        return $url;
    }

    /** @return list<array<string, mixed>> */
    public function listDestinations(string $accountId): array
    {
        $body = $this->client()->get('/v1/payouts/destinations', [], $accountId);

        // Bachs list endpoints use `items` (docs.bachs.io/api-reference/success-responses).
        return array_values(array_filter(
            array_is_list($body) ? $body : (array) ($body['items'] ?? $body['data'] ?? $body['destinations'] ?? []),
            'is_array',
        ));
    }

    /** Move a recipient account's available USD back to the platform, once per key. */
    public function sweepAccount(string $accountId, string $idempotencyKey): bool
    {
        return $this->sweep($idempotencyKey, $accountId);
    }

    public function accountBalance(string $accountId, string $currency = 'USD'): ?string
    {
        return $this->availableBalance($this->client()->get('/v1/balances', [], $accountId), $currency);
    }

    /**
     * Capability statuses from a capabilities response or an account object, keyed by name.
     *
     * GET /v1/accounts/{id}/capabilities returns `{ "items": [ { "name", "status", ... } ] }`
     * (docs.bachs.io/api-reference/accounts/list-capabilities). Account reads nest a
     * `capabilities` map. Accept both, plus legacy `data` wrappers.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, string>
     */
    public static function normalizeCapabilities(array $body): array
    {
        $caps = $body['capabilities']
            ?? $body['configuration']['recipient']['capabilities']
            ?? $body['items']
            ?? $body['data']
            ?? null;
        // A bare body only counts as a capability map when its entries are capability objects.
        $bare = $caps === null;
        $caps = (array) ($caps ?? (isset($body['capability']) || isset($body['name']) ? [$body] : $body));
        if ($bare && ! array_is_list($caps)) {
            $caps = array_filter($caps, fn ($value) => is_array($value) && isset($value['status']));
        }

        if (array_is_list($caps)) {
            $caps = collect($caps)
                ->filter(fn ($c) => is_array($c))
                ->keyBy(fn ($c) => (string) ($c['capability'] ?? $c['name'] ?? $c['id'] ?? ''))
                ->all();
        }

        $out = [];
        foreach ($caps as $name => $value) {
            if (! is_string($name) || $name === '') {
                continue;
            }
            $status = is_array($value) ? ($value['status'] ?? null) : $value;
            if (is_string($status) && $status !== '') {
                $out[$name] = strtolower($status);
            }
        }

        return $out;
    }

    /**
     * Human-readable reasons for inactive required capabilities, from status_details.
     *
     * @param  array<string, mixed>  $body  Raw capabilities list or account object
     * @param  array<string, string>  $caps  name => status
     */
    public static function describeInactiveCapabilities(array $body, array $caps): string
    {
        $required = PayoutConnection::REQUIRED_CAPABILITIES;
        $parts = [];
        foreach ($required as $name) {
            $status = $caps[$name] ?? 'missing';
            if ($status === 'active') {
                continue;
            }
            $detail = self::capabilityDetailMessage($body, $name);
            $parts[] = $detail !== null
                ? "{$name}={$status} ({$detail})"
                : "{$name}={$status}";
        }

        if ($parts === []) {
            return 'Payouts are not active on the recipient account yet.';
        }

        return 'Recipient capabilities not ready: '.implode('; ', $parts).'.';
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function capabilityDetailMessage(array $body, string $name): ?string
    {
        $rows = $body['items']
            ?? $body['data']
            ?? (isset($body['capabilities']) && is_array($body['capabilities']) ? $body['capabilities'] : null)
            ?? null;

        $entry = null;
        if (is_array($rows)) {
            if (array_is_list($rows)) {
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $key = (string) ($row['capability'] ?? $row['name'] ?? $row['id'] ?? '');
                    if ($key === $name) {
                        $entry = $row;
                        break;
                    }
                }
            } elseif (isset($rows[$name]) && is_array($rows[$name])) {
                $entry = $rows[$name];
            }
        }

        if ($entry === null) {
            return null;
        }

        $details = $entry['status_details'] ?? null;
        if (! is_array($details) || $details === []) {
            return null;
        }

        $messages = [];
        foreach ($details as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $msg = trim((string) ($detail['message'] ?? $detail['code'] ?? ''));
            if ($msg !== '') {
                $messages[] = $msg;
            }
        }

        return $messages === [] ? null : implode(' · ', $messages);
    }

    // ---------------------------------------------------------------- payout catalogue

    public function payoutCountries(): array
    {
        return array_keys($this->routes());
    }

    public function payoutMethods(string $country): array
    {
        return $this->routes()[strtoupper($country)] ?? [];
    }

    public function listBanks(string $country, string $method = 'bank_account'): array
    {
        $country = strtoupper($country);

        if ($method === 'mobile_money') {
            $body = $this->client()->get('/v1/payouts/mobile-money-providers', ['country_code' => $country]);

            return collect((array) ($body['providers'] ?? $body['data'] ?? []))
                ->map(fn ($p) => ['name' => (string) ($p['name'] ?? ''), 'code' => (string) ($p['name'] ?? '')])
                ->filter(fn ($p) => $p['code'] !== '')
                ->values()
                ->all();
        }

        $body = $this->client()->get('/v1/reference/banks', ['country' => $country]);
        $list = array_is_list($body) ? $body : (array) ($body['data'] ?? $body['banks'] ?? []);

        return collect($list)
            ->map(fn ($b) => [
                'name' => (string) ($b['name'] ?? $b['bank_name'] ?? ''),
                'code' => (string) ($b['code'] ?? $b['bank_code'] ?? ''),
            ])
            ->filter(fn ($b) => $b['name'] !== '' && $b['code'] !== '')
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function supportsResolve(string $country, string $method): bool
    {
        return strtoupper($country) === 'NG' && $method === 'bank_account';
    }

    public function resolveAccount(PayoutAccountDetails $account): ResolvedAccount
    {
        $body = $this->client()->post('/v1/misc/bank-accounts/resolve', [
            'account_number' => (string) $account->field('account_number'),
            'bank_code' => (string) ($account->bankCode ?? $account->field('bank_code')),
        ]);

        $name = trim((string) ($body['account_name'] ?? $body['data']['account_name'] ?? ''));
        if ($name === '') {
            throw new GatewayException('The bank did not return an account name.', 'ACCOUNT_NOT_RESOLVED', 422, false, $body);
        }

        return new ResolvedAccount($name, self::KEY, $body);
    }

    public function coverage(): array
    {
        $body = $this->client()->get('/v1/currencies/payout-supported');
        $list = array_is_list($body) ? $body : (array) ($body['currencies'] ?? $body['data'] ?? []);
        $supported = collect($list)
            ->map(fn ($c) => strtoupper((string) (is_array($c) ? ($c['code'] ?? $c['currency'] ?? '') : $c)))
            ->filter()
            ->all();

        // Bachs docs (Supported currencies): GET /v1/currencies/payout-supported lists currencies
        // configured on the organisation, not the product support matrix. A fresh sandbox often
        // returns none even though NGN/GHS/… payouts are documented. PAYOUT_ROUTES mirrors that
        // published matrix (docs.bachs.io/guides/payouts/global-payouts), so use it when the
        // org list is empty rather than reporting zero routes.
        if ($supported === []) {
            $supported = collect($this->routes())
                ->keys()
                ->map(fn ($country) => strtoupper((string) config("payout_methods.countries.$country.currency")))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $routes = [];
        foreach ($this->routes() as $country => $methods) {
            $currency = (string) config("payout_methods.countries.$country.currency");
            if (! in_array($currency, $supported, true)) {
                continue;
            }
            foreach ($methods as $method) {
                [$min, $max] = $method === 'mobile_money' ? (self::MOBILE_LIMITS[$country] ?? [null, null]) : [null, null];
                $routes[] = new CoverageRoute($country, $method, $currency, $this->supportsResolve($country, $method), $min, $max);
            }
        }

        return $routes;
    }

    public function failureClass(?string $code): string
    {
        $code = strtoupper((string) $code);

        return match (true) {
            in_array($code, self::NOT_CREATED_CODES, true) => FailureClass::NOT_CREATED,
            in_array($code, self::RECOVERABLE_CODES, true) => FailureClass::RECOVERABLE,
            in_array($code, self::ACCOUNT_DETAILS_CODES, true) => FailureClass::ACCOUNT_DETAILS,
            default => FailureClass::OTHER,
        };
    }

    public function balance(string $currency = 'USD'): ?string
    {
        return $this->availableBalance($this->client()->get('/v1/balances'), $currency);
    }

    public function quoteTransfer(string $amount, PayoutAccountDetails $destination): ?PayoutQuote
    {
        $currency = strtoupper($destination->currency);
        if ($currency === 'USD') {
            return null;
        }

        $quote = $this->client()->post('/v1/payouts/quotes', $this->quoteBody($amount, $destination));
        $quoteId = $this->str($quote['quote_id'] ?? $quote['id'] ?? null);
        // Bachs docs: a successful quote returns quote_id + FX fields. It does not include a fee
        // field — the payout fee is charged when the payout is created (docs.bachs.io Create Payout Quote).
        if ($quoteId === null) {
            return null;
        }

        return new PayoutQuote(
            providerFee: $this->quoteProviderFee($quote, Money::round($amount, 2)),
            feeCurrency: strtoupper((string) ($quote['fee_currency'] ?? $quote['from_currency'] ?? 'USD')),
            destinationCurrency: $currency,
            fxRate: $this->str($quote['exchange_rate'] ?? null),
            destinationAmount: $this->str($quote['to_amount'] ?? null),
            expiresAt: ! empty($quote['expires_at']) ? CarbonImmutable::parse($quote['expires_at']) : null,
            source: PayoutQuote::SOURCE_LIVE,
            quoteId: $quoteId,
            gatewayKey: self::KEY,
            quotedAt: CarbonImmutable::now(),
        );
    }

    /**
     * Prefer an explicit fee when present. Otherwise derive it from Bachs' documented quote shape:
     * fee is taken from from_amount before FX, so fee ≈ from_amount − (to_amount / exchange_rate).
     * When that cannot be computed, return 0 — funding still adds the payout fee buffer.
     *
     * @param  array<string, mixed>  $quote
     */
    private function quoteProviderFee(array $quote, string $requestedAmount): string
    {
        $explicit = $quote['fee'] ?? $quote['fee_amount'] ?? null;
        if ($explicit !== null && $explicit !== '') {
            return Money::round((string) $explicit, 2);
        }

        $from = $this->str($quote['from_amount'] ?? $requestedAmount);
        $to = $this->str($quote['to_amount'] ?? null);
        $rate = $this->str($quote['exchange_rate'] ?? null);
        if ($from === null || $to === null || $rate === null || Money::cmp($rate, '0', 8) <= 0) {
            return '0.00';
        }

        $sourceAfterFee = Money::div($to, $rate, 8);
        $fee = Money::sub($from, $sourceAfterFee, 8);
        if (Money::cmp($fee, '0', 8) < 0) {
            return '0.00';
        }

        return Money::round($fee, 2);
    }

    // ---------------------------------------------------------------- payouts

    public function prepareBeneficiary(PayoutAccountDetails $destination): array
    {
        $ids = (array) ($destination->beneficiaryIds[self::KEY] ?? []);
        $fingerprint = $this->fingerprint($destination);

        // Accounts are only created by the agent's own connect flow, never silently here.
        $accountId = $this->connectedAccountId($destination);
        if ($accountId === null) {
            throw new GatewayException('The agent has not connected a Bachs payout account yet.', 'ACCOUNT_NOT_CONNECTED', 400);
        }

        $sameAccount = ($ids['account'] ?? null) === $accountId;
        $destinationId = $sameAccount && ($ids['fingerprint'] ?? null) === $fingerprint ? ($ids['destination'] ?? null) : null;
        $row = null;

        if (! $destinationId && ! $this->requiresApiDestination($destination)) {
            $row = $this->hostedDestination($accountId, $destination);
            $destinationId = $row !== null ? $this->str($row['id'] ?? $row['destination_id'] ?? null) : null;
        }

        if (! $destinationId) {
            $created = $this->client()->post('/v1/payouts/destinations', $this->destinationBody($destination), $accountId, 'pd-'.$accountId.'-'.$fingerprint);
            $destinationId = (string) ($created['id'] ?? $created['destination_id'] ?? '');
            if ($destinationId === '') {
                throw new GatewayException('Bachs did not return a destination id.', null, null, false, $created);
            }
            $row = $created;
        } elseif ($row === null) {
            $row = $this->client()->get('/v1/payouts/destinations/'.rawurlencode($destinationId), [], $accountId);
        }

        $ids = ['account' => $accountId, 'destination' => $destinationId, 'fingerprint' => $fingerprint];

        return [
            'ids' => [self::KEY => $ids],
            'usable' => (bool) ($row['is_usable'] ?? false),
            'status' => $this->str($row['status'] ?? null),
            'message' => $this->str($row['status_reason'] ?? null),
        ];
    }

    public function initiateTransfer(PayoutRequest $request): PayoutResult
    {
        $ref = $request->reference;
        $destination = $request->destination;
        $net = Money::round($request->amount, 2);
        $source = strtoupper($request->currency);

        if (! PayoutGate::currencyAllowed($source)) {
            return $this->result(PayoutResult::NOT_CREATED, null, 'CURRENCY_MISMATCH', 'Bachs payouts are funded in '.PayoutGate::PAYOUT_CURRENCY.', but this amount is in '.$source.'.', FailureClass::NOT_CREATED, [], []);
        }

        if (! $this->connectionReady($destination)) {
            return $this->result(PayoutResult::NOT_CREATED, null, 'ACCOUNT_NOT_CONNECTED', 'The agent has not finished connecting their Bachs payout account.', FailureClass::NOT_CREATED, [], []);
        }

        try {
            $prepared = $this->prepareBeneficiary($destination);
        } catch (GatewayException $e) {
            return $this->failureBeforeMoney($e, []);
        }

        $ids = $prepared['ids'];
        $accountId = (string) $ids[self::KEY]['account'];
        $destinationId = (string) $ids[self::KEY]['destination'];
        $raw = ['account' => $accountId, 'destination' => $destinationId];

        if (! $prepared['usable']) {
            $code = strtolower((string) $prepared['status']) === 'rejected' ? 'DESTINATION_REJECTED' : 'DESTINATION_PENDING_REVIEW';

            return $this->result(PayoutResult::NOT_CREATED, null, $code, $prepared['message'] ?? 'The destination is not approved for payouts yet.', FailureClass::NOT_CREATED, $raw, $ids);
        }

        try {
            $gate = $this->payoutCapabilityGate($accountId);
            if ($gate !== null) {
                return $this->result(
                    PayoutResult::NOT_CREATED,
                    null,
                    'PAYOUTS_NOT_ENABLED',
                    $gate['message'],
                    FailureClass::NOT_CREATED,
                    $raw + ['capabilities' => $gate['capabilities']],
                    $ids,
                );
            }
        } catch (GatewayException $e) {
            return $this->failureBeforeMoney($e, $ids, $raw);
        }

        $feeEstimate = $request->quote && $request->quote->gatewayKey === self::KEY ? $request->quote->providerFee : '0';
        $buffer = Money::round((string) ($this->meta('payout_fee_buffer') ?: '1.00'), 2);
        $fund = Money::round(Money::add(Money::add($net, $feeEstimate, 4), $buffer, 4), 2);

        try {
            $transfer = $this->retryTransient(fn () => $this->client()->post('/v1/transfers', [
                'destination' => $accountId,
                'amount' => $fund,
                'currency' => $source,
                'transfer_group' => $ref,
                'description' => mb_substr($request->narration, 0, 200),
                'metadata' => ['reference' => $ref],
            ], null, $ref.'-transfer'));
        } catch (GatewayException $e) {
            if (! $e->transient) {
                return $this->failureBeforeMoney($e, $ids, $raw);
            }

            $transfer = $this->findTransfer($ref);
            if ($transfer === null) {
                // The transfer may still land. Never sweep or retry elsewhere until it is confirmed.
                return $this->result(PayoutResult::UNKNOWN, null, 'TRANSFER_UNCONFIRMED', 'Bachs did not confirm the funding transfer. Waiting for confirmation before any retry.', null, $raw + ['error' => $e->body], $ids);
            }
        }
        $raw['transfer'] = $this->str($transfer['id'] ?? null);
        $raw['funded'] = $fund;

        $payout = $this->createPayout($request, $accountId, $destinationId, $net, $raw, $ids);
        if ($payout instanceof PayoutResult) {
            return $payout;
        }

        if (strtolower((string) ($payout['status'] ?? '')) !== 'failed') {
            $this->sweep($ref.'-sweep', $accountId);
        }

        return $this->payoutToResult($payout, $raw, $ids);
    }

    public function transferStatus(string $reference, ?string $providerReference = null): PayoutResult
    {
        $accountId = $this->accountForReference($reference);
        if (! $accountId) {
            // Bachs payouts are only created on the agent's recipient account, so none can exist.
            return PayoutResult::notFound('The agent has no Bachs recipient account, so no payout exists.');
        }

        try {
            if ($providerReference) {
                $payout = $this->client()->get('/v1/payouts/'.rawurlencode($providerReference), [], $accountId);
            } else {
                $list = $this->client()->get('/v1/payouts', ['reference' => $reference], $accountId);
                $rows = array_is_list($list) ? $list : (array) ($list['data'] ?? $list['payouts'] ?? []);
                $payout = collect($rows)->first(fn ($row) => (string) ($row['reference'] ?? '') === $reference);
                if (! $payout) {
                    return PayoutResult::notFound('Bachs has no payout with this reference.', ['account' => $accountId]);
                }
            }
        } catch (GatewayException $e) {
            return PayoutResult::unknown($e->getMessage(), ['error' => $e->body], $providerReference);
        }

        return $this->payoutToResult((array) $payout, ['account' => $accountId], []);
    }

    public function reclaimFailedPayout(string $reference, PayoutAccountDetails $destination): bool
    {
        $accountId = $destination->beneficiaryIds[self::KEY]['account'] ?? $this->accountForReference($reference);

        // A new key each hour, so a later retry is a real sweep rather than a replay of the first.
        return $accountId ? $this->sweep($reference.'-reclaim-'.now()->format('YmdH'), (string) $accountId) : true;
    }

    // ---------------------------------------------------------------- internals

    /**
     * @return array<string, mixed>|PayoutResult  the payout row, or a final result when no payout exists
     */
    private function createPayout(PayoutRequest $request, string $accountId, string $destinationId, string $net, array $raw, array $ids): array|PayoutResult
    {
        $ref = $request->reference;
        $crossCurrency = strtoupper($request->destination->currency) !== 'USD';

        for ($try = 0; $try < 2; $try++) {
            $body = ['destination' => $destinationId, 'reference' => $ref];

            try {
                if ($crossCurrency) {
                    $quote = $this->client()->post('/v1/payouts/quotes', $this->quoteBody($net, $request->destination), $accountId);
                    $quoteId = (string) ($quote['quote_id'] ?? $quote['id'] ?? '');
                    if ($quoteId === '') {
                        throw new GatewayException('Bachs did not return a quote.', 'QUOTE_UNAVAILABLE', 422, false, $quote);
                    }
                    $body['quote_id'] = $quoteId;
                    $raw['quote'] = ['id' => $quoteId, 'to_amount' => $quote['to_amount'] ?? null, 'rate' => $quote['exchange_rate'] ?? null];
                } else {
                    $body['amount'] = $net;
                }
            } catch (GatewayException $e) {
                return $this->failureAfterFunding($e, $ref, $accountId, $raw, $ids);
            }

            try {
                return $this->retryTransient(fn () => $this->client()->post('/v1/payouts', $body, $accountId, $try === 0 ? $ref : $ref.'-r'.$try));
            } catch (GatewayException $e) {
                if ($e->transient) {
                    return $this->result(PayoutResult::UNKNOWN, null, null, 'Bachs did not confirm the payout: '.$e->getMessage(), null, $raw + ['error' => $e->body], $ids);
                }
                if ($e->providerCode === 'QUOTE_EXPIRED' && $try === 0) {
                    continue;
                }

                return $this->failureAfterFunding($e, $ref, $accountId, $raw, $ids);
            }
        }

        return $this->failureAfterFunding(new GatewayException('Quote expired twice.', 'QUOTE_EXPIRED', 400), $ref, $accountId, $raw, $ids);
    }

    /** A definitive error after the funding transfer: move the money back, then classify. */
    private function failureAfterFunding(GatewayException $e, string $ref, string $accountId, array $raw, array $ids): PayoutResult
    {
        $raw['error'] = $e->body;

        if (! $this->sweep($ref.'-reverse', $accountId)) {
            return $this->result(
                PayoutResult::FAILED, null, $e->providerCode, $e->getMessage().' Funds are still on the Bachs recipient account.',
                FailureClass::OTHER, $raw, $ids,
            );
        }

        return $this->classified($e, $raw, $ids);
    }

    /** A provider answer before any money moved. */
    private function failureBeforeMoney(GatewayException $e, array $ids, array $raw = []): PayoutResult
    {
        $raw['error'] = $e->body;

        if ($e->transient) {
            return $this->result(PayoutResult::FAILED, null, 'GATEWAY_UNAVAILABLE', 'Bachs could not be reached. No payout was created.', FailureClass::RECOVERABLE, $raw, $ids);
        }

        return $this->classified($e, $raw, $ids);
    }

    private function classified(GatewayException $e, array $raw, array $ids): PayoutResult
    {
        $class = $this->failureClass($e->providerCode);

        return $class === FailureClass::NOT_CREATED
            ? $this->result(PayoutResult::NOT_CREATED, null, $e->providerCode, $e->getMessage(), $class, $raw, $ids)
            : $this->result(PayoutResult::FAILED, null, $e->providerCode, $e->getMessage(), $class, $raw, $ids);
    }

    /**
     * @param  array<string, mixed>  $payout
     */
    private function payoutToResult(array $payout, array $raw, array $ids): PayoutResult
    {
        $status = strtolower((string) ($payout['status'] ?? ''));
        $id = $this->str($payout['id'] ?? null);
        $fee = $this->str($payout['fee'] ?? null);
        $raw['payout'] = $payout;

        return match ($status) {
            'completed', 'paid', 'succeeded' => $this->result(PayoutResult::SUCCEEDED, $id, null, null, null, $raw, $ids, $fee),
            'failed', 'returned', 'cancelled', 'canceled' => $this->result(
                PayoutResult::FAILED, $id, $this->str($payout['failure_code'] ?? 'PAYOUT_FAILED'),
                $this->str($payout['failure_reason'] ?? 'The payout failed at the destination.'),
                FailureClass::OTHER, $raw, $ids, $fee,
            ),
            'pending' => $this->result(PayoutResult::PENDING, $id, null, null, null, $raw, $ids, $fee),
            default => $this->result(PayoutResult::PROCESSING, $id, null, null, null, $raw, $ids, $fee),
        };
    }

    private function result(string $status, ?string $providerReference, ?string $code, ?string $reason, ?string $class, array $raw, array $ids, ?string $fee = null): PayoutResult
    {
        return new PayoutResult(
            status: $status,
            providerReference: $providerReference,
            failureCode: $code,
            failureReason: $reason,
            failureClass: $class,
            fee: $fee,
            raw: $raw,
            beneficiaryIds: $ids,
        );
    }

    /** Move the recipient account's whole available USD balance back to the platform. */
    private function sweep(string $idempotencyKey, string $accountId): bool
    {
        try {
            $available = $this->availableBalance($this->client()->get('/v1/balances', [], $accountId), 'USD') ?? '0';
            if (! Money::isPositive($available, 2)) {
                return true;
            }

            $this->retryTransient(fn () => $this->client()->post('/v1/transfers', [
                'destination' => 'self',
                'amount' => Money::round($available, 2),
                'currency' => 'USD',
                'description' => 'Unused payout funds returned',
            ], $accountId, $idempotencyKey));

            return true;
        } catch (GatewayException $e) {
            Log::warning('bachs.sweep_failed', ['account' => $accountId, 'key' => $idempotencyKey, 'code' => $e->providerCode]);

            return false;
        }
    }

    private function payoutsActive(string $accountId): bool
    {
        return $this->payoutCapabilityGate($accountId) === null;
    }

    /**
     * Live gate for recipient payouts+transfers. Returns null when both are active;
     * otherwise the message and statuses to show admin (never a vague "not active").
     *
     * @return array{message: string, capabilities: array<string, string>}|null
     */
    private function payoutCapabilityGate(string $accountId): ?array
    {
        $listBody = $this->client()->get('/v1/accounts/'.rawurlencode($accountId).'/capabilities');
        $caps = self::normalizeCapabilities($listBody);

        if (($caps['payouts'] ?? null) === 'active' && ($caps['transfers'] ?? null) === 'active') {
            return null;
        }

        // Account object also carries capabilities; merge so a list-shape miss cannot
        // falsely block when the dashboard already shows active (same merge as Connect sync).
        $account = $this->retrieveAccount($accountId);
        $caps = $caps + self::normalizeCapabilities($account);
        if (($caps['payouts'] ?? null) === 'active' && ($caps['transfers'] ?? null) === 'active') {
            return null;
        }

        return [
            'message' => self::describeInactiveCapabilities($listBody !== [] ? $listBody : $account, $caps),
            'capabilities' => array_intersect_key($caps, array_flip(PayoutConnection::REQUIRED_CAPABILITIES)),
        ];
    }

    private function connection(PayoutAccountDetails $destination): ?PayoutConnection
    {
        if ($destination->userId === null) {
            return null;
        }

        $user = \App\Models\User::query()->find($destination->userId);

        return $user ? app(BachsConnectService::class)->connection($user) : PayoutConnection::for($destination->userId, self::KEY);
    }

    private function connectedAccountId(PayoutAccountDetails $destination): ?string
    {
        $accountId = $this->connection($destination)?->account_id;

        return filled($accountId) ? (string) $accountId : null;
    }

    /** Stored readiness, re-read from Bachs once when it looks stale so a missed webhook does not block payouts. */
    private function connectionReady(PayoutAccountDetails $destination): bool
    {
        $connection = $this->connection($destination);
        if (! $connection || blank($connection->account_id)) {
            return false;
        }
        if (! $connection->matchesEnvironment($this->environment())) {
            return false;
        }
        if ($connection->isActive()) {
            return true;
        }

        try {
            return app(BachsConnectService::class)->sync($connection)->isActive();
        } catch (GatewayException $e) {
            Log::warning('bachs.connect.sync_failed', ['user_id' => $connection->user_id, 'code' => $e->providerCode]);

            return false;
        }
    }

    /** Whether this country/method can use account `fields.payout_destination` (local rails). */
    public function usesInlinePayoutDestination(string $country, string $method = 'bank_account'): bool
    {
        $schema = (string) config(
            'payout_methods.countries.'.strtoupper($country).'.methods.'.$method.'.schema',
            'local_bank',
        );

        return ! in_array($schema, self::API_ONLY_SCHEMAS, true);
    }

    private function requiresApiDestination(PayoutAccountDetails $d): bool
    {
        return ! $this->usesInlinePayoutDestination($d->country, $d->method);
    }

    /**
     * A usable destination the agent added during hosted onboarding that matches this payout
     * account (same currency and type, same last four digits).
     *
     * @return array<string, mixed>|null
     */
    private function hostedDestination(string $accountId, PayoutAccountDetails $d): ?array
    {
        $number = preg_replace('/\D/', '', (string) $d->accountNumber());
        if (strlen($number) < 4) {
            return null;
        }
        $last4 = substr($number, -4);
        $type = $d->method === 'mobile_money' ? 'mobile_money' : 'bank_account';

        foreach ($this->listDestinations($accountId) as $row) {
            if (strtoupper((string) ($row['currency'] ?? '')) !== strtoupper($d->currency)) {
                continue;
            }
            if (strtolower((string) ($row['type'] ?? $type)) !== $type) {
                continue;
            }
            $theirs = preg_replace('/\D/', '', (string) ($row['last4'] ?? $row['account_number_last4'] ?? $row['account_number'] ?? $row['phone_number'] ?? ''));
            if ($theirs !== '' && substr($theirs, -4) === $last4 && ($row['is_usable'] ?? false)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function retryTransient(callable $call): mixed
    {
        try {
            return $call();
        } catch (GatewayException $e) {
            if (! $e->transient) {
                throw $e;
            }
            usleep(app()->runningUnitTests() ? 0 : 500_000);

            return $call();
        }
    }

    /**
     * Collection calls feed the circuit breaker so checkout can hide Bachs during an outage.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function tracked(callable $call): mixed
    {
        try {
            $result = $call();
            $this->health->recordSuccess(self::KEY);

            return $result;
        } catch (GatewayException $e) {
            if ($e->transient) {
                $this->health->recordFailure(self::KEY);
            }
            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    private function findSessionByReference(string $reference): ?array
    {
        $list = $this->tracked(fn () => $this->client()->get('/v1/checkout-sessions', ['reference' => $reference]));
        $rows = array_is_list($list) ? $list : (array) ($list['data'] ?? $list['checkout_sessions'] ?? []);

        return collect($rows)->first(fn ($row) => (string) ($row['reference'] ?? '') === $reference);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function sessionToEvent(array $session, string $reference): PaymentEvent
    {
        $sessionRef = $this->str($session['reference'] ?? $session['metadata']['reference'] ?? null);
        $checkoutId = $this->str($session['checkout_id'] ?? $session['id'] ?? null);
        // Prefer checkout price over charge amount: crypto overpays (e.g. $10.15 for a $10 order)
        // should still match the order total. Bachs ACCEPTED/OVERPAID means the money is in.
        $amount = $this->str(
            $session['pricing']['amount']
                ?? $session['amount']
                ?? $session['charge']['amount']
                ?? $session['settlement_amount']
                ?? null
        );
        $currency = $this->str(
            $session['pricing']['currency']
                ?? $session['currency']
                ?? $session['charge']['currency']
                ?? $session['settlement_currency']
                ?? null
        );

        if ($sessionRef !== null && $sessionRef !== $reference) {
            return new PaymentEvent(PaymentEvent::TYPE_COLLECTION, self::KEY, PaymentEvent::STATUS_FAILED, $reference, $checkoutId, $amount, $currency,
                failureCode: 'REFERENCE_MISMATCH', failureReason: 'The checkout belongs to a different reference.', raw: $session);
        }

        $status = strtolower((string) ($session['status'] ?? ''));
        $paymentStatus = strtoupper((string) ($session['payment']['status'] ?? $session['charge']['status'] ?? $session['payment_status'] ?? ''));

        $paid = in_array($status, ['complete', 'completed', 'paid', 'succeeded'], true)
            || in_array($paymentStatus, ['SUCCEEDED', 'PAID', 'ACCEPTED', 'OVERPAID'], true)
            || in_array(strtolower((string) ($session['payment_status'] ?? '')), ['paid', 'succeeded'], true);

        $state = match (true) {
            $paid => PaymentEvent::STATUS_SUCCEEDED,
            in_array($status, ['expired', 'cancelled', 'canceled', 'failed'], true) => PaymentEvent::STATUS_FAILED,
            default => PaymentEvent::STATUS_PENDING,
        };

        return new PaymentEvent(PaymentEvent::TYPE_COLLECTION, self::KEY, $state, $reference, $checkoutId, $amount, $currency,
            failureCode: $state === PaymentEvent::STATUS_FAILED ? strtoupper($status) : null, raw: $session);
    }

    /**
     * @return array<string, mixed>
     */
    private function destinationBody(PayoutAccountDetails $d): array
    {
        $country = strtoupper($d->country);
        $currency = strtoupper($d->currency);
        $schema = (string) config("payout_methods.countries.$country.methods.{$d->method}.schema", 'local_bank');
        $name = mb_substr($d->holderName, 0, 120);
        $base = [
            'currency' => $currency,
            'account_name' => $name,
            'name' => 'Payout '.$country.' '.substr(sha1($this->fingerprint($d)), 0, 6),
            'default_for_currency' => $d->isPrimary,
        ];

        return match ($schema) {
            'mobile_money' => $base + [
                'type' => 'mobile_money',
                'mobile_provider' => (string) ($d->field('mobile_provider') ?? $d->bankCode),
                'phone_number' => (string) $d->field('phone_number'),
            ],
            'us_bank' => $base + [
                'type' => 'bank_account',
                'scheme' => (string) $d->field('scheme', 'ach'),
                'bank_name' => (string) ($d->field('bank_name') ?? $d->bankName),
                'routing_number' => (string) $d->field('routing_number'),
                'account_number' => (string) $d->field('account_number'),
                'bank_address' => [
                    'line1' => (string) $d->field('bank_address_line1'),
                    'city' => (string) $d->field('bank_address_city'),
                    'state' => strtoupper((string) $d->field('bank_address_state')),
                    'postal_code' => (string) $d->field('bank_address_postal_code'),
                    'country' => 'US',
                ],
            ],
            'gb_bank' => $base + [
                'type' => 'bank_account',
                'bank_name' => (string) ($d->field('bank_name') ?? $d->bankName),
                'sort_code' => (string) $d->field('sort_code'),
                'account_number' => (string) $d->field('account_number'),
            ],
            'sepa' => $base + [
                'type' => 'bank_account',
                'bank_name' => (string) ($d->field('bank_name') ?? $d->bankName),
                'iban' => (string) $d->field('iban'),
                'swift_bic' => strtoupper((string) $d->field('swift_bic')),
            ],
            'ca_bank' => $base + [
                'type' => 'bank_account',
                'scheme' => (string) $d->field('scheme', 'eft'),
                'bank_name' => (string) ($d->field('bank_name') ?? $d->bankName),
                'institution_number' => (string) $d->field('institution_number'),
                'transit_number' => (string) $d->field('transit_number'),
                'account_number' => (string) $d->field('account_number'),
            ],
            default => $base + [
                'type' => 'bank_account',
                'bank_code' => (string) ($d->bankCode ?? $d->field('bank_code')),
                'account_number' => (string) $d->field('account_number'),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteBody(string $amount, PayoutAccountDetails $destination): array
    {
        return [
            'from_currency' => 'USD',
            'to_currency' => strtoupper($destination->currency),
            'amount' => Money::round($amount, 2),
            'payout_method' => $destination->method === 'mobile_money' ? 'MOBILE_MONEY' : 'BANK_TRANSFER',
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function availableBalance(array $body, string $currency): ?string
    {
        $currency = strtoupper($currency);
        $rows = array_is_list($body) ? $body : (array) ($body['balances'] ?? $body['data'] ?? $body['available'] ?? []);

        foreach ($rows as $key => $row) {
            if (is_array($row) && strtoupper((string) ($row['currency'] ?? (is_string($key) ? $key : ''))) === $currency) {
                $value = $row['available_balance'] ?? $row['available'] ?? $row['amount'] ?? null;

                return $value === null ? null : Money::round((string) $value, 2);
            }
        }

        return null;
    }

    /**
     * The platform transfer for an attempt, found by its transfer group. Null when it is not
     * listed or the lookup failed.
     *
     * @return array<string, mixed>|null
     */
    private function findTransfer(string $reference): ?array
    {
        try {
            $list = $this->client()->get('/v1/transfers', ['transfer_group' => $reference]);
        } catch (GatewayException $e) {
            Log::warning('bachs.transfer_lookup_failed', ['reference' => $reference, 'code' => $e->providerCode]);

            return null;
        }

        $rows = array_is_list($list) ? $list : (array) ($list['data'] ?? $list['transfers'] ?? []);
        $row = collect($rows)->first(fn ($row) => is_array($row) && (
            (string) ($row['transfer_group'] ?? '') === $reference
            || (string) ($row['metadata']['reference'] ?? '') === $reference
        ));

        return is_array($row) ? $row : null;
    }

    private function accountForReference(string $reference): ?string
    {
        $attempt = WithdrawalPayoutAttempt::query()
            ->where('gateway_key', self::KEY)
            ->where('reference', $reference)
            ->first();
        if (! $attempt) {
            return null;
        }

        $account = $attempt->response_snapshot['account'] ?? null;
        if ($account) {
            return (string) $account;
        }

        $ids = (array) ($attempt->withdrawal?->payoutAccount?->provider_beneficiary_ids ?? []);
        if (isset($ids[self::KEY]['account'])) {
            return (string) $ids[self::KEY]['account'];
        }

        $userId = $attempt->withdrawal?->user_id;
        $connected = $userId ? PayoutConnection::for((int) $userId, self::KEY)?->account_id : null;

        return filled($connected) ? (string) $connected : null;
    }

    /** @return array<string, list<string>> */
    private function routes(): array
    {
        $routes = self::PAYOUT_ROUTES;
        foreach ((array) config('payout_methods.countries', []) as $country => $row) {
            if (($row['currency'] ?? null) === 'EUR') {
                $routes[$country] = ['bank_account'];
            }
        }

        return $routes;
    }

    private function fingerprint(PayoutAccountDetails $d): string
    {
        $fields = $d->fields;
        ksort($fields);

        return substr(sha1(implode('|', [strtoupper($d->country), $d->method, (string) $d->bankCode, json_encode($fields), $d->holderName])), 0, 20);
    }

    private function str(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function client(): BachsClient
    {
        $key = $this->credential('secret_key');
        if (! $key) {
            throw new GatewayException('Bachs is not configured.', 'UNAUTHORIZED', 401);
        }

        return $this->client ??= new BachsClient($key, $this->isLive(), $this->timeout());
    }

    public function refreshProvider(): void
    {
        parent::refreshProvider();
        $this->client = null;
    }
}
