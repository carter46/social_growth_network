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
            $method = $request->method();

            return match (true) {
                $path === '/v1/accounts' && $method === 'POST' => Http::response([
                    'id' => 'acct_1',
                    'entity_type' => 'individual',
                    'capabilities' => ['transfers' => ['status' => 'inactive'], 'payouts' => ['status' => 'inactive']],
                    'requirements' => ['currently_due' => ['individual.id_number']],
                ]),
                $path === '/v1/accounts/acct_1' && $method === 'POST' => Http::response([
                    'id' => 'acct_1',
                    'entity_type' => 'individual',
                    'capabilities' => $capabilities,
                    'requirements' => [],
                ]),
                $path === '/v1/accounts/acct_1/account-links' => Http::response(['url' => self::ONBOARDING_URL]),
                $path === '/v1/accounts/acct_1/capabilities' => Http::response(['capabilities' => $capabilities]),
                $path === '/v1/accounts/acct_1' => Http::response(['id' => 'acct_1', 'capabilities' => $capabilities, 'requirements' => []]),
                $path === '/v1/accounts/acct_1/persons' && $method === 'GET' => Http::response(['items' => []]),
                $path === '/v1/accounts/acct_1/persons' && $method === 'POST' => Http::response([
                    'id' => 'per_1',
                    'first_name' => 'Ada',
                    'relationship' => ['representative' => true],
                ]),
                $path === '/v1/utilities/uploads' => Http::response(['upload_id' => 'upl_1']),
                str_starts_with((string) $path, '/v1/accounts/acct_1/persons/') && str_ends_with((string) $path, '/documents') => Http::response(['id' => 'doc_1']),
                $path === '/v1/reference/banks' => Http::response(['data' => [['code' => '058', 'name' => 'GTBank']]]),
                $path === '/v1/misc/bank-accounts/resolve' => Http::response([
                    'account_number' => '0123456789',
                    'account_name' => 'ADA OBI',
                    'bank_code' => '058',
                ]),
                $path === '/v1/currencies/payout-supported' => Http::response(['currencies' => []]),
                $path === '/v1/payouts/destinations' && $method === 'GET' => Http::response(['data' => []]),
                $path === '/v1/payouts/destinations' => Http::response(['id' => 'pd_'.(++$destinations), 'is_usable' => true, 'status' => 'active']),
                default => Http::response(['error_code' => 'NOT_FOUND', 'detail' => 'Not faked: '.$method.' '.$path], 404),
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

    public function test_wallet_shows_connect_button_and_form_stays_on_site(): void
    {
        $this->fakeBachs();
        $agent = $this->agentWithBalance();

        $this->actingAs($agent)->get(route('agent.wallet'))
            ->assertOk()
            ->assertSee('Open My Bank', false)
            ->assertSee('Not connected', false)
            ->assertDontSee('Bachs', false)
            ->assertDontSee('Self-serve deposits', false);

        $this->actingAs($agent)->get(route('agent.banks.index'))
            ->assertOk()
            ->assertSee('Connect bank account', false);

        $this->actingAs($agent)->get(route('agent.payouts.connect'))
            ->assertOk()
            ->assertSee('Connect bank account')
            ->assertSee('You stay on this page')
            ->assertDontSee('registered business', false);

        $this->actingAs($agent)->get(route('agent.payouts.connect.refresh'))
            ->assertRedirect(route('agent.payouts.connect'));
    }

    public function test_api_onboarding_creates_individual_recipient_without_external_redirect(): void
    {
        $this->fakeBachs();
        $this->coverRoute('NG', 'bank_account', ['bachs']);
        $agent = $this->agentWithBalance('100.00', 'Ada Obi');

        $this->actingAs($agent)->post(route('agent.payouts.connect.submit'), [
            'country' => 'NG',
            'first_name' => 'Ada',
            'last_name' => 'Obi',
            'email' => $agent->email,
            'phone' => '+2348012345678',
            'dob' => '1995-06-15',
            'address_line1' => '12 Marina Road',
            'address_city' => 'Lagos',
            'address_state' => 'Lagos',
            'id_type' => 'id_card',
            'id_number' => 'A12345678',
            'method' => 'bank_account',
            'bank_code' => '058',
            'account_number' => '0123456789',
            'account_name' => 'Ada Obi',
        ])->assertRedirect(route('agent.wallet'));

        $creates = Http::recorded(fn (HttpRequest $r) => $r->method() === 'POST' && parse_url($r->url(), PHP_URL_PATH) === '/v1/accounts')->values();
        $this->assertCount(1, $creates);
        $this->assertSame('individual', $creates[0][0]['entity_type']);
        $this->assertSame('NG', $creates[0][0]['country']);
        $this->assertArrayHasKey('recipient', $creates[0][0]['configuration']);
        $this->assertArrayNotHasKey('merchant', $creates[0][0]['configuration']);

        $persons = Http::recorded(fn (HttpRequest $r) => $r->method() === 'POST' && parse_url($r->url(), PHP_URL_PATH) === '/v1/accounts/acct_1/persons');
        $this->assertCount(1, $persons);

        $updates = Http::recorded(fn (HttpRequest $r) => $r->method() === 'POST' && parse_url($r->url(), PHP_URL_PATH) === '/v1/accounts/acct_1')->values();
        $this->assertNotEmpty($updates);
        $this->assertSame('0123456789', data_get($updates->last()[0], 'fields.payout_destination.account_number'));

        $this->assertSame('acct_1', PayoutConnection::for($agent->id, 'bachs')?->account_id);
        $this->assertCount(0, Http::recorded(fn (HttpRequest $r) => str_contains((string) parse_url($r->url(), PHP_URL_PATH), 'account-links')));
    }

    public function test_international_country_uses_hosted_partner_bridge(): void
    {
        $this->fakeBachs();
        $agent = $this->agentWithBalance();
        $agent->forceFill([
            'country' => 'US',
            'phone' => '+12025550123',
            'address_line1' => '1 Main St',
            'address_city' => 'Boston',
            'address_state' => 'MA',
            'profile_completed_at' => now(),
        ])->save();

        $this->actingAs($agent)->get(route('agent.payouts.connect'))
            ->assertOk()
            ->assertSee('secure form', false)
            ->assertSee(route('agent.payouts.connect.hosted'), false);

        $this->actingAs($agent)->post(route('agent.payouts.connect.hosted'))
            ->assertRedirect(self::ONBOARDING_URL);
    }

    public function test_connect_resolve_returns_bank_account_name(): void
    {
        $this->fakeBachs();
        $this->coverRoute('NG', 'bank_account', ['bachs']);
        $agent = $this->agentWithBalance();

        $this->actingAs($agent)->postJson(route('agent.payouts.connect.resolve'), [
            'country' => 'NG',
            'method' => 'bank_account',
            'bank_code' => '058',
            'account_number' => '0123456789',
        ])
            ->assertOk()
            ->assertJsonPath('account_name', 'ADA OBI');
    }

    public function test_connect_form_requires_id_fields_and_shows_resolve_hints(): void
    {
        $this->fakeBachs();
        $this->coverRoute('NG', 'bank_account', ['bachs']);
        $agent = $this->agentWithBalance();

        $this->actingAs($agent)->get(route('agent.payouts.connect'))
            ->assertOk()
            ->assertSee('ID type', false)
            ->assertDontSee('if required', false)
            ->assertSee('fills in automatically', false)
            ->assertSee('resolveUrl', false);

        $this->actingAs($agent)->post(route('agent.payouts.connect.submit'), [
            'country' => 'NG',
            'first_name' => 'Ada',
            'last_name' => 'Obi',
            'email' => $agent->email,
            'phone' => '+2348012345678',
            'dob' => '1995-06-15',
            'address_line1' => '12 Marina Road',
            'address_city' => 'Lagos',
            'address_state' => 'Lagos',
            'method' => 'bank_account',
            'bank_code' => '058',
            'account_number' => '0123456789',
            'account_name' => 'Ada Obi',
        ])->assertSessionHasErrors(['id_type', 'id_number']);
    }

    public function test_active_connect_keeps_primary_route_available_without_coverage(): void
    {
        $this->fakeBachs();
        $agent = $this->agentWithBalance();
        $account = $this->payoutAccount($agent, ['country' => 'NG', 'method' => 'bank_account', 'is_primary' => true]);
        $this->connection($agent, PayoutConnection::ACTIVE, [
            'transfers' => 'active',
            'payouts' => 'active',
        ]);

        // No coverRoute() — coverage empty must not block Connect agents.
        $this->assertTrue($account->fresh()->routeAvailable());
    }

    public function test_incomplete_profile_blocks_connect_and_shows_wallet_banner(): void
    {
        $this->fakeBachs();
        $agent = $this->agentWithBalance();
        $agent->forceFill([
            'phone' => null,
            'country' => null,
            'address_line1' => null,
            'address_city' => null,
            'address_state' => null,
            'profile_completed_at' => null,
        ])->save();

        $this->actingAs($agent)->get(route('agent.payouts.connect'))
            ->assertRedirect(route('agent.wallet'));

        $this->actingAs($agent)->get(route('agent.wallet'))
            ->assertOk()
            ->assertSee('Update your payout profile', false)
            ->assertDontSee('Update payout details', false);

        $this->actingAs($agent)->get(route('agent.banks.index'))
            ->assertOk()
            ->assertSee('Payout setup', false)
            ->assertSee('Connect bank account', false);
    }

    public function test_return_reads_the_account_from_bachs_before_showing_ready(): void
    {
        $this->fakeBachs();
        $agent = $this->agentWithBalance();
        $this->connection($agent);

        $this->actingAs($agent)->get(route('agent.payouts.connect.return'))
            ->assertRedirect(route('agent.banks.replace'))
            ->assertSessionHas('status');

        $this->assertTrue(PayoutConnection::for($agent->id, 'bachs')->isActive());
    }

    public function test_return_imports_hosted_bachs_destinations_as_local_payout_accounts(): void
    {
        Http::fake(function (HttpRequest $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/v1/accounts/acct_1/capabilities' => Http::response(['capabilities' => [
                    'transfers' => ['status' => 'active'],
                    'payouts' => ['status' => 'active'],
                ]]),
                $path === '/v1/accounts/acct_1' => Http::response([
                    'id' => 'acct_1',
                    'country' => 'NG',
                    'capabilities' => [
                        'transfers' => ['status' => 'active'],
                        'payouts' => ['status' => 'active'],
                    ],
                    'requirements' => [],
                ]),
                $path === '/v1/payouts/destinations' && $request->method() === 'GET' => Http::response(['data' => [[
                    'id' => 'pd_hosted_ng',
                    'type' => 'bank_account',
                    'currency' => 'NGN',
                    'country' => 'NG',
                    'is_usable' => true,
                    'status' => 'active',
                    'last4' => '7890',
                    'account_name' => 'Ada Obi',
                    'bank_name' => 'GTBank',
                    'bank_code' => '058',
                ]]]),
                default => Http::response(['error_code' => 'NOT_FOUND', 'detail' => 'Not faked'], 404),
            };
        });

        $agent = $this->agentWithBalance('100.00', 'Ada Obi');
        $agent->forceFill(['country' => 'NG'])->save();
        $this->connection($agent);

        $this->actingAs($agent)->get(route('agent.payouts.connect.return'))
            ->assertRedirect(route('agent.wallet'))
            ->assertSessionHas('success');

        $account = PayoutAccount::query()->where('user_id', $agent->id)->where('active', true)->first();
        $this->assertNotNull($account);
        $this->assertSame('NG', $account->country);
        $this->assertSame('NGN', $account->currency);
        $this->assertSame('bank_account', $account->method);
        $this->assertTrue($account->is_primary);
        $this->assertSame(PayoutAccount::RESOLVED, $account->verification);
        $this->assertSame('pd_hosted_ng', data_get($account->provider_beneficiary_ids, 'bachs.destination'));
        $this->assertSame('acct_1', data_get($account->provider_beneficiary_ids, 'bachs.account'));
        $this->assertSame('****7890', $account->display_mask);
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
