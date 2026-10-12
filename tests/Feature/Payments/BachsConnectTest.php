<?php

namespace Tests\Feature\Payments;

use App\Models\IntegrationProvider;
use App\Models\PaymentWebhook;
use App\Models\PayoutAccount;
use App\Models\PayoutConnection;
use App\Models\User;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Wallet\Payments\Bachs\BachsConnectService;
use App\Modules\Wallet\Payments\Bachs\BachsDriver;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Payments\Data\PayoutRequest;
use App\Modules\Wallet\Payments\Data\PayoutResult;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Modules\Wallet\Services\WithdrawalPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsPayouts;
use Tests\TestCase;

class BachsConnectTest extends TestCase
{
    use BuildsPayouts;
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_example';

    private const ONBOARDING_URL = 'https://connect.example.com/onboard/abc';

    protected function setUp(): void
    {
        parent::setUp();

        $this->openPayoutGate();

        $provider = IntegrationProvider::forProvider('bachs');
        $provider->forceFill([
            'enabled' => true,
            'credentials' => ['secret_key' => 'sk_sandbox_example', 'webhook_secret' => self::WEBHOOK_SECRET],
            'meta' => ['environment' => 'sandbox'],
        ])->save();

        app(GatewayRegistry::class)->setDrivers(['bachs' => BachsDriver::class]);
    }

    /** @param  array<string, string>  $caps */
    private function fakeBachs(array $caps = ['transfers' => 'active', 'payouts' => 'active']): void
    {
        $capabilities = array_map(fn (string $status) => ['status' => $status], $caps);
        $destinations = 0;

        Http::fake(function (HttpRequest $request) use ($capabilities, &$destinations) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/v1/accounts' => Http::response([
                    'id' => 'acct_1',
                    'capabilities' => ['transfers' => ['status' => 'inactive'], 'payouts' => ['status' => 'inactive']],
                    'requirements' => ['currently_due' => ['individual.id_number']],
                ]),
                $path === '/v1/accounts/acct_1/account-links' => Http::response(['url' => self::ONBOARDING_URL]),
                $path === '/v1/accounts/acct_1/capabilities' => Http::response(['capabilities' => $capabilities]),
                $path === '/v1/accounts/acct_1' => Http::response(['id' => 'acct_1', 'capabilities' => $capabilities, 'requirements' => []]),
                $path === '/v1/payouts/destinations' && $request->method() === 'GET' => Http::response(['data' => []]),
                $path === '/v1/payouts/destinations' => Http::response(['id' => 'pd_'.(++$destinations), 'is_usable' => true, 'status' => 'active']),
                default => Http::response(['error_code' => 'NOT_FOUND', 'detail' => 'Not faked'], 404),
            };
        });
    }

    private function signedWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($payload);
        $ts = (string) time();
        $signature = hash_hmac('sha256', $ts.'.'.$raw, self::WEBHOOK_SECRET);

        return $this->call('POST', '/webhooks/payments/bachs', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_BACHS_SIGNATURE_V2' => 't='.$ts.',v1='.$signature,
        ], $raw);
    }

    private function connection(User $agent, string $status = PayoutConnection::REQUIREMENTS_DUE, array $caps = []): PayoutConnection
    {
        return PayoutConnection::query()->create([
            'user_id' => $agent->id,
            'provider' => 'bachs',
            'account_id' => 'acct_1',
            'status' => $status,
            'capabilities' => $caps,
        ]);
    }

    public function test_wallet_shows_connect_button_and_onboarding_reuses_one_account(): void
    {
        $this->fakeBachs();
        $agent = $this->agentWithBalance();

        $this->actingAs($agent)->get(route('agent.wallet'))
            ->assertOk()
            ->assertSee('Connect bank account');

        $this->actingAs($agent)->post(route('agent.payouts.connect'))->assertRedirect(self::ONBOARDING_URL);
        $this->actingAs($agent)->post(route('agent.payouts.connect'))->assertRedirect(self::ONBOARDING_URL);

        $creates = Http::recorded(fn (HttpRequest $r) => $r->method() === 'POST' && parse_url($r->url(), PHP_URL_PATH) === '/v1/accounts')->values();
        $this->assertCount(1, $creates);
        $this->assertSame('acct-user-'.$agent->id, $creates[0][0]->header('Idempotency-Key')[0]);

        $connection = PayoutConnection::for($agent->id, 'bachs');
        $this->assertSame('acct_1', $connection->account_id);
        $this->assertSame(PayoutConnection::REQUIREMENTS_DUE, $connection->status);

        $this->actingAs($agent)->get(route('agent.wallet'))->assertSee('Continue setup');

        $this->actingAs($agent)->get(route('agent.payouts.connect.refresh'))->assertRedirect(self::ONBOARDING_URL);
        $links = Http::recorded(fn (HttpRequest $r) => parse_url($r->url(), PHP_URL_PATH) === '/v1/accounts/acct_1/account-links');
        $this->assertCount(3, $links);
    }

    public function test_return_reads_the_account_from_bachs_before_showing_ready(): void
    {
        $this->fakeBachs();
        $agent = $this->agentWithBalance();
        $this->connection($agent);

        $this->actingAs($agent)->get(route('agent.payouts.connect.return'))
            ->assertRedirect(route('agent.wallet'))
            ->assertSessionHas('success');

        $this->assertTrue(PayoutConnection::for($agent->id, 'bachs')->isActive());
    }

    public function test_return_without_finished_setup_stays_not_ready(): void
    {
        $this->fakeBachs(['transfers' => 'inactive', 'payouts' => 'inactive']);
        $agent = $this->agentWithBalance();
        $this->connection($agent);

        $this->actingAs($agent)->get(route('agent.payouts.connect.return'))->assertSessionHas('info');

        $this->assertFalse(PayoutConnection::for($agent->id, 'bachs')->isActive());
    }

    public function test_connect_webhook_marks_ready_and_older_events_are_ignored(): void
    {
        $this->fakeBachs();
        $agent = $this->agentWithBalance();
        $this->connection($agent);

        $this->signedWebhook([
            'id' => 'evt_new',
            'type' => 'account.updated',
            'created_at' => now()->toIso8601String(),
            'data' => ['object' => [
                'id' => 'acct_1',
                'capabilities' => ['transfers' => ['status' => 'active'], 'payouts' => ['status' => 'active']],
                'requirements' => ['currently_due' => []],
            ]],
        ])->assertOk();

        $this->assertTrue(PayoutConnection::for($agent->id, 'bachs')->isActive());

        $this->signedWebhook([
            'id' => 'evt_old',
            'type' => 'capability.updated',
            'account' => 'acct_1',
            'created_at' => now()->subMinutes(5)->toIso8601String(),
            'data' => ['object' => ['capability' => 'payouts', 'status' => 'inactive']],
        ])->assertOk();

        $this->assertTrue(PayoutConnection::for($agent->id, 'bachs')->isActive());
        $this->assertSame('ignored', PaymentWebhook::query()->where('idempotency_key', 'evt:evt_old')->value('status'));
    }

    public function test_unsigned_connect_webhook_is_rejected(): void
    {
        $agent = $this->agentWithBalance();
        $this->connection($agent);

        $this->postJson('/webhooks/payments/bachs', [
            'id' => 'evt_forged',
            'type' => 'account.updated',
            'data' => ['object' => ['id' => 'acct_1', 'capabilities' => ['transfers' => 'active', 'payouts' => 'active']]],
        ])->assertUnauthorized();

        $this->assertFalse(PayoutConnection::for($agent->id, 'bachs')->isActive());
    }

    public function test_payout_is_blocked_until_the_agent_is_connected(): void
    {
        Http::fake();
        $agent = $this->agentWithBalance();
        $account = $this->payoutAccount($agent);

        $result = app(GatewayRegistry::class)->driver('bachs')->initiateTransfer(new PayoutRequest(
            'WPO-1-1', '20.00', PayoutAccountDetails::fromPayoutAccount($account), 'Payout',
        ));

        $this->assertSame(PayoutResult::NOT_CREATED, $result->status);
        $this->assertSame('ACCOUNT_NOT_CONNECTED', $result->failureCode);
        Http::assertNothingSent();
    }

    public function test_activation_registers_every_payout_account_under_one_bachs_account(): void
    {
        $this->fakeBachs();
        $this->coverRoute('US', 'bank_account', ['bachs']);
        $this->coverRoute('GB', 'bank_account', ['bachs']);
        $this->coverRoute('NG', 'bank_account', ['bachs']);

        $agent = $this->agentWithBalance();
        $us = $this->payoutAccount($agent, [
            'country' => 'US', 'currency' => 'USD', 'bank_code' => null, 'bank_name' => 'Example Bank',
            'details' => [
                'routing_number' => '011000015', 'account_number' => '000123456789', 'scheme' => 'ach', 'bank_name' => 'Example Bank',
                'bank_address_line1' => '1 Example Street', 'bank_address_city' => 'Boston', 'bank_address_state' => 'MA', 'bank_address_postal_code' => '02110',
            ],
        ]);
        $gb = $this->payoutAccount($agent, [
            'country' => 'GB', 'currency' => 'GBP', 'bank_code' => null, 'is_primary' => false,
            'details' => ['sort_code' => '400000', 'account_number' => '12345678', 'bank_name' => 'Example Bank'],
        ]);
        $ng = $this->payoutAccount($agent, ['is_primary' => false]);

        $this->connection($agent);
        $this->actingAs($agent)->get(route('agent.payouts.connect.return'));

        $this->assertTrue(PayoutConnection::for($agent->id, 'bachs')->isActive());

        $created = Http::recorded(fn (HttpRequest $r) => $r->method() === 'POST' && parse_url($r->url(), PHP_URL_PATH) === '/v1/payouts/destinations')->values();
        $this->assertCount(3, $created);
        foreach ($created as [$request]) {
            $this->assertSame('acct_1', $request->header('X-Account-Id')[0]);
        }
        $this->assertTrue($created[0][0]['default_for_currency']);
        $this->assertSame('USD', $created[0][0]['currency']);

        foreach ([$us, $gb, $ng] as $account) {
            $this->assertSame('acct_1', data_get($account->fresh()->provider_beneficiary_ids, 'bachs.account'));
        }
        $this->assertCount(0, Http::recorded(fn (HttpRequest $r) => parse_url($r->url(), PHP_URL_PATH) === '/v1/accounts'));
        $this->assertSame(3, PayoutAccount::query()->where('user_id', $agent->id)->count());
    }

    private function fakeBalance(string $available): void
    {
        Http::fake(function (HttpRequest $request) use ($available) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/v1/balances' => Http::response(['balances' => [['currency' => 'USD', 'available_balance' => $available]]]),
                $path === '/v1/transfers' && $request->method() === 'POST' => Http::response(['id' => 'tr_back', 'status' => 'succeeded']),
                default => Http::response(['error_code' => 'NOT_FOUND', 'detail' => 'Not faked'], 404),
            };
        });
    }

    /** @return \Illuminate\Support\Collection<int, array{0: HttpRequest, 1: mixed}> */
    private function sweeps(): \Illuminate\Support\Collection
    {
        return Http::recorded(fn (HttpRequest $r) => $r->method() === 'POST' && parse_url($r->url(), PHP_URL_PATH) === '/v1/transfers')->values();
    }

    public function test_stranded_balance_is_reported_and_swept_only_after_confirmation(): void
    {
        $this->adminUser();
        $this->fakeBalance('3.50');
        $agent = $this->agentWithBalance();
        $this->connection($agent, PayoutConnection::ACTIVE);

        $this->artisan('payments:bachs-stranded', ['--alert' => true])
            ->expectsOutputToContain('acct_1')
            ->assertSuccessful();
        $this->assertDatabaseHas('admin_notifications', ['type' => 'payment.stranded_balance']);
        $this->assertCount(0, $this->sweeps());

        $this->artisan('payments:bachs-stranded', ['--sweep' => true])
            ->expectsConfirmation('Sweep $3.50 from user #'.$agent->id.' (acct_1) back to the platform?', 'yes')
            ->assertSuccessful();

        $sweeps = $this->sweeps();
        $this->assertCount(1, $sweeps);
        $this->assertSame('acct_1', $sweeps[0][0]->header('X-Account-Id')[0]);
        $this->assertSame('acct_1-sweep-'.now()->format('Y-m-d'), $sweeps[0][0]->header('Idempotency-Key')[0]);
        $this->assertSame('self', $sweeps[0][0]['destination']);
        $this->assertSame('3.50', (string) $sweeps[0][0]['amount']);
    }

    public function test_reconcile_retries_a_pending_reclaim_and_skips_it_while_a_payout_is_live(): void
    {
        $this->fakeBalance('20.00');
        $agent = $this->agentWithBalance();
        $account = $this->payoutAccount($agent, ['provider_beneficiary_ids' => ['bachs' => ['account' => 'acct_1']]]);
        $failed = $this->pendingWithdrawal($agent, $account, '20.00');
        $attempt = WithdrawalPayoutAttempt::query()->create([
            'withdrawal_id' => $failed->id, 'attempt_no' => 1, 'gateway_key' => 'bachs', 'reference' => 'WPO-RECLAIM-1',
            'status' => WithdrawalPayoutAttempt::FAILED, 'amount' => '20.00', 'response_snapshot' => ['account' => 'acct_1'],
            'reclaim_pending' => true, 'resolved_at' => now()->subHours(2), 'last_checked_at' => now()->subHours(2),
        ]);

        $other = $this->pendingWithdrawal($agent, $account, '10.00');
        $live = WithdrawalPayoutAttempt::query()->create([
            'withdrawal_id' => $other->id, 'attempt_no' => 1, 'gateway_key' => 'bachs', 'reference' => 'WPO-LIVE-1',
            'status' => WithdrawalPayoutAttempt::PROCESSING, 'live_flag' => 1, 'amount' => '10.00',
        ]);

        $this->assertFalse(app(WithdrawalPayoutService::class)->reclaim($attempt));
        $this->assertCount(0, $this->sweeps());

        $live->update(['status' => WithdrawalPayoutAttempt::SUCCEEDED, 'live_flag' => null]);
        $attempt->update(['last_checked_at' => now()->subHours(2)]);

        $this->artisan('payments:reconcile')->assertSuccessful();

        $sweeps = $this->sweeps();
        $this->assertCount(1, $sweeps);
        $this->assertSame('WPO-RECLAIM-1-reclaim-'.now()->format('YmdH'), $sweeps[0][0]->header('Idempotency-Key')[0]);
        $this->assertTrue($attempt->fresh()->reclaim_pending);
    }
}
