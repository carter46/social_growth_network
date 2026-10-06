<?php

namespace Tests\Feature;

use App\Events\OrderCompleted;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\Order;
use App\Models\ReferralCommission;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletProvisioningService;
use App\Services\Campaigns\CampaignParticipationService;
use App\Services\Referrals\ReferralCommissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferralProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The SQLite schema still references the dropped escrows table from transactions.
        if (! Schema::hasTable('escrows')) {
            Schema::create('escrows', fn (Blueprint $table) => $table->id());
        }

        SystemSetting::set('referral_enabled', true);
        SystemSetting::set('referral_agent_percent', '50');
        SystemSetting::set('referral_creator_percent', '30');
    }

    private function referrer(bool $withWallet = true): User
    {
        $agent = User::factory()->agent()->kycApproved()->create();
        $agent->ensureReferralCode();

        if ($withWallet) {
            Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 0, 'locked_balance' => 0]);
        }

        return $agent->fresh();
    }

    private function referredAgent(User $referrer): User
    {
        $agent = User::factory()->agent()->kycApproved()->create();
        $agent->forceFill(['referred_by_id' => $referrer->id])->save();
        Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 0, 'locked_balance' => 0]);

        return $agent->fresh();
    }

    private function submittedParticipation(User $agent, float $reward): CampaignParticipation
    {
        $campaign = Campaign::query()->create([
            'creator_id' => User::factory()->creator()->create()->id,
            'title' => 'Referral campaign',
            'quantity' => 5,
            'completed_count' => 0,
            'locked_creator_price' => $reward * 2,
            'locked_agent_reward' => $reward,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        return CampaignParticipation::query()->create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'status' => CampaignParticipation::STATUS_SUBMITTED,
            'started_at' => now(),
            'submitted_at' => now(),
            'reward_amount' => $reward,
        ]);
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'account_type' => 'creator',
            'name' => 'New Person',
            'username' => 'newperson',
            'email' => 'newperson@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ], $overrides);
    }

    public function test_referral_link_prefills_code_and_signup_links_referrer(): void
    {
        $referrer = $this->referrer();

        $this->get(route('register', ['ref' => strtolower($referrer->referral_code)]))
            ->assertOk()
            ->assertSee($referrer->referral_code, false);

        $this->post('/register', $this->registrationPayload(['referral_code' => $referrer->referral_code]))
            ->assertRedirect(route('verification.notice', absolute: false));

        $creator = User::query()->where('email', 'newperson@example.com')->firstOrFail();
        $this->assertSame($referrer->id, $creator->referred_by_id);
        $this->assertNull($creator->referral_code);
    }

    public function test_remembered_link_code_is_used_when_form_field_is_absent(): void
    {
        $referrer = $this->referrer();

        $this->get(route('register', ['ref' => $referrer->referral_code]))->assertOk();

        $this->post('/register', $this->registrationPayload([
            'account_type' => 'agent',
            'username' => 'newagent',
            'email' => 'newagent@example.com',
        ]));

        $agent = User::query()->where('email', 'newagent@example.com')->firstOrFail();
        $this->assertSame($referrer->id, $agent->referred_by_id);
        $this->assertNotNull($agent->referral_code);
        $this->assertNotSame($referrer->referral_code, $agent->referral_code);
    }

    public function test_invalid_or_creator_code_is_rejected(): void
    {
        $this->post('/register', $this->registrationPayload(['referral_code' => 'NOPE1234']))
            ->assertSessionHasErrors('referral_code');

        $creator = User::factory()->creator()->create();
        $creator->forceFill(['referral_code' => 'CREATOR2'])->save();

        $this->post('/register', $this->registrationPayload(['referral_code' => 'CREATOR2']))
            ->assertSessionHasErrors('referral_code');

        $this->assertGuest();
    }

    public function test_creators_never_get_a_referral_code(): void
    {
        $creator = User::factory()->creator()->create();

        $this->assertNull($creator->ensureReferralCode());
        $this->assertNull($creator->fresh()->referral_code);
    }

    public function test_referred_agent_reward_pays_referrer_percentage_once(): void
    {
        $referrer = $this->referrer();
        $agent = $this->referredAgent($referrer);
        $participation = $this->submittedParticipation($agent, 4);

        $service = app(CampaignParticipationService::class);
        $service->approve($participation);
        $service->approve($participation->fresh());

        $commission = ReferralCommission::query()->sole();
        $this->assertSame(ReferralCommission::KIND_AGENT_EARNING, $commission->kind);
        $this->assertSame(ReferralCommission::STATUS_CREDITED, $commission->status);
        $this->assertEquals(4.0, (float) $commission->base_amount);
        $this->assertEquals(50.0, (float) $commission->rate);
        $this->assertEquals(2.0, (float) $commission->amount);
        $this->assertEquals(2.0, (float) $referrer->wallet()->first()->balance);
        $this->assertEquals(4.0, (float) $agent->wallet()->first()->balance);
        $this->assertSame('referral_commission', Transaction::query()->find($commission->transaction_id)->type);
    }

    public function test_rate_is_frozen_when_commission_is_created(): void
    {
        $referrer = $this->referrer();
        $agent = $this->referredAgent($referrer);

        app(CampaignParticipationService::class)->approve($this->submittedParticipation($agent, 10));
        SystemSetting::set('referral_agent_percent', '10');
        app(CampaignParticipationService::class)->approve($this->submittedParticipation($agent, 10));

        $amounts = ReferralCommission::query()->orderBy('id')->pluck('amount')->map(fn ($a) => (float) $a)->all();
        $this->assertSame([5.0, 1.0], $amounts);
    }

    public function test_paid_creator_order_pays_commission_once_and_unpaid_orders_do_not(): void
    {
        $referrer = $this->referrer();
        $creator = User::factory()->creator()->create();
        $creator->forceFill(['referred_by_id' => $referrer->id])->save();

        $pending = Order::factory()->create(['user_id' => $creator->id, 'total_amount' => 8000, 'status' => 'pending']);
        $cancelled = Order::factory()->create(['user_id' => $creator->id, 'total_amount' => 8000, 'status' => 'cancelled']);
        OrderCompleted::dispatch($pending->id, $creator->id, null);
        OrderCompleted::dispatch($cancelled->id, $creator->id, null);
        $this->assertSame(0, ReferralCommission::query()->count());

        $paid = Order::factory()->create(['user_id' => $creator->id, 'total_amount' => 8000, 'status' => 'paid']);
        OrderCompleted::dispatch($paid->id, $creator->id, null);
        OrderCompleted::dispatch($paid->id, $creator->id, null);

        $commission = ReferralCommission::query()->sole();
        $this->assertSame(ReferralCommission::KIND_CREATOR_SPEND, $commission->kind);
        $this->assertEquals(2400.0, (float) $commission->amount);
        $this->assertEquals(2400.0, (float) $referrer->wallet()->first()->balance);
    }

    public function test_commission_waits_for_wallet_and_is_released_only_once(): void
    {
        SystemSetting::set('kyc_required', '0');
        $referrer = $this->referrer(withWallet: false);
        $agent = $this->referredAgent($referrer);

        app(CampaignParticipationService::class)->approve($this->submittedParticipation($agent, 4));

        $commission = ReferralCommission::query()->sole();
        $this->assertSame(ReferralCommission::STATUS_PENDING, $commission->status);

        app(WalletProvisioningService::class)->createWallet($referrer);
        app(ReferralCommissionService::class)->releasePending($referrer);

        $this->assertSame(ReferralCommission::STATUS_CREDITED, $commission->fresh()->status);
        $this->assertEquals(2.0, (float) $referrer->wallet()->first()->balance);
        $this->assertSame(1, Transaction::query()->where('type', 'referral_commission')->count());
    }

    public function test_no_commission_when_program_off_or_referrer_suspended(): void
    {
        $referrer = $this->referrer();
        $agent = $this->referredAgent($referrer);

        SystemSetting::set('referral_enabled', false);
        app(CampaignParticipationService::class)->approve($this->submittedParticipation($agent, 4));

        SystemSetting::set('referral_enabled', true);
        $referrer->suspend();
        app(CampaignParticipationService::class)->approve($this->submittedParticipation($agent, 4));

        $this->assertSame(0, ReferralCommission::query()->count());
    }

    public function test_reverse_for_source_cancels_pending_and_claws_back_credited(): void
    {
        $service = app(ReferralCommissionService::class);

        $referrer = $this->referrer();
        $agent = $this->referredAgent($referrer);
        $participation = $this->submittedParticipation($agent, 4);
        app(CampaignParticipationService::class)->approve($participation);

        $this->assertSame(1, $service->reverseForSource($participation->fresh(), 'Reward revoked'));
        $this->assertSame(0, $service->reverseForSource($participation->fresh(), 'Reward revoked'));

        $credited = ReferralCommission::query()->sole();
        $this->assertSame(ReferralCommission::STATUS_REVERSED, $credited->status);
        $this->assertNotNull($credited->reversal_transaction_id);
        $this->assertEquals(0.0, (float) $referrer->wallet()->first()->balance);

        $waiting = $this->referrer(withWallet: false);
        $other = $this->referredAgent($waiting);
        $otherParticipation = $this->submittedParticipation($other, 4);
        app(CampaignParticipationService::class)->approve($otherParticipation);

        $service->reverseForSource($otherParticipation->fresh(), 'Reward revoked');
        $pending = ReferralCommission::query()->where('referrer_id', $waiting->id)->sole();
        $this->assertSame(ReferralCommission::STATUS_REVERSED, $pending->status);
        $this->assertNull($pending->reversal_transaction_id);
    }

    public function test_agent_sees_referrals_page_and_creator_is_blocked(): void
    {
        $referrer = $this->referrer();
        $this->referredAgent($referrer);

        $this->actingAs($referrer)
            ->get(route('agent.referrals'))
            ->assertOk()
            ->assertSee($referrer->referral_code, false)
            ->assertSee('ref='.$referrer->referral_code, false)
            ->assertSee('Agents referred')
            ->assertSee('Referral history');

        $this->actingAs(User::factory()->creator()->create())
            ->get('/agent/referrals')
            ->assertForbidden();
    }

    private function mockGoogleSignup(string $email): void
    {
        $row = \App\Models\IntegrationProvider::forProvider(\App\Models\IntegrationProvider::GOOGLE_IDENTITY);
        $row->enabled = true;
        $row->mergeCredentials(['client_id' => 'test-client-id.apps.googleusercontent.com']);
        $row->status = 'connected';
        $row->save();

        $mock = \Mockery::mock(\App\Services\Auth\Identity\GoogleIdentityProvider::class);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('name')->andReturn(\App\Models\UserAuthProvider::GOOGLE);
        $mock->shouldReceive('verifyCredential')->with('valid-credential')->andReturn(new \App\Services\Auth\Identity\VerifiedIdentity(
            provider: \App\Models\UserAuthProvider::GOOGLE,
            providerUserId: 'google-sub-ref',
            email: $email,
            emailVerified: true,
            name: 'Google Person',
            avatarUrl: null,
        ));

        $this->app->instance(\App\Services\Auth\Identity\GoogleIdentityProvider::class, $mock);
        $this->app->forgetInstance(\App\Services\Auth\Identity\SocialAuthService::class);
    }

    public function test_google_signup_uses_typed_referral_code_and_rejects_invalid_one(): void
    {
        $referrer = $this->referrer();
        $this->mockGoogleSignup('google.person@example.com');

        $this->postJson(route('auth.google'), [
            'credential' => 'valid-credential',
            'account_type' => 'agent',
            'referral_code' => 'BADCODE9',
        ])->assertStatus(422)->assertJson(['message' => 'This referral code is not valid.']);
        $this->assertGuest();

        $this->postJson(route('auth.google'), [
            'credential' => 'valid-credential',
            'account_type' => 'agent',
            'referral_code' => strtolower($referrer->referral_code),
        ])->assertOk();

        $user = User::query()->where('email', 'google.person@example.com')->firstOrFail();
        $this->assertSame($referrer->id, $user->referred_by_id);
        $this->assertNotNull($user->referral_code);
    }

    public function test_link_with_invalid_code_is_not_remembered_or_prefilled(): void
    {
        $creator = User::factory()->creator()->create();
        $creator->forceFill(['referral_code' => 'CREATOR3'])->save();

        $this->get(route('register', ['ref' => 'CREATOR3']))
            ->assertOk()
            ->assertSessionMissing('referral_code')
            ->assertDontSee('value="CREATOR3"', false);
    }

    public function test_release_command_credits_stuck_pending_commissions(): void
    {
        $referrer = $this->referrer();
        $agent = $this->referredAgent($referrer);
        $participation = $this->submittedParticipation($agent, 4);

        $commission = ReferralCommission::query()->create([
            'referrer_id' => $referrer->id,
            'referred_user_id' => $agent->id,
            'kind' => ReferralCommission::KIND_AGENT_EARNING,
            'source_type' => $participation->getMorphClass(),
            'source_id' => $participation->id,
            'base_amount' => 4,
            'rate' => 50,
            'amount' => 2,
            'status' => ReferralCommission::STATUS_PENDING,
        ]);

        $this->artisan('referrals:release-pending')->assertSuccessful();
        $this->artisan('referrals:release-pending')->assertSuccessful();

        $this->assertSame(ReferralCommission::STATUS_CREDITED, $commission->fresh()->status);
        $this->assertEquals(2.0, (float) $referrer->wallet()->first()->balance);
    }

    public function test_admin_can_save_referral_percentages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.settings.referrals'), [
                'referral_enabled' => '1',
                'referral_agent_percent' => '40',
                'referral_creator_percent' => '25.5',
            ])
            ->assertSessionHasNoErrors();

        $settings = SystemSetting::referralSettings();
        $this->assertTrue($settings['enabled']);
        $this->assertSame(40.0, $settings['agent_percent']);
        $this->assertSame(25.5, $settings['creator_percent']);

        $this->actingAs($admin)
            ->post(route('admin.settings.referrals'), [
                'referral_agent_percent' => '150',
                'referral_creator_percent' => '10',
            ])
            ->assertSessionHasErrors('referral_agent_percent');
    }
}
