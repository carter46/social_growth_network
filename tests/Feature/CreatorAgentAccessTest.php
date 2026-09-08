<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFunding;
use App\Models\Withdrawal;
use App\Modules\Wallet\Services\DepositCheckoutService;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreatorAgentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_home_route_is_agent_dashboard(): void
    {
        $agent = User::factory()->agent()->create();

        $this->assertSame('/agent', $agent->homeRoute());
    }

    public function test_guest_cannot_access_agent_area(): void
    {
        $this->get('/agent')->assertRedirect(route('login'));
    }

    public function test_unverified_agent_cannot_access_agent_area(): void
    {
        $agent = User::factory()->agent()->unverified()->create();

        $this->actingAs($agent)
            ->get('/agent')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_creator_cannot_access_agent_area(): void
    {
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)->get('/agent')->assertForbidden();
    }

    public function test_agent_cannot_access_creator_dashboard(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->get('/dashboard')->assertForbidden();
    }

    public function test_agent_cannot_access_deposit_url(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->get('/dashboard/deposit')->assertForbidden();
    }

    public function test_agent_cannot_post_deposit_checkout(): void
    {
        $agent = User::factory()->agent()->kycApproved()->create();
        Wallet::factory()->create(['user_id' => $agent->id]);

        $this->actingAs($agent)
            ->post(route('dashboard.deposit.store-checkout'), ['amount' => 1000])
            ->assertForbidden();
    }

    public function test_agent_cannot_access_platform_checkout_or_favorites(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)
            ->get('/checkout/platform/some-product')
            ->assertForbidden();

        $this->actingAs($agent)
            ->post(route('favorites.toggle'), [
                'type' => 'platform_product',
                'id' => 1,
            ])
            ->assertForbidden();
    }

    public function test_agent_cannot_access_creator_services_purchase_routes(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)
            ->get(route('dashboard.services'))
            ->assertForbidden();
    }

    public function test_verified_creator_can_access_dashboard(): void
    {
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)->get('/dashboard')->assertOk();
    }

    public function test_agent_can_open_agent_shell_and_kyc(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)
            ->get('/agent')
            ->assertOk()
            ->assertSee('data-dashboard-shell="agent"', false);

        $this->actingAs($agent)
            ->get(route('agent.account.kyc'))
            ->assertOk();
    }

    public function test_agent_and_creator_cannot_access_admin(): void
    {
        $agent = User::factory()->agent()->create();
        $creator = User::factory()->creator()->create();

        $this->actingAs($agent)->get('/admin')->assertForbidden();
        $this->actingAs($creator)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_still_access_admin_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_admin_email_change_does_not_clear_verification(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.account.profile.update'), [
                'name' => $admin->name,
                'email' => 'new-admin@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.account.profile'));

        $admin->refresh();
        $this->assertSame('new-admin@example.com', $admin->email);
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_creator_email_change_still_requires_reverification(): void
    {
        $creator = User::factory()->creator()->create([
            'email' => 'creator@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($creator)
            ->patch(route('dashboard.account.profile.update'), [
                'name' => $creator->name,
                'email' => 'new-creator@example.com',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($creator->refresh()->email_verified_at);
    }

    public function test_agent_registration_assigns_agent_role(): void
    {
        $response = $this->post(route('register.agent.store'), [
            'name' => 'Agent User',
            'username' => 'agentuser1',
            'email' => 'agentuser1@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'agentuser1@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('agent'));
        $this->assertFalse($user->hasRole('user'));
    }

    public function test_creator_registration_still_assigns_user_role(): void
    {
        $this->post('/register', [
            'name' => 'Creator User',
            'username' => 'creatoruser1',
            'email' => 'creatoruser1@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'creatoruser1@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('agent'));
    }

    public function test_agent_cannot_view_another_users_withdrawal(): void
    {
        $owner = User::factory()->creator()->kycApproved()->create();
        $agent = User::factory()->agent()->kycApproved()->create();
        Wallet::factory()->create(['user_id' => $owner->id, 'balance' => 5000]);
        Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 5000]);

        $withdrawal = Withdrawal::query()->create([
            'user_id' => $owner->id,
            'wallet_id' => $owner->wallet->id,
            'amount' => 100,
            'currency' => 'NGN',
            'bank_name' => 'GTBank',
            'bank_code' => '058',
            'account_number' => '0123456789',
            'account_name' => $owner->name,
            'status' => 'pending',
            'internal_status' => 'pending_review',
            'reference' => 'WD-TEST-1',
        ]);

        $this->actingAs($agent)
            ->get(route('agent.withdrawal.show', $withdrawal))
            ->assertForbidden();
    }

    public function test_credit_from_funding_rejects_agent_wallets(): void
    {
        $agent = User::factory()->agent()->kycApproved()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $agent->id,
            'balance' => 0,
            'reserved_account_number' => '1234567890',
            'reserved_bank_name' => 'Test Bank',
            'reserved_account_reference' => 'RA-AGENT',
        ]);

        $funding = WalletFunding::create([
            'user_id' => $agent->id,
            'wallet_id' => $wallet->id,
            'method' => 'monnify_checkout',
            'amount' => 1000,
            'currency' => 'NGN',
            'status' => 'processing',
            'internal_status' => 'processing',
            'provider' => 'monnify',
            'provider_payment_reference' => 'DEP-AGENT-TEST',
            'reference' => 'DEP-AGENT-TEST',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Agents cannot receive deposit credits');

        app(WalletService::class)->creditFromFunding($funding);
    }

    public function test_reserved_payment_credit_skips_agent_and_clears_rails(): void
    {
        $agent = User::factory()->agent()->kycApproved()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $agent->id,
            'balance' => 0,
            'reserved_account_number' => '9988776655',
            'reserved_bank_name' => 'Test Bank',
            'reserved_account_reference' => 'RA-AGENT-2',
        ]);

        $result = app(DepositCheckoutService::class)->creditReservedPayment([
            'paymentReference' => 'RSV-AGENT-TEST',
            'amountPaid' => '500.00',
            'paymentStatus' => 'PAID',
            'destinationAccountInformation' => [
                'accountNumber' => '9988776655',
            ],
        ]);

        $this->assertNull($result);
        $wallet->refresh();
        $this->assertNull($wallet->reserved_account_number);
        $this->assertSame(0.0, (float) $wallet->balance);
        $this->assertDatabaseMissing('wallet_fundings', [
            'provider_payment_reference' => 'RSV-AGENT-TEST',
        ]);
    }
}
