<?php

namespace Tests\Feature\Auth;

use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function registerAgent(): User
    {
        $referrer = User::factory()->agent()->create();
        $referrer->ensureReferralCode();

        $this->post(route('agent-onboarding.store'), [
            'referral_code' => $referrer->fresh()->referral_code,
            'name' => 'New Agent',
            'date_of_birth' => now()->subYears(24)->toDateString(),
            'username' => 'newagent',
            'email' => 'newagent@example.com',
            'phone' => '+2348012345678',
            'country' => 'NG',
            'address_line1' => '12 Marina Road',
            'address_city' => 'Lagos',
            'address_state' => 'Lagos',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'newagent@example.com')->firstOrFail();
        $this->assertSame('+2348012345678', $user->phone);
        $this->assertSame('NG', $user->country);
        $this->assertSame('12 Marina Road', $user->address_line1);
        $this->assertNotNull($user->profile_completed_at);
        $this->assertTrue($user->hasCompletePayoutProfile());

        return $user;
    }

    public function test_onboarding_signup_page_explains_earning_and_needs_an_invite_code(): void
    {
        $this->get(route('agent-onboarding'))
            ->assertOk()
            ->assertSee('earn', false)
            ->assertSee('invite code', false)
            ->assertSee('name="referral_code"', false)
            ->assertSee('data-account-type="agent"', false);
    }

    public function test_guide_is_shown_once_after_first_email_verification(): void
    {
        $agent = $this->registerAgent();
        DB::table('email_verification_codes')->where('user_id', $agent->id)->update(['code_hash' => Hash::make('123456')]);

        $this->post(route('logout'));
        $this->flushSession();

        $this->actingAs($agent->fresh())
            ->post(route('verification.verify'), ['otp' => '123456'])
            ->assertRedirect(route('agent.onboarding'));

        $this->post(route('logout'));
        $this->post(route('login'), ['login' => 'newagent@example.com', 'email' => 'newagent@example.com', 'password' => 'password'])
            ->assertRedirect(route('agent'));
    }

    public function test_agent_can_skip_the_channel_step_and_use_the_dashboard(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)
            ->get(route('agent.onboarding'))
            ->assertOk()
            ->assertSee('Connect your YouTube channel')
            ->assertSee('Skip for now')
            ->assertSee('href="'.route('agent').'"', false)
            ->assertSee('data-onboarding-connect', false)
            ->assertSee('name="return"', false)
            ->assertSee('/agent/onboarding', false)
            ->assertDontSee('Connect bank account')
            ->assertDontSee('payout', false);

        $this->actingAs($agent)
            ->get(route('agent'))
            ->assertOk()
            ->assertSee('data-channel-banner', false)
            ->assertSee('agent_channel_banner_dismissed:'.$agent->id, false)
            ->assertDontSee('data-channel-banner-reset', false);
    }

    public function test_onboarding_is_a_focused_page_with_a_step_timeline(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)
            ->get(route('agent.onboarding'))
            ->assertOk()
            ->assertSee('aria-label="Onboarding steps"', false)
            ->assertSeeInOrder(['Create your account', 'Verify your email', 'Connect your YouTube channel', 'Pick your first task'])
            ->assertSee('Sign out')
            ->assertDontSee('data-dashboard-sidebar', false)
            ->assertDontSee('<aside', false);
    }

    public function test_registration_sends_a_single_verification_code(): void
    {
        $agent = $this->registerAgent();

        $this->assertSame(1, DB::table('email_verification_codes')->where('user_id', $agent->id)->count());
    }

    public function test_agent_with_a_verified_channel_continues_to_the_marketplace(): void
    {
        $agent = User::factory()->agent()->create();
        $this->linkVerifiedYoutubeChannel($agent);

        $this->actingAs($agent)
            ->get(route('agent.onboarding'))
            ->assertOk()
            ->assertSee('Browse open tasks')
            ->assertSee(route('agent.marketplace'), false)
            ->assertDontSee('Skip for now');

        $this->actingAs($agent)
            ->get(route('agent'))
            ->assertOk()
            ->assertDontSee('Connect your YouTube channel')
            ->assertSee('data-channel-banner-reset', false);
    }

    public function test_applying_for_a_youtube_task_without_a_channel_is_still_refused(): void
    {
        $agent = User::factory()->agent()->kycApproved()->create();
        Wallet::factory()->create(['user_id' => $agent->id]);

        $campaign = Campaign::query()->create([
            'creator_id' => User::factory()->creator()->create()->id,
            'title' => 'YouTube campaign',
            'target_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'meta' => ['platform' => 'youtube'],
            'quantity' => 2,
            'completed_count' => 0,
            'locked_creator_price' => 1000,
            'locked_agent_reward' => 100,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAs($agent)
            ->post(route('agent.marketplace.start', $campaign))
            ->assertRedirect(route('agent.marketplace.show', $campaign))
            ->assertSessionHas('youtube_channel_required', true);

        $this->assertFalse(CampaignParticipation::query()->where('agent_id', $agent->id)->exists());
    }
}
