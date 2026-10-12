<?php

namespace Tests\Feature\Payments;

use App\Models\IntegrationProvider;
use App\Modules\Wallet\Payments\Data\PaymentEvent;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Payments\Data\PayoutRequest;
use App\Modules\Wallet\Payments\Data\PayoutResult;
use App\Modules\Wallet\Payments\Exceptions\InvalidWebhookSignature;
use App\Modules\Wallet\Payments\FailureClass;
use App\Modules\Wallet\Payments\Flutterwave\FlutterwaveDriver;
use App\Modules\Wallet\Payments\GatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsPayouts;
use Tests\TestCase;

/**
 * HTTP contract for the Flutterwave v3 driver: paths, bodies, envelopes and error handling.
 */
class FlutterwaveDriverContractTest extends TestCase
{
    use BuildsPayouts;
    use RefreshDatabase;

    private const HASH = 'verif_hash_example';

    /** @var array<string, list<mixed>> "METHOD /path" => queued responses (the last one repeats) */
    private array $routes = [];

    private FlutterwaveDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openPayoutGate();

        IntegrationProvider::forProvider('flutterwave')->forceFill([
            'enabled' => true,
            'credentials' => ['secret_key' => 'FLWSECK_TEST-example', 'webhook_hash' => self::HASH],
            'meta' => ['environment' => 'sandbox'],
        ])->save();
        app(GatewayRegistry::class)->setDrivers(['flutterwave' => FlutterwaveDriver::class]);
        $this->driver = app(GatewayRegistry::class)->driver('flutterwave');

        // 1000 NGN costs 0.65 USD, so 20.00 USD buys 30769.23 NGN.
        $this->on('GET /v3/transfers/rates', $this->ok(['source' => ['currency' => 'USD', 'amount' => 0.65], 'destination' => ['currency' => 'NGN', 'amount' => 1000]]));

        Http::fake(function (HttpRequest $request) {
            $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);
            if (! isset($this->routes[$key])) {
                return Http::response(['status' => 'error', 'message' => 'Not faked: '.$key], 404);
            }
            $next = count($this->routes[$key]) > 1 ? array_shift($this->routes[$key]) : $this->routes[$key][0];

            return is_array($next) ? Http::response($next) : $next;
        });
    }

    private function on(string $route, mixed ...$responses): void
    {
        $this->routes[$route] = $responses;
    }

    /** @return array<string, mixed> */
    private function ok(array $data): array
    {
        return ['status' => 'success', 'message' => 'ok', 'data' => $data];
    }

    private function error(int $status, string $message): mixed
    {
        return Http::response(['status' => 'error', 'message' => $message, 'data' => null], $status);
    }

    /** @return Collection<int, HttpRequest> */
    private function sent(string $method, string $path): Collection
    {
        return Http::recorded(fn (HttpRequest $r) => $r->method() === $method && parse_url($r->url(), PHP_URL_PATH) === $path)
            ->map(fn (array $pair) => $pair[0])
            ->values();
    }

    private function ngDestination(): PayoutAccountDetails
    {
        return new PayoutAccountDetails('NG', 'NGN', 'bank_account', 'Ada Lovelace', ['account_number' => '0123456789', 'bank_code' => '044'], '044', 'Example Bank');
    }

    private function pay(string $currency = 'USD'): PayoutResult
    {
        return $this->driver->initiateTransfer(new PayoutRequest('WPO-1-1', '20.00', $this->ngDestination(), 'Payout', $currency));
    }

    public function test_transfer_is_sent_in_the_destination_currency_and_debited_in_usd(): void
    {
        $this->on('POST /v3/transfers', $this->ok(['id' => 77, 'status' => 'NEW', 'fee' => 26.88]));

        $result = $this->pay();

        $this->assertSame(PayoutResult::PENDING, $result->status);
        $this->assertSame('77', $result->providerReference);

        $rate = $this->sent('GET', '/v3/transfers/rates')[0];
        $this->assertSame(['amount' => '1000', 'destination_currency' => 'NGN', 'source_currency' => 'USD'], $rate->data());

        $transfer = $this->sent('POST', '/v3/transfers')[0];
        $this->assertSame('Bearer FLWSECK_TEST-example', $transfer->header('Authorization')[0]);
        $this->assertSame('30769.23', $transfer['amount']);
        $this->assertSame('NGN', $transfer['currency']);
        $this->assertSame('USD', $transfer['debit_currency']);
        $this->assertSame('WPO-1-1', $transfer['reference']);
        $this->assertSame('044', $transfer['account_bank']);
        $this->assertSame('0123456789', $transfer['account_number']);
    }

    public function test_currency_mismatch_is_refused_without_calling_flutterwave(): void
    {
        $result = $this->pay('NGN');

        $this->assertSame(PayoutResult::NOT_CREATED, $result->status);
        $this->assertSame('CURRENCY_MISMATCH', $result->failureCode);
        Http::assertNothingSent();
    }

    public function test_timeout_on_the_transfer_leaves_it_unknown_and_is_not_resent(): void
    {
        $this->on('POST /v3/transfers', $this->error(503, 'Service unavailable'));

        $result = $this->pay();

        $this->assertSame(PayoutResult::UNKNOWN, $result->status);
        $this->assertCount(1, $this->sent('POST', '/v3/transfers'));
    }

    public function test_unreachable_rate_service_fails_before_any_transfer(): void
    {
        $this->on('GET /v3/transfers/rates', $this->error(502, 'Bad gateway'));

        $result = $this->pay();

        $this->assertSame(PayoutResult::FAILED, $result->status);
        $this->assertSame('GATEWAY_UNAVAILABLE', $result->failureCode);
        $this->assertSame(FailureClass::RECOVERABLE, $result->failureClass);
        $this->assertCount(0, $this->sent('POST', '/v3/transfers'));
    }

    public function test_rejections_are_classified_from_the_message(): void
    {
        $this->on('POST /v3/transfers', $this->error(400, 'Insufficient balance in your USD wallet'), $this->error(400, 'Transfers are not enabled for this account'));

        $short = $this->pay();
        $this->assertSame(PayoutResult::FAILED, $short->status);
        $this->assertSame('INSUFFICIENT_BALANCE', $short->failureCode);
        $this->assertSame(FailureClass::RECOVERABLE, $short->failureClass);

        $blocked = $this->pay();
        $this->assertSame(PayoutResult::NOT_CREATED, $blocked->status);
        $this->assertSame('TRANSFER_NOT_ALLOWED', $blocked->failureCode);
    }

    public function test_an_error_envelope_with_http_200_is_still_an_error(): void
    {
        $this->on('POST /v3/transfers', ['status' => 'error', 'message' => 'Invalid account number', 'data' => null]);

        $result = $this->pay();

        $this->assertSame(PayoutResult::FAILED, $result->status);
        $this->assertSame(FailureClass::ACCOUNT_DETAILS, $result->failureClass);
    }

    public function test_status_lookup_by_id_and_by_reference(): void
    {
        $this->on('GET /v3/transfers/77', $this->ok(['id' => 77, 'reference' => 'WPO-1-1', 'status' => 'SUCCESSFUL', 'fee' => 0.4]));
        $paid = $this->driver->transferStatus('WPO-1-1', '77');
        $this->assertSame(PayoutResult::SUCCEEDED, $paid->status);
        $this->assertSame('0.4', $paid->fee);

        $this->on('GET /v3/transfers', $this->ok([['id' => 5, 'reference' => 'WPO-OTHER', 'status' => 'SUCCESSFUL']]), $this->ok([]));
        $absent = $this->driver->transferStatus('WPO-1-1');
        $this->assertTrue($absent->confirmedAbsent());

        $this->on('GET /v3/transfers', $this->error(500, 'Server error'));
        $unreachable = $this->driver->transferStatus('WPO-1-1');
        $this->assertSame(PayoutResult::UNKNOWN, $unreachable->status);
        $this->assertFalse($unreachable->confirmedAbsent());
    }

    public function test_fee_quote_is_converted_to_usd_and_rounded_up_to_the_cent(): void
    {
        $this->on('GET /v3/transfers/fee', $this->ok([['currency' => 'NGN', 'fee_type' => 'value', 'fee' => 26.88]]));

        $quote = $this->driver->quoteTransfer('20.00', $this->ngDestination());

        $this->assertSame('0.02', $quote->providerFee);
        $this->assertSame('30769.23', $quote->destinationAmount);
        $this->assertSame(['amount' => '30769.23', 'currency' => 'NGN', 'type' => 'account'], $this->sent('GET', '/v3/transfers/fee')[0]->data());
    }

    public function test_verify_checks_the_reference_and_reads_the_amount(): void
    {
        $this->on('GET /v3/transactions/verify_by_reference',
            $this->ok(['id' => 9, 'tx_ref' => 'DEP-1', 'status' => 'successful', 'amount' => 25, 'currency' => 'USD']),
            $this->ok(['id' => 10, 'tx_ref' => 'DEP-OTHER', 'status' => 'successful', 'amount' => 25, 'currency' => 'USD']),
            $this->error(404, 'No transaction was found for this id'),
        );

        $paid = $this->driver->verify('DEP-1');
        $this->assertSame(PaymentEvent::STATUS_SUCCEEDED, $paid->status);
        $this->assertSame('25', $paid->amount);
        $this->assertSame('DEP-1', $this->sent('GET', '/v3/transactions/verify_by_reference')[0]['tx_ref']);

        $mismatch = $this->driver->verify('DEP-1');
        $this->assertSame(PaymentEvent::STATUS_FAILED, $mismatch->status);
        $this->assertSame('REFERENCE_MISMATCH', $mismatch->failureCode);

        $this->assertSame(PaymentEvent::STATUS_PENDING, $this->driver->verify('DEP-1')->status);
    }

    public function test_webhook_requires_the_configured_hash(): void
    {
        $raw = json_encode(['event' => 'transfer.completed', 'data' => ['id' => 77, 'reference' => 'WPO-1-1', 'status' => 'FAILED', 'complete_message' => 'Account closed']]);
        $request = fn (?string $hash) => Request::create('/webhooks/payments/flutterwave', 'POST', [], [], [], array_filter([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_VERIF_HASH' => $hash,
        ]), $raw);

        $event = $this->driver->parseWebhook($request(self::HASH));
        $this->assertSame(PaymentEvent::TYPE_PAYOUT, $event->type);
        $this->assertSame(PaymentEvent::STATUS_FAILED, $event->status);
        $this->assertSame('WPO-1-1', $event->reference);
        $this->assertSame('transfer.completed:77:FAILED', $event->eventId);

        foreach (['wrong_hash', null] as $hash) {
            try {
                $this->driver->parseWebhook($request($hash));
                $this->fail('A webhook without the right hash was accepted.');
            } catch (InvalidWebhookSignature) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
