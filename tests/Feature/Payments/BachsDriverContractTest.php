<?php

namespace Tests\Feature\Payments;

use App\Models\IntegrationProvider;
use App\Models\PayoutConnection;
use App\Models\User;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Wallet\Payments\Bachs\BachsDriver;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Payments\Data\PayoutRequest;
use App\Modules\Wallet\Payments\Data\PayoutResult;
use App\Modules\Wallet\Payments\Exceptions\InvalidWebhookSignature;
use App\Modules\Wallet\Payments\GatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsPayouts;
use Tests\TestCase;

/**
 * HTTP contract for the Bachs driver: paths, headers, idempotency keys and error handling.
 */
class BachsDriverContractTest extends TestCase
{
    use BuildsPayouts;
    use RefreshDatabase;

    private const SECRET = 'whsec_current_example';

    /** @var array<string, list<mixed>> "METHOD /path" => queued responses (the last one repeats) */
    private array $routes = [];

    private BachsDriver $driver;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openPayoutGate();

        IntegrationProvider::forProvider('bachs')->forceFill([
            'enabled' => true,
            'credentials' => ['secret_key' => 'sk_sandbox_example', 'webhook_secret' => self::SECRET],
            'meta' => ['environment' => 'sandbox'],
        ])->save();
        app(GatewayRegistry::class)->setDrivers(['bachs' => BachsDriver::class]);
        $this->driver = app(GatewayRegistry::class)->driver('bachs');

        $this->agent = $this->agentWithBalance();
        PayoutConnection::query()->create([
            'user_id' => $this->agent->id,
            'provider' => 'bachs',
            'account_id' => 'acct_1',
            'status' => PayoutConnection::ACTIVE,
            'capabilities' => ['transfers' => 'active', 'payouts' => 'active'],
        ]);

        // Documented list shape: { items: [ { name, status, ... } ] }
        $this->on('GET /v1/accounts/acct_1/capabilities', ['items' => [
            ['name' => 'transfers', 'status' => 'active', 'requested' => true, 'status_details' => null],
            ['name' => 'payouts', 'status' => 'active', 'requested' => true, 'status_details' => null],
        ]]);
        $this->on('GET /v1/payouts/destinations', ['items' => []]);
        $this->on('POST /v1/payouts/destinations', ['id' => 'pd_1', 'is_usable' => true, 'status' => 'active']);
        $this->on('GET /v1/balances', ['balances' => [['currency' => 'USD', 'available_balance' => '1.00']]]);

        Http::fake(function (HttpRequest $request) {
            $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);
            if (! isset($this->routes[$key])) {
                return Http::response(['error_code' => 'NOT_FOUND', 'detail' => 'Not faked: '.$key], 404);
            }
            $next = count($this->routes[$key]) > 1 ? array_shift($this->routes[$key]) : $this->routes[$key][0];

            return is_array($next) ? Http::response($next) : $next;
        });
    }

    private function on(string $route, mixed ...$responses): void
    {
        $this->routes[$route] = $responses;
    }

    private function error(int $status, string $code): mixed
    {
        return Http::response(['error_code' => $code, 'detail' => $code], $status);
    }

    /** @return Collection<int, HttpRequest> */
    private function sent(string $method, string $path): Collection
    {
        return Http::recorded(fn (HttpRequest $r) => $r->method() === $method && parse_url($r->url(), PHP_URL_PATH) === $path)
            ->map(fn (array $pair) => $pair[0])
            ->values();
    }

    private function usDestination(): PayoutAccountDetails
    {
        return PayoutAccountDetails::fromPayoutAccount($this->payoutAccount($this->agent, [
            'country' => 'US', 'currency' => 'USD', 'bank_code' => null, 'bank_name' => 'Example Bank',
            'details' => [
                'routing_number' => '011000015', 'account_number' => '000123456789', 'scheme' => 'ach', 'bank_name' => 'Example Bank',
                'bank_address_line1' => '1 Example Street', 'bank_address_city' => 'Boston', 'bank_address_state' => 'MA', 'bank_address_postal_code' => '02110',
            ],
        ]));
    }

    private function pay(PayoutAccountDetails $destination, string $reference = 'WPO-1-1'): PayoutResult
    {
        return $this->driver->initiateTransfer(new PayoutRequest($reference, '20.00', $destination, 'Payout'));
    }

    public function test_account_link_and_destination_calls_use_the_documented_paths_and_keys(): void
    {
        $this->on('POST /v1/accounts', ['id' => 'acct_1']);
        $this->on('POST /v1/accounts/acct_1/account-links', ['url' => 'https://connect.example.com/onboard/1']);

        $account = $this->driver->createRecipientAccount($this->agent->id, 'agent@example.com', 'Ada Lovelace', 'us');
        $link = $this->driver->createAccountLink('acct_1', 'account_onboarding', 'https://example.com/return', 'https://example.com/refresh');

        $this->assertSame('acct_1', $account['id']);
        $this->assertSame('https://connect.example.com/onboard/1', $link);

        $create = $this->sent('POST', '/v1/accounts')[0];
        $this->assertSame('acct-user-'.$this->agent->id, $create->header('Idempotency-Key')[0]);
        $this->assertSame([], $create->header('X-Account-Id'));
        $this->assertSame('US', $create['country']);
        $this->assertSame('Bearer sk_sandbox_example', $create->header('Authorization')[0]);
        $this->assertStringStartsWith('https://sandbox-api.bachs.io/', $create->url());

        $linkRequest = $this->sent('POST', '/v1/accounts/acct_1/account-links')[0];
        $this->assertSame('account_onboarding', $linkRequest['type']);
        $this->assertSame('https://example.com/refresh', $linkRequest['refresh_url']);

        $prepared = $this->driver->prepareBeneficiary($this->usDestination());
        $this->assertTrue($prepared['usable']);
        $this->assertSame('pd_1', $prepared['ids']['bachs']['destination']);

        $destination = $this->sent('POST', '/v1/payouts/destinations')[0];
        $this->assertSame('acct_1', $destination->header('X-Account-Id')[0]);
        $this->assertStringStartsWith('pd-acct_1-', $destination->header('Idempotency-Key')[0]);
    }

    public function test_unusable_destination_stops_before_any_money_moves(): void
    {
        $this->on('POST /v1/payouts/destinations', ['id' => 'pd_1', 'is_usable' => false, 'status' => 'rejected', 'status_reason' => 'Account closed']);

        $result = $this->pay($this->usDestination());

        $this->assertSame(PayoutResult::NOT_CREATED, $result->status);
        $this->assertSame('DESTINATION_REJECTED', $result->failureCode);
        $this->assertCount(0, $this->sent('POST', '/v1/transfers'));
    }

    public function test_normalize_capabilities_reads_documented_items_list(): void
    {
        $caps = BachsDriver::normalizeCapabilities([
            'items' => [
                ['name' => 'payouts', 'status' => 'active', 'requested' => true, 'status_details' => null],
                ['name' => 'transfers', 'status' => 'restricted', 'requested' => true, 'status_details' => [
                    ['code' => 'requirements_outstanding', 'message' => 'Outstanding requirements. See the account\'s requirements.'],
                ]],
            ],
        ]);

        $this->assertSame(['payouts' => 'active', 'transfers' => 'restricted'], $caps);
        $this->assertStringContainsString('transfers=restricted', BachsDriver::describeInactiveCapabilities([
            'items' => [
                ['name' => 'transfers', 'status' => 'restricted', 'status_details' => [
                    ['code' => 'requirements_outstanding', 'message' => 'Outstanding requirements. See the account\'s requirements.'],
                ]],
                ['name' => 'payouts', 'status' => 'active', 'status_details' => null],
            ],
        ], $caps));
    }

    public function test_inactive_capabilities_report_live_statuses_not_a_vague_message(): void
    {
        $this->on('GET /v1/accounts/acct_1/capabilities', ['items' => [
            ['name' => 'payouts', 'status' => 'restricted', 'requested' => true, 'status_details' => [
                ['code' => 'requirements_outstanding', 'resolution' => 'provide_info', 'message' => 'Outstanding requirements. See the account\'s requirements.'],
            ]],
            ['name' => 'transfers', 'status' => 'active', 'requested' => true, 'status_details' => null],
        ]]);
        $this->on('GET /v1/accounts/acct_1', [
            'id' => 'acct_1',
            'capabilities' => [
                'payouts' => ['status' => 'restricted'],
                'transfers' => ['status' => 'active'],
            ],
            'requirements' => ['currently_due' => ['persons.per_1.id_document']],
        ]);

        $result = $this->pay($this->usDestination());

        $this->assertSame(PayoutResult::NOT_CREATED, $result->status);
        $this->assertSame('PAYOUTS_NOT_ENABLED', $result->failureCode);
        $this->assertStringContainsString('payouts=restricted', (string) $result->failureReason);
        $this->assertStringContainsString('Outstanding requirements', (string) $result->failureReason);
        $this->assertSame(['payouts' => 'restricted', 'transfers' => 'active'], $result->raw['capabilities'] ?? null);
        $this->assertCount(0, $this->sent('POST', '/v1/transfers'));
    }

    public function test_transfer_then_payout_then_sweep_carry_the_right_account_and_keys(): void
    {
        $this->on('POST /v1/transfers', ['id' => 'tr_1'], ['id' => 'tr_back']);
        $this->on('POST /v1/payouts', ['id' => 'po_1', 'status' => 'processing']);

        $result = $this->pay($this->usDestination());

        $this->assertSame(PayoutResult::PROCESSING, $result->status);
        $this->assertSame('po_1', $result->providerReference);
        $this->assertSame('tr_1', $result->raw['transfer']);

        [$fund, $sweep] = $this->sent('POST', '/v1/transfers')->all();
        $this->assertSame([], $fund->header('X-Account-Id'));
        $this->assertSame('WPO-1-1-transfer', $fund->header('Idempotency-Key')[0]);
        $this->assertSame('acct_1', $fund['destination']);
        $this->assertSame('21.00', $fund['amount']);
        $this->assertSame('WPO-1-1', $fund['transfer_group']);

        $payout = $this->sent('POST', '/v1/payouts')[0];
        $this->assertSame('acct_1', $payout->header('X-Account-Id')[0]);
        $this->assertSame('WPO-1-1', $payout->header('Idempotency-Key')[0]);
        $this->assertSame(['destination' => 'pd_1', 'reference' => 'WPO-1-1', 'amount' => '20.00'], $payout->data());

        $this->assertSame('acct_1', $sweep->header('X-Account-Id')[0]);
        $this->assertSame('WPO-1-1-sweep', $sweep->header('Idempotency-Key')[0]);
        $this->assertSame('self', $sweep['destination']);
        $this->assertSame('1.00', $sweep['amount']);
    }

    public function test_unconfirmed_transfer_stays_unknown_and_nothing_is_swept(): void
    {
        $this->on('POST /v1/transfers', $this->error(503, 'SERVICE_UNAVAILABLE'));
        $this->on('GET /v1/transfers', ['data' => []]);

        $result = $this->pay($this->usDestination());

        $this->assertSame(PayoutResult::UNKNOWN, $result->status);
        $this->assertSame('TRANSFER_UNCONFIRMED', $result->failureCode);
        $this->assertFalse($result->confirmedAbsent());
        $this->assertCount(2, $this->sent('POST', '/v1/transfers'));
        $this->assertSame('WPO-1-1', $this->sent('GET', '/v1/transfers')[0]->data()['transfer_group']);
        $this->assertCount(0, $this->sent('POST', '/v1/payouts'));
        $this->assertCount(0, $this->sent('GET', '/v1/balances'));
    }

    public function test_transfer_found_by_lookup_after_a_timeout_continues_to_the_payout(): void
    {
        $this->on('POST /v1/transfers', $this->error(504, 'GATEWAY_TIMEOUT'), $this->error(504, 'GATEWAY_TIMEOUT'), ['id' => 'tr_back']);
        $this->on('GET /v1/transfers', ['data' => [['id' => 'tr_9', 'transfer_group' => 'WPO-1-1']]]);
        $this->on('POST /v1/payouts', ['id' => 'po_1', 'status' => 'pending']);

        $result = $this->pay($this->usDestination());

        $this->assertSame(PayoutResult::PENDING, $result->status);
        $this->assertSame('tr_9', $result->raw['transfer']);
    }

    public function test_transient_payout_error_is_retried_with_the_same_key(): void
    {
        $this->on('POST /v1/transfers', ['id' => 'tr_1']);
        $this->on('POST /v1/payouts', $this->error(502, 'BAD_GATEWAY'), ['id' => 'po_1', 'status' => 'completed', 'fee' => '0.25']);

        $result = $this->pay($this->usDestination());

        $this->assertSame(PayoutResult::SUCCEEDED, $result->status);
        $this->assertSame('0.25', $result->fee);
        $keys = $this->sent('POST', '/v1/payouts')->map(fn (HttpRequest $r) => $r->header('Idempotency-Key')[0])->all();
        $this->assertSame(['WPO-1-1', 'WPO-1-1'], $keys);
    }

    public function test_idempotency_in_progress_leaves_the_payout_unknown_without_reversing(): void
    {
        $this->on('POST /v1/transfers', ['id' => 'tr_1']);
        $this->on('POST /v1/payouts', $this->error(409, 'IDEMPOTENCY_IN_PROGRESS'));

        $result = $this->pay($this->usDestination());

        $this->assertSame(PayoutResult::UNKNOWN, $result->status);
        $this->assertCount(2, $this->sent('POST', '/v1/payouts'));
        $this->assertCount(1, $this->sent('POST', '/v1/transfers'));
        $this->assertCount(0, $this->sent('GET', '/v1/balances'));
    }

    public function test_expired_quote_is_requoted_once_under_a_new_key(): void
    {
        $this->on('POST /v1/transfers', ['id' => 'tr_1']);
        $this->on('POST /v1/payouts/quotes', ['quote_id' => 'q_1'], ['quote_id' => 'q_2']);
        $this->on('POST /v1/payouts', $this->error(400, 'QUOTE_EXPIRED'), ['id' => 'po_2', 'status' => 'processing']);

        $result = $this->pay(PayoutAccountDetails::fromPayoutAccount($this->payoutAccount($this->agent)));

        $this->assertSame(PayoutResult::PROCESSING, $result->status);
        $payouts = $this->sent('POST', '/v1/payouts');
        $this->assertSame(['WPO-1-1', 'WPO-1-1-r1'], $payouts->map(fn (HttpRequest $r) => $r->header('Idempotency-Key')[0])->all());
        $this->assertSame(['q_1', 'q_2'], $payouts->map(fn (HttpRequest $r) => $r['quote_id'])->all());
        $this->assertSame('NGN', $this->sent('POST', '/v1/payouts/quotes')[0]['to_currency']);
    }

    public function test_quote_expiring_twice_reverses_the_funding_and_fails(): void
    {
        $this->on('POST /v1/transfers', ['id' => 'tr_1'], ['id' => 'tr_back']);
        $this->on('GET /v1/balances', ['balances' => [['currency' => 'USD', 'available_balance' => '21.00']]]);
        $this->on('POST /v1/payouts/quotes', ['quote_id' => 'q_1']);
        $this->on('POST /v1/payouts', $this->error(400, 'QUOTE_EXPIRED'));

        $result = $this->pay(PayoutAccountDetails::fromPayoutAccount($this->payoutAccount($this->agent)));

        $this->assertSame(PayoutResult::FAILED, $result->status);
        $this->assertSame('QUOTE_EXPIRED', $result->failureCode);
        $reverse = $this->sent('POST', '/v1/transfers')[1];
        $this->assertSame('WPO-1-1-reverse', $reverse->header('Idempotency-Key')[0]);
        $this->assertSame('21.00', $reverse['amount']);
    }

    public function test_documented_quote_response_without_fee_field_is_accepted(): void
    {
        // Exact shape from docs.bachs.io Create Payout Quote (no fee / fee_amount).
        $this->on('POST /v1/payouts/quotes', [
            'quote_id' => 'pqt_1a2b3c4d5e6f',
            'from_currency' => 'USD',
            'to_currency' => 'NGN',
            'from_amount' => '100.00',
            'to_amount' => '148500.00',
            'exchange_rate' => '1500.00',
            'expires_at' => '2026-02-22T12:31:00+00:00',
        ]);

        $quote = $this->driver->quoteTransfer('100.00', PayoutAccountDetails::fromPayoutAccount($this->payoutAccount($this->agent)));

        $this->assertNotNull($quote);
        $this->assertSame('pqt_1a2b3c4d5e6f', $quote->quoteId);
        $this->assertSame('NGN', $quote->destinationCurrency);
        $this->assertSame('148500.00', $quote->destinationAmount);
        $this->assertSame('1500.00', $quote->fxRate);
        // fee = from_amount − (to_amount / rate) = 100 − 99 = 1
        $this->assertSame('1.00', $quote->providerFee);
        $body = $this->sent('POST', '/v1/payouts/quotes')[0];
        $this->assertSame('USD', $body['from_currency']);
        $this->assertSame('NGN', $body['to_currency']);
        $this->assertSame('BANK_TRANSFER', $body['payout_method']);
    }

    public function test_quote_without_quote_id_is_rejected(): void
    {
        $this->on('POST /v1/payouts/quotes', [
            'from_currency' => 'USD',
            'to_currency' => 'NGN',
            'from_amount' => '50.00',
            'to_amount' => '75000.00',
            'exchange_rate' => '1500.00',
        ]);

        $this->assertNull($this->driver->quoteTransfer('50.00', PayoutAccountDetails::fromPayoutAccount($this->payoutAccount($this->agent))));
    }

    private function webhook(string $raw, string $header): Request
    {
        return Request::create('/webhooks/payments/bachs', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_BACHS_SIGNATURE_V2' => $header,
        ], $raw);
    }

    public function test_signature_v2_accepts_any_listed_signature_during_secret_rotation(): void
    {
        $raw = json_encode(['id' => 'evt_1', 'type' => 'payout.paid', 'data' => ['object' => ['id' => 'po_1', 'reference' => 'WPO-1-1', 'status' => 'completed']]]);
        $ts = (string) time();
        $old = hash_hmac('sha256', $ts.'.'.$raw, 'whsec_previous_example');
        $current = hash_hmac('sha256', $ts.'.'.$raw, self::SECRET);

        $event = $this->driver->parseWebhook($this->webhook($raw, 't='.$ts.',v1='.$old.',v1='.$current));
        $this->assertSame('evt_1', $event->eventId);

        $rejected = 0;
        foreach ([
            't='.$ts.',v1='.$old,
            't='.(time() - 600).',v1='.hash_hmac('sha256', (time() - 600).'.'.$raw, self::SECRET),
            't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$raw.' ', self::SECRET),
        ] as $header) {
            try {
                $this->driver->parseWebhook($this->webhook($raw, $header));
            } catch (InvalidWebhookSignature) {
                $rejected++;
            }
        }
        $this->assertSame(3, $rejected);
    }

    public function test_connected_account_endpoint_secret_is_accepted_alongside_the_account_secret(): void
    {
        $provider = IntegrationProvider::forProvider('bachs');
        $provider->mergeCredentials(['connect_webhook_secret' => 'whsec_connect_example']);
        $provider->save();
        $this->driver->refreshProvider();

        $raw = json_encode(['id' => 'evt_2', 'type' => 'account.updated', 'data' => ['object' => ['id' => 'acct_1']]]);
        $ts = (string) time();

        $event = $this->driver->parseWebhook($this->webhook($raw, 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$raw, 'whsec_connect_example')));
        $this->assertSame('evt_2', $event->eventId);

        $event = $this->driver->parseWebhook($this->webhook($raw, 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$raw, self::SECRET)));
        $this->assertSame('evt_2', $event->eventId);

        $this->expectException(InvalidWebhookSignature::class);
        $this->driver->parseWebhook($this->webhook($raw, 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$raw, 'whsec_unknown_example')));
    }

    public function test_status_lookup_distinguishes_absent_from_unreachable(): void
    {
        $this->on('GET /v1/payouts', ['data' => [['id' => 'po_other', 'reference' => 'WPO-9-9', 'status' => 'completed']]]);
        $account = $this->payoutAccount($this->agent, ['provider_beneficiary_ids' => ['bachs' => ['account' => 'acct_1']]]);
        $attempt = WithdrawalPayoutAttempt::query()->create([
            'withdrawal_id' => $this->pendingWithdrawal($this->agent, $account)->id,
            'attempt_no' => 1, 'gateway_key' => 'bachs', 'reference' => 'WPO-1-1', 'status' => 'not_created',
            'amount' => '20.00', 'response_snapshot' => ['account' => 'acct_1'],
        ]);

        $absent = $this->driver->transferStatus($attempt->reference);
        $this->assertTrue($absent->confirmedAbsent());
        $this->assertSame('acct_1', $this->sent('GET', '/v1/payouts')[0]->header('X-Account-Id')[0]);

        $this->on('GET /v1/payouts', $this->error(503, 'SERVICE_UNAVAILABLE'));
        $unreachable = $this->driver->transferStatus($attempt->reference);
        $this->assertSame(PayoutResult::UNKNOWN, $unreachable->status);
        $this->assertFalse($unreachable->confirmedAbsent());
    }

    public function test_coverage_uses_documented_routes_when_org_payout_supported_list_is_empty(): void
    {
        $this->on('GET /v1/currencies/payout-supported', ['currencies' => []]);

        $routes = $this->driver->coverage();
        $ng = collect($routes)->first(fn ($r) => $r->country === 'NG' && $r->method === 'bank_account');

        $this->assertNotNull($ng);
        $this->assertSame('NGN', $ng->currency);
        $this->assertNotEmpty($routes);
    }

    public function test_coverage_respects_org_payout_supported_list_when_present(): void
    {
        $this->on('GET /v1/currencies/payout-supported', ['currencies' => ['USD']]);

        $routes = $this->driver->coverage();
        $countries = collect($routes)->pluck('country')->unique()->values()->all();

        $this->assertContains('US', $countries);
        $this->assertNotContains('NG', $countries);
    }

    public function test_crypto_accepted_overpayment_verifies_as_succeeded_at_checkout_price(): void
    {
        $this->on('GET /v1/checkout-sessions/chk_crypto_1', [
            'id' => 'chk_crypto_1',
            'reference' => 'PLT-PAY-CRYPTO-1',
            'status' => 'completed',
            'pricing' => ['amount' => '10.00', 'currency' => 'USD'],
            'amount' => '10.15',
            'currency' => 'USD',
            'charge' => ['status' => 'accepted', 'amount' => '10.15', 'currency' => 'USD'],
            'payment_status' => 'paid',
        ]);

        $event = $this->driver->verify('PLT-PAY-CRYPTO-1', 'chk_crypto_1');

        $this->assertTrue($event->succeeded());
        $this->assertSame('10.00', $event->amount);
        $this->assertSame('USD', $event->currency);
    }
}
