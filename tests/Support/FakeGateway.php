<?php

namespace Tests\Support;

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
use App\Modules\Wallet\Payments\FailureClass;
use App\Modules\Wallet\Payments\GatewayRegistry;
use Illuminate\Http\Request;

/**
 * Scriptable gateway for tests. Register with FakeGateway::install(['fake_a', 'fake_b']).
 */
class FakeGateway implements CollectionGateway, PayoutGateway
{
    public bool $configured = true;

    /** @var list<string> */
    public array $currencyList = ['USD'];

    /** Status returned by verify(): succeeded, pending or failed. */
    public string $verifyStatus = PaymentEvent::STATUS_SUCCEEDED;

    public ?string $verifyAmount = null;

    public ?string $verifyCurrency = 'USD';

    /** @var list<CheckoutRequest> */
    public array $checkouts = [];

    /** @var list<PayoutResult> queued results for initiateTransfer; the last one repeats */
    public array $transferResults = [];

    /** @var list<PayoutRequest> */
    public array $transfers = [];

    /** @var array<string, PayoutResult> status by our attempt reference */
    public array $statuses = [];

    /** When true, a lookup for an unknown reference cannot confirm that nothing exists. */
    public bool $lookupUnconfirmed = false;

    /** Number of upcoming webhook processing runs that throw. */
    public int $processFailures = 0;

    public ?PayoutQuote $quote = null;

    public ?string $balanceAmount = '100000.00';

    public ?string $resolvedName = null;

    /** @var list<CoverageRoute> */
    public array $coverageRoutes = [];

    public bool $coverageThrows = false;

    /** @var array<string, string> failure code => FailureClass */
    public array $failureClasses = [
        'NOT_CREATED' => FailureClass::NOT_CREATED,
        'INVALID_ACCOUNT' => FailureClass::ACCOUNT_DETAILS,
        'INSUFFICIENT_BALANCE' => FailureClass::RECOVERABLE,
    ];

    /** @var list<array{name: string, code: string}> */
    public array $banks = [
        ['name' => 'Example Bank', 'code' => '001'],
        ['name' => 'Sample Bank', 'code' => '002'],
    ];

    public function __construct(private string $gatewayKey = 'fake') {}

    /**
     * Replace every installed driver with fakes and make them the collection and payout order.
     *
     * @param  list<string>  $keys
     * @return array<string, self>
     */
    public static function install(array $keys = ['fake']): array
    {
        $fakes = [];
        foreach ($keys as $key) {
            $fakes[$key] = new self($key);
        }

        $registry = app(GatewayRegistry::class);
        $registry->setDrivers($fakes);
        config([
            'payments.default_collection_order' => $keys,
            'payments.default_payout_order' => $keys,
        ]);

        return $fakes;
    }

    public function key(): string
    {
        return $this->gatewayKey;
    }

    public function displayName(): string
    {
        return 'Fake '.ucfirst($this->gatewayKey);
    }

    public function credentialFields(): array
    {
        return [];
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function testConnection(): array
    {
        return ['ok' => $this->configured, 'message' => $this->configured ? 'Connected.' : 'Not configured.'];
    }

    public function parseWebhook(Request $request): PaymentEvent
    {
        return $this->toEvent((array) json_decode($request->getContent(), true));
    }

    public function normalizeWebhookPayload(array $payload): PaymentEvent
    {
        if ($this->processFailures > 0) {
            $this->processFailures--;

            throw new \RuntimeException('Simulated processing failure.');
        }

        return $this->toEvent($payload);
    }

    private function toEvent(array $payload): PaymentEvent
    {
        return new PaymentEvent(
            type: (string) ($payload['type'] ?? PaymentEvent::TYPE_IGNORED),
            gateway: $this->gatewayKey,
            status: (string) ($payload['status'] ?? PaymentEvent::STATUS_PENDING),
            reference: $payload['reference'] ?? null,
            providerReference: $payload['provider_reference'] ?? null,
            amount: isset($payload['amount']) ? (string) $payload['amount'] : null,
            currency: $payload['currency'] ?? 'USD',
            eventId: $payload['id'] ?? null,
            failureCode: $payload['failure_code'] ?? null,
            raw: $payload,
        );
    }

    // ---------------------------------------------------------------- collections

    public function currencies(): array
    {
        return $this->currencyList;
    }

    public function initializeCheckout(CheckoutRequest $request): CheckoutSession
    {
        $this->checkouts[] = $request;

        return new CheckoutSession('https://checkout.example.com/'.$this->gatewayKey.'/'.$request->reference, 'prov-'.$request->reference);
    }

    public function verify(string $reference, ?string $providerReference = null): PaymentEvent
    {
        $checkout = collect($this->checkouts)->firstWhere('reference', $reference);

        return new PaymentEvent(
            type: PaymentEvent::TYPE_COLLECTION,
            gateway: $this->gatewayKey,
            status: $this->verifyStatus,
            reference: $reference,
            providerReference: $providerReference ?? 'prov-'.$reference,
            amount: $this->verifyAmount ?? $checkout?->amount,
            currency: $this->verifyCurrency,
        );
    }

    // ---------------------------------------------------------------- payouts

    public function payoutCountries(): array
    {
        return array_values(array_unique(array_map(fn (CoverageRoute $r) => $r->country, $this->coverageRoutes)));
    }

    public function payoutMethods(string $country): array
    {
        return array_values(array_unique(array_map(
            fn (CoverageRoute $r) => $r->method,
            array_filter($this->coverageRoutes, fn (CoverageRoute $r) => $r->country === strtoupper($country)),
        )));
    }

    public function listBanks(string $country, string $method = 'bank_account'): array
    {
        return $this->banks;
    }

    public function supportsResolve(string $country, string $method): bool
    {
        return $this->resolvedName !== null;
    }

    public function resolveAccount(PayoutAccountDetails $account): ResolvedAccount
    {
        if ($this->resolvedName === null) {
            throw new GatewayException('Account lookup is not available.');
        }

        return new ResolvedAccount($this->resolvedName, $this->gatewayKey);
    }

    public function initiateTransfer(PayoutRequest $request): PayoutResult
    {
        $this->transfers[] = $request;
        $result = count($this->transferResults) > 1
            ? array_shift($this->transferResults)
            : ($this->transferResults[0] ?? new PayoutResult(PayoutResult::PROCESSING, 'prov-'.$request->reference));

        if ($result->status !== PayoutResult::NOT_CREATED) {
            $this->statuses[$request->reference] ??= $result;
        }

        return $result;
    }

    public function transferStatus(string $reference, ?string $providerReference = null): PayoutResult
    {
        return $this->statuses[$reference] ?? ($this->lookupUnconfirmed
            ? PayoutResult::unknown('Lookup timed out.')
            : PayoutResult::notFound('No such transfer.'));
    }

    public function balance(string $currency = 'USD'): ?string
    {
        return $this->balanceAmount;
    }

    public function quoteTransfer(string $amount, PayoutAccountDetails $destination): ?PayoutQuote
    {
        return $this->quote;
    }

    public function coverage(): array
    {
        if ($this->coverageThrows) {
            throw new GatewayException('Coverage lookup failed.');
        }

        return $this->coverageRoutes;
    }

    public function failureClass(?string $code): string
    {
        return $this->failureClasses[(string) $code] ?? FailureClass::OTHER;
    }

    // ---------------------------------------------------------------- helpers

    public function succeed(string $reference, ?string $providerReference = null): void
    {
        $this->statuses[$reference] = new PayoutResult(PayoutResult::SUCCEEDED, $providerReference ?? 'prov-'.$reference);
    }

    public function fail(string $reference, string $code, ?string $returnCharge = null): void
    {
        $this->statuses[$reference] = PayoutResult::failed($code, 'Failed: '.$code, $this->failureClass($code), providerReference: 'prov-'.$reference, returnCharge: $returnCharge);
    }
}
