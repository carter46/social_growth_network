<?php

namespace App\Modules\Wallet\Payments\Flutterwave;

use App\Modules\Wallet\Payments\AbstractGatewayDriver;
use App\Modules\Wallet\Payments\Contracts\CollectionGateway;
use App\Modules\Wallet\Payments\Contracts\PayoutGateway;
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

/**
 * Flutterwave v3: hosted Standard checkout, transfers debited from the USD balance.
 * Transfers are sent in the destination currency with debit_currency USD, so the USD net
 * amount is converted with /transfers/rates first. References are unique at Flutterwave,
 * which makes a repeated reference a duplicate rather than a second payment.
 */
class FlutterwaveDriver extends AbstractGatewayDriver implements CollectionGateway, PayoutGateway
{
    public const KEY = 'flutterwave';

    /** Countries Flutterwave lists banks for. Routes are only offered once coverage verifies them. */
    private const BANK_COUNTRIES = ['NG', 'GH', 'KE', 'UG', 'TZ', 'ZA'];

    /** International bank routes: never reported by coverage(); an admin verifies them manually. */
    private const MANUAL_BANK_COUNTRIES = ['US', 'GB'];

    /** Mobile money networks (account_bank values) per country. */
    private const MOBILE_NETWORKS = [
        'GH' => ['MTN' => 'MTN', 'VODAFONE' => 'Telecel (Vodafone)', 'AIRTEL' => 'AirtelTigo'],
        'KE' => ['MPS' => 'M-Pesa'],
        'UG' => ['MPS' => 'MTN Mobile Money', 'AIRTEL' => 'Airtel Money'],
        'TZ' => ['MPS' => 'M-Pesa', 'AIRTEL' => 'Airtel Money', 'TIGO' => 'Tigo Pesa'],
        'RW' => ['MPS' => 'MTN Mobile Money', 'AIRTEL' => 'Airtel Money'],
    ];

    private const CHECKOUT_CURRENCIES = ['USD', 'NGN', 'GHS', 'KES', 'UGX', 'TZS', 'RWF', 'ZAR', 'XAF', 'XOF', 'ZMW', 'EUR', 'GBP'];

    private const NOT_CREATED_CODES = ['UNAUTHORIZED', 'FORBIDDEN', 'TRANSFER_NOT_ALLOWED', 'CURRENCY_NOT_SUPPORTED'];

    private ?FlutterwaveClient $client = null;

    public function __construct(private GatewayHealth $health) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function displayName(): string
    {
        return 'Flutterwave';
    }

    public function credentialFields(): array
    {
        return [
            $this->environmentField(),
            ['name' => 'secret_key', 'label' => 'Secret key', 'type' => 'secret', 'required' => true, 'store' => 'credentials',
                'hint' => 'FLWSECK_TEST-… for test mode, FLWSECK-… for live.'],
            ['name' => 'webhook_hash', 'label' => 'Webhook secret hash', 'type' => 'secret', 'required' => true, 'store' => 'credentials',
                'hint' => 'The "Secret hash" set under Settings > Webhooks. Sent back in the verif-hash header.'],
            ['name' => 'checkout_title', 'label' => 'Checkout title', 'type' => 'text', 'required' => false, 'store' => 'meta',
                'hint' => 'Shown on the Flutterwave payment page. Defaults to the site name.'],
        ];
    }

    public function testConnection(): array
    {
        if (! $this->credential('secret_key')) {
            return ['ok' => false, 'message' => 'Enter the secret key first.'];
        }

        try {
            $balance = $this->balance('USD');
        } catch (GatewayException $e) {
            return ['ok' => false, 'message' => 'Flutterwave said: '.$e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Connected. USD balance: '.($balance !== null ? money($balance) : 'not available').'.'];
    }

    public static function codeForMessage(string $message, int $status): string
    {
        $m = strtolower($message);

        return match (true) {
            $status === 401 => 'UNAUTHORIZED',
            $status === 403 => 'FORBIDDEN',
            str_contains($m, 'insufficient') => 'INSUFFICIENT_BALANCE',
            str_contains($m, 'duplicate') => 'DUPLICATE_REFERENCE',
            str_contains($m, 'invalid account') || str_contains($m, 'could not resolve') || str_contains($m, 'account resolve') => 'INVALID_ACCOUNT',
            str_contains($m, 'not enabled') || str_contains($m, 'not allowed') || str_contains($m, 'not permitted') => 'TRANSFER_NOT_ALLOWED',
            str_contains($m, 'currency') && str_contains($m, 'not supported') => 'CURRENCY_NOT_SUPPORTED',
            str_contains($m, 'no transaction') || str_contains($m, 'not found') => 'NOT_FOUND',
            default => 'REQUEST_REJECTED',
        };
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

        $minutes = max(1, min(1440, $request->expiresInMinutes));

        $body = $this->tracked(fn () => $this->client()->post('/payments', [
            'tx_ref' => $request->reference,
            'amount' => Money::round($request->amount, 2),
            'currency' => strtoupper($request->currency),
            'redirect_url' => $request->callbackUrl,
            'customer' => $customer,
            'customizations' => [
                'title' => (string) ($this->meta('checkout_title') ?: config('app.name')),
                'description' => mb_substr($request->description, 0, 150),
            ],
            'meta' => $request->metadata + ['reference' => $request->reference],
            'session_duration' => $minutes,
        ]));

        $link = (string) ($body['data']['link'] ?? '');
        if ($link === '') {
            throw new GatewayException('Flutterwave did not return a payment link.', null, null, false, $body);
        }

        return new CheckoutSession($link, null, CarbonImmutable::now()->addMinutes($minutes), $body);
    }

    public function verify(string $reference, ?string $providerReference = null): PaymentEvent
    {
        try {
            $body = $this->tracked(fn () => $this->client()->get('/transactions/verify_by_reference', ['tx_ref' => $reference]));
        } catch (GatewayException $e) {
            if (! $e->transient && in_array($e->providerCode, ['NOT_FOUND', 'REQUEST_REJECTED'], true)) {
                return new PaymentEvent(PaymentEvent::TYPE_COLLECTION, self::KEY, PaymentEvent::STATUS_PENDING, $reference, $providerReference);
            }
            throw $e;
        }

        $data = (array) ($body['data'] ?? []);
        $txRef = (string) ($data['tx_ref'] ?? '');
        $id = $this->str($data['id'] ?? null);

        if ($txRef !== '' && $txRef !== $reference) {
            return new PaymentEvent(PaymentEvent::TYPE_COLLECTION, self::KEY, PaymentEvent::STATUS_FAILED, $reference, $id,
                failureCode: 'REFERENCE_MISMATCH', failureReason: 'The transaction belongs to a different reference.', raw: $data);
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        $state = match ($status) {
            'successful' => PaymentEvent::STATUS_SUCCEEDED,
            'failed', 'cancelled' => PaymentEvent::STATUS_FAILED,
            default => PaymentEvent::STATUS_PENDING,
        };

        return new PaymentEvent(
            PaymentEvent::TYPE_COLLECTION, self::KEY, $state, $reference, $id,
            $this->str($data['amount'] ?? null), $this->str($data['currency'] ?? null),
            failureCode: $state === PaymentEvent::STATUS_FAILED ? strtoupper($status) : null,
            failureReason: $this->str($data['processor_response'] ?? null),
            raw: $data,
        );
    }

    // ---------------------------------------------------------------- webhooks

    public function parseWebhook(Request $request): PaymentEvent
    {
        $hash = $this->credential('webhook_hash');
        $header = (string) $request->header('verif-hash', '');

        if (! $hash || $header === '' || ! hash_equals($hash, $header)) {
            throw new InvalidWebhookSignature('Flutterwave verif-hash does not match.');
        }

        return $this->normalizeWebhookPayload(json_decode($request->getContent(), true) ?: []);
    }

    public function normalizeWebhookPayload(array $payload): PaymentEvent
    {
        $event = (string) ($payload['event'] ?? '');
        $data = (array) ($payload['data'] ?? []);
        $status = strtoupper((string) ($data['status'] ?? ''));
        $eventId = isset($data['id']) ? $event.':'.$data['id'].':'.$status : null;

        if ($event === 'charge.completed') {
            return new PaymentEvent(
                PaymentEvent::TYPE_COLLECTION, self::KEY,
                $status === 'SUCCESSFUL' ? PaymentEvent::STATUS_SUCCEEDED : ($status === 'FAILED' ? PaymentEvent::STATUS_FAILED : PaymentEvent::STATUS_PENDING),
                $this->str($data['tx_ref'] ?? null), $this->str($data['id'] ?? null),
                $this->str($data['amount'] ?? null), $this->str($data['currency'] ?? null),
                $eventId, raw: $payload,
            );
        }

        if ($event === 'transfer.completed') {
            return new PaymentEvent(
                PaymentEvent::TYPE_PAYOUT, self::KEY,
                $status === 'SUCCESSFUL' ? PaymentEvent::STATUS_SUCCEEDED : ($status === 'FAILED' ? PaymentEvent::STATUS_FAILED : PaymentEvent::STATUS_PENDING),
                $this->str($data['reference'] ?? null), $this->str($data['id'] ?? null),
                $this->str($data['amount'] ?? null), $this->str($data['currency'] ?? null),
                $eventId,
                $status === 'FAILED' ? 'TRANSFER_FAILED' : null,
                $this->str($data['complete_message'] ?? null),
                $payload,
            );
        }

        return PaymentEvent::ignored(self::KEY, $eventId, $payload);
    }

    // ---------------------------------------------------------------- payout catalogue

    public function payoutCountries(): array
    {
        return array_values(array_unique(array_merge(
            self::BANK_COUNTRIES,
            self::MANUAL_BANK_COUNTRIES,
            array_keys(self::MOBILE_NETWORKS),
            $this->sepaCountries(),
        )));
    }

    public function payoutMethods(string $country): array
    {
        $country = strtoupper($country);
        $methods = [];
        if (in_array($country, self::BANK_COUNTRIES, true) || in_array($country, self::MANUAL_BANK_COUNTRIES, true) || in_array($country, $this->sepaCountries(), true)) {
            $methods[] = 'bank_account';
        }
        if (isset(self::MOBILE_NETWORKS[$country])) {
            $methods[] = 'mobile_money';
        }

        return $methods;
    }

    public function listBanks(string $country, string $method = 'bank_account'): array
    {
        $country = strtoupper($country);

        if ($method === 'mobile_money') {
            return collect(self::MOBILE_NETWORKS[$country] ?? [])
                ->map(fn ($name, $code) => ['name' => $name, 'code' => (string) $code])
                ->values()
                ->all();
        }

        $body = $this->client()->get('/banks/'.$country);

        return collect((array) ($body['data'] ?? []))
            ->map(fn ($b) => ['name' => (string) ($b['name'] ?? ''), 'code' => (string) ($b['code'] ?? '')])
            ->filter(fn ($b) => $b['name'] !== '' && $b['code'] !== '')
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function supportsResolve(string $country, string $method): bool
    {
        $country = strtoupper($country);

        return ($country === 'NG' && $method === 'bank_account')
            || ($country === 'GH' && in_array($method, ['bank_account', 'mobile_money'], true));
    }

    public function resolveAccount(PayoutAccountDetails $account): ResolvedAccount
    {
        $number = $account->method === 'mobile_money' ? $account->field('phone_number') : $account->field('account_number');
        $bank = $account->method === 'mobile_money'
            ? ($account->field('mobile_provider') ?? $account->bankCode)
            : ($account->bankCode ?? $account->field('bank_code'));

        $body = $this->client()->post('/accounts/resolve', [
            'account_number' => (string) $number,
            'account_bank' => (string) $bank,
        ]);

        $name = trim((string) ($body['data']['account_name'] ?? ''));
        if ($name === '') {
            throw new GatewayException('Flutterwave did not return an account name.', 'INVALID_ACCOUNT', 422, false, $body);
        }

        return new ResolvedAccount($name, self::KEY, (array) $body['data']);
    }

    public function coverage(): array
    {
        $routes = [];
        foreach (array_unique(array_merge(self::BANK_COUNTRIES, array_keys(self::MOBILE_NETWORKS))) as $country) {
            $currency = (string) config("payout_methods.countries.$country.currency", '');
            if ($currency === '') {
                continue;
            }

            try {
                $banks = $this->listBanks($country);
            } catch (GatewayException) {
                continue;
            }
            if ($banks === []) {
                continue;
            }

            if (in_array($country, self::BANK_COUNTRIES, true)) {
                $routes[] = new CoverageRoute($country, 'bank_account', $currency, $this->supportsResolve($country, 'bank_account'));
            }
            if (isset(self::MOBILE_NETWORKS[$country])) {
                $routes[] = new CoverageRoute($country, 'mobile_money', $currency, $this->supportsResolve($country, 'mobile_money'));
            }
        }

        return $routes;
    }

    public function failureClass(?string $code): string
    {
        $code = strtoupper((string) $code);

        return match (true) {
            in_array($code, self::NOT_CREATED_CODES, true) => FailureClass::NOT_CREATED,
            $code === 'INSUFFICIENT_BALANCE' => FailureClass::RECOVERABLE,
            $code === 'INVALID_ACCOUNT' => FailureClass::ACCOUNT_DETAILS,
            default => FailureClass::OTHER,
        };
    }

    public function balance(string $currency = 'USD'): ?string
    {
        $body = $this->client()->get('/balances/'.strtoupper($currency));
        $value = $body['data']['available_balance'] ?? null;

        return $value === null ? null : Money::round((string) $value, 2);
    }

    public function quoteTransfer(string $amount, PayoutAccountDetails $destination): ?PayoutQuote
    {
        $currency = strtoupper($destination->currency);
        $usdPerUnit = $currency === 'USD' ? '1' : $this->usdPerUnit($currency);
        $destinationAmount = $currency === 'USD' ? Money::round($amount, 2) : $this->toDestination($amount, $usdPerUnit, $currency);

        $body = $this->client()->get('/transfers/fee', [
            'amount' => $destinationAmount,
            'currency' => $currency,
            'type' => $destination->method === 'mobile_money' ? 'mobilemoney' : 'account',
        ]);
        $row = collect((array) ($body['data'] ?? []))->first();
        if (! is_array($row) || ! isset($row['fee'])) {
            return null;
        }

        $fee = (string) $row['fee'];
        if (strtolower((string) ($row['fee_type'] ?? 'value')) === 'percentage') {
            $fee = Money::div(Money::mul($destinationAmount, $fee, 6), '100', 6);
        }
        $feeCurrency = strtoupper((string) ($row['currency'] ?? $currency));
        $feeUsd = $feeCurrency === 'USD' ? $fee : Money::mul($fee, $usdPerUnit, 6);

        return new PayoutQuote(
            providerFee: $this->ceilCents($feeUsd),
            feeCurrency: 'USD',
            destinationCurrency: $currency,
            fxRate: $currency === 'USD' ? '1' : Money::div('1', $usdPerUnit, 6),
            destinationAmount: $destinationAmount,
            expiresAt: CarbonImmutable::now()->addMinutes((int) config('payments.payout.quote_cache_minutes', 5)),
            source: PayoutQuote::SOURCE_LIVE,
            gatewayKey: self::KEY,
            quotedAt: CarbonImmutable::now(),
        );
    }

    // ---------------------------------------------------------------- payouts

    public function initiateTransfer(PayoutRequest $request): PayoutResult
    {
        $d = $request->destination;
        $currency = strtoupper($d->currency);
        $net = Money::round($request->amount, 2);

        if (! PayoutGate::currencyAllowed($request->currency)) {
            return PayoutResult::notCreated('CURRENCY_MISMATCH', 'Flutterwave payouts are debited in '.PayoutGate::PAYOUT_CURRENCY.', but this amount is in '.strtoupper($request->currency).'.');
        }

        try {
            $amount = $currency === 'USD' ? $net : $this->toDestination($net, $this->usdPerUnit($currency), $currency);
        } catch (GatewayException $e) {
            return $e->transient
                ? PayoutResult::failed('GATEWAY_UNAVAILABLE', 'Flutterwave could not be reached for a rate. No transfer was created.', FailureClass::RECOVERABLE, ['error' => $e->body])
                : $this->classified($e);
        }

        $body = $this->transferBody($request, $amount, $currency);

        try {
            $response = $this->client()->post('/transfers', $body);
        } catch (GatewayException $e) {
            if ($e->transient) {
                return PayoutResult::unknown('Flutterwave did not confirm the transfer: '.$e->getMessage(), ['error' => $e->body, 'amount' => $amount, 'currency' => $currency]);
            }

            return $this->classified($e);
        }

        $data = (array) ($response['data'] ?? []);

        return $this->transferToResult($data + ['_sent' => ['amount' => $amount, 'currency' => $currency]]);
    }

    public function transferStatus(string $reference, ?string $providerReference = null): PayoutResult
    {
        try {
            if ($providerReference) {
                $data = (array) ($this->client()->get('/transfers/'.rawurlencode($providerReference))['data'] ?? []);
            } else {
                $data = null;
                for ($page = 1; $page <= 3 && $data === null; $page++) {
                    $rows = (array) ($this->client()->get('/transfers', ['page' => $page])['data'] ?? []);
                    $data = collect($rows)->first(fn ($row) => (string) ($row['reference'] ?? '') === $reference);
                    if ($rows === []) {
                        break;
                    }
                }
                if (! $data) {
                    return PayoutResult::notFound('Flutterwave has no transfer with this reference in recent transfers.');
                }
            }
        } catch (GatewayException $e) {
            return PayoutResult::unknown($e->getMessage(), ['error' => $e->body], $providerReference);
        }

        return $this->transferToResult((array) $data);
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $data
     */
    private function transferToResult(array $data): PayoutResult
    {
        $status = strtoupper((string) ($data['status'] ?? ''));
        $id = $this->str($data['id'] ?? null);
        $fee = $this->str($data['fee'] ?? null);

        return match ($status) {
            'SUCCESSFUL' => new PayoutResult(PayoutResult::SUCCEEDED, $id, fee: $fee, raw: $data),
            'FAILED' => new PayoutResult(
                PayoutResult::FAILED, $id, 'TRANSFER_FAILED',
                $this->str($data['complete_message'] ?? 'The transfer failed.'),
                self::codeForMessage((string) ($data['complete_message'] ?? ''), 200) === 'INSUFFICIENT_BALANCE' ? FailureClass::RECOVERABLE : FailureClass::OTHER,
                fee: $fee, raw: $data,
            ),
            'NEW' => new PayoutResult(PayoutResult::PENDING, $id, fee: $fee, raw: $data),
            default => new PayoutResult(PayoutResult::PROCESSING, $id, fee: $fee, raw: $data),
        };
    }

    private function classified(GatewayException $e): PayoutResult
    {
        $class = $this->failureClass($e->providerCode);

        return $class === FailureClass::NOT_CREATED
            ? PayoutResult::notCreated($e->providerCode, $e->getMessage(), ['error' => $e->body])
            : PayoutResult::failed($e->providerCode, $e->getMessage(), $class, ['error' => $e->body]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transferBody(PayoutRequest $request, string $amount, string $currency): array
    {
        $d = $request->destination;
        $country = strtoupper($d->country);
        $schema = (string) config("payout_methods.countries.$country.methods.{$d->method}.schema", 'local_bank');

        $base = [
            'amount' => $amount,
            'currency' => $currency,
            'debit_currency' => strtoupper($request->currency),
            'narration' => mb_substr($request->narration, 0, 100),
            'reference' => $request->reference,
            'beneficiary_name' => $d->holderName,
        ];

        if ($webhook = $this->webhookUrl()) {
            $base['callback_url'] = $webhook;
        }

        return match ($schema) {
            'mobile_money' => $base + [
                'account_bank' => (string) ($d->field('mobile_provider') ?? $d->bankCode),
                'account_number' => (string) $d->field('phone_number'),
            ],
            'us_bank' => $base + ['meta' => [[
                'AccountNumber' => (string) $d->field('account_number'),
                'RoutingNumber' => (string) $d->field('routing_number'),
                'SwiftCode' => '',
                'BankName' => (string) ($d->field('bank_name') ?? $d->bankName),
                'BeneficiaryName' => $d->holderName,
                'BeneficiaryAddress' => trim(implode(', ', array_filter([
                    $d->field('bank_address_line1'), $d->field('bank_address_city'),
                    $d->field('bank_address_state'), $d->field('bank_address_postal_code'),
                ]))),
                'BeneficiaryCountry' => 'US',
            ]]],
            'gb_bank' => $base + ['meta' => [[
                'AccountNumber' => (string) $d->field('account_number'),
                'SortCode' => (string) $d->field('sort_code'),
                'BankName' => (string) ($d->field('bank_name') ?? $d->bankName),
                'BeneficiaryName' => $d->holderName,
                'BeneficiaryCountry' => 'GB',
            ]]],
            'sepa' => $base + ['meta' => [[
                'AccountNumber' => (string) $d->field('iban'),
                'SwiftCode' => strtoupper((string) $d->field('swift_bic')),
                'BankName' => (string) ($d->field('bank_name') ?? $d->bankName),
                'BeneficiaryName' => $d->holderName,
                'BeneficiaryCountry' => $country,
            ]]],
            default => $base + [
                'account_bank' => (string) ($d->bankCode ?? $d->field('bank_code')),
                'account_number' => (string) $d->field('account_number'),
            ],
        };
    }

    /** USD needed per one unit of the destination currency, from /transfers/rates. */
    private function usdPerUnit(string $currency): string
    {
        $body = $this->client()->get('/transfers/rates', [
            'amount' => '1000',
            'destination_currency' => $currency,
            'source_currency' => 'USD',
        ]);

        $source = (string) ($body['data']['source']['amount'] ?? '');
        $destination = (string) ($body['data']['destination']['amount'] ?? '');
        if (! is_numeric($source) || ! is_numeric($destination) || ! Money::isPositive($destination, 6)) {
            throw new GatewayException('Flutterwave did not return a usable rate for '.$currency.'.', 'CURRENCY_NOT_SUPPORTED', 422, false, $body);
        }

        return Money::div($source, $destination, 10);
    }

    /** Destination amount the USD net buys, rounded down so the USD debit never exceeds it. */
    private function toDestination(string $usd, string $usdPerUnit, string $currency): string
    {
        $decimals = in_array($currency, ['UGX', 'RWF', 'XAF', 'XOF', 'TZS'], true) ? 0 : 2;
        $raw = bcdiv(Money::normalize($usd), $usdPerUnit, $decimals);

        return $decimals === 0 ? $raw : Money::round($raw, 2);
    }

    private function ceilCents(string $value): string
    {
        $cents = bcmul(Money::normalize($value), '100', 6);
        $whole = bcadd($cents, '0', 0);
        if (bccomp($cents, $whole, 6) > 0) {
            $whole = bcadd($whole, '1', 0);
        }

        return bcdiv($whole, '100', 2);
    }

    /** @return list<string> */
    private function sepaCountries(): array
    {
        return collect((array) config('payout_methods.countries', []))
            ->filter(fn ($row) => ($row['currency'] ?? null) === 'EUR')
            ->keys()
            ->all();
    }

    /**
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

    private function str(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function client(): FlutterwaveClient
    {
        $key = $this->credential('secret_key');
        if (! $key) {
            throw new GatewayException('Flutterwave is not configured.', 'UNAUTHORIZED', 401);
        }

        return $this->client ??= new FlutterwaveClient($key, $this->timeout());
    }

    public function refreshProvider(): void
    {
        parent::refreshProvider();
        $this->client = null;
    }
}
