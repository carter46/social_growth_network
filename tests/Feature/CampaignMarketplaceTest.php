<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\User;
use App\Services\Campaigns\CampaignParticipationService;
use App\Services\Campaigns\EngagementVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CampaignMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_start_submit_and_admin_can_approve_reward(): void
    {
        $creator = User::factory()->creator()->create();
        $agent = User::factory()->agent()->kycApproved()->create();
        \App\Models\Wallet::factory()->create([
            'user_id' => $agent->id,
            'balance' => 0,
            'locked_balance' => 0,
        ]);

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'platform_product_id' => null,
            'title' => 'Follow campaign',
            'quantity' => 2,
            'completed_count' => 0,
            'locked_creator_price' => 5000,
            'locked_agent_reward' => 250,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAs($agent)
            ->post(route('agent.marketplace.start', $campaign))
            ->assertRedirect();

        $participation = CampaignParticipation::query()->first();
        $this->assertNotNull($participation);
        $this->assertSame(CampaignParticipation::STATUS_STARTED, $participation->status);
        $this->assertEquals(250.0, (float) $participation->reward_amount);

        $this->actingAs($agent)
            ->post(route('agent.tasks.submit', $participation), [
                'proof_url' => 'https://example.com/proof',
                'proof_notes' => 'Done',
            ])
            ->assertRedirect(route('agent.tasks.show', $participation));

        $this->assertSame(CampaignParticipation::STATUS_SUBMITTED, $participation->fresh()->status);

        $admin = User::factory()->admin()->create();
        app(CampaignParticipationService::class)->approve($participation->fresh(), $admin);

        $participation->refresh();
        $this->assertSame(CampaignParticipation::STATUS_PAID, $participation->status);
        $this->assertSame(1, $campaign->fresh()->completed_count);
        $this->assertEquals(250.0, (float) $agent->wallet()->first()->balance);
    }

    public function test_creator_is_notified_when_campaign_goes_live_and_on_status_change(): void
    {
        $creator = User::factory()->creator()->create();

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Notify campaign',
            'quantity' => 3,
            'completed_count' => 0,
            'locked_creator_price' => 1000,
            'locked_agent_reward' => 100,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.campaigns.status', $campaign), ['status' => Campaign::STATUS_REJECTED])
            ->assertRedirect();

        $types = \App\Models\UserNotification::query()->where('user_id', $creator->id)->pluck('type')->all();
        $this->assertContains('campaign.active', $types);
        $this->assertContains('campaign.rejected', $types);
    }

    public function test_campaign_pages_render_for_creator_and_admin(): void
    {
        $creator = User::factory()->creator()->create();
        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Render campaign',
            'quantity' => 4,
            'completed_count' => 1,
            'locked_creator_price' => 2000,
            'locked_agent_reward' => 1000,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAs($creator)
            ->get(route('dashboard.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Campaign cost')
            ->assertSee('8,000.00')
            ->assertDontSee('Agent reward');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Agent payouts')
            ->assertSee('What the creator bought');
    }

    public function test_agent_cannot_submit_on_cancelled_campaign(): void
    {
        $creator = User::factory()->creator()->create();
        $agent = User::factory()->agent()->kycApproved()->create();
        \App\Models\Wallet::factory()->create(['user_id' => $agent->id]);

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Cancel campaign',
            'quantity' => 2,
            'completed_count' => 0,
            'locked_creator_price' => 1000,
            'locked_agent_reward' => 100,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAs($agent)->post(route('agent.marketplace.start', $campaign))->assertRedirect();
        $participation = CampaignParticipation::query()->firstOrFail();

        $campaign->update(['status' => Campaign::STATUS_CANCELLED]);

        $this->actingAs($agent)
            ->post(route('agent.tasks.submit', $participation), ['proof_url' => 'https://example.com/proof'])
            ->assertSessionHas('error');

        $this->assertSame(CampaignParticipation::STATUS_STARTED, $participation->fresh()->status);
    }

    public function test_rejected_agent_cannot_retake_and_marketplace_hides_joined(): void
    {
        $creator = User::factory()->creator()->create();
        $agent = User::factory()->agent()->kycApproved()->create();
        \App\Models\Wallet::factory()->create(['user_id' => $agent->id]);

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Like campaign',
            'quantity' => 5,
            'completed_count' => 0,
            'locked_creator_price' => 1000,
            'locked_agent_reward' => 100,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAs($agent)->post(route('agent.marketplace.start', $campaign))->assertRedirect();
        $participation = CampaignParticipation::query()->firstOrFail();

        $this->actingAs($agent)->post(route('agent.tasks.submit', $participation), [
            'proof_url' => 'https://example.com/proof.png',
        ]);

        app(CampaignParticipationService::class)->reject($participation->fresh(), User::factory()->admin()->create(), 'Bad proof');

        $this->actingAs($agent)
            ->post(route('agent.marketplace.start', $campaign))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($agent)
            ->get(route('agent.marketplace'))
            ->assertOk()
            ->assertDontSee('Like campaign');
    }

    public function test_locked_agent_reward_is_immutable_after_campaign_create(): void
    {
        $creator = User::factory()->creator()->create();
        $agent = User::factory()->agent()->kycApproved()->create();
        \App\Models\Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 0]);

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Reward lock',
            'quantity' => 1,
            'completed_count' => 0,
            'locked_creator_price' => 1000,
            'locked_agent_reward' => 100,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $this->assertEquals(100.0, (float) $participation->reward_amount);

        // Product/admin price changes must not rewrite locked campaign reward.
        $campaign->update(['locked_agent_reward' => 999]);
        $this->assertEquals(100.0, (float) $campaign->fresh()->locked_agent_reward);
        $participation->refresh();
        $this->assertEquals(100.0, (float) $participation->reward_amount);

        app(CampaignParticipationService::class)->submit($participation, ['proof_url' => 'https://example.com/p']);
        app(CampaignParticipationService::class)->approve($participation->fresh(), User::factory()->admin()->create());

        $this->assertEquals(100.0, (float) $agent->wallet()->first()->balance);
    }

    public function test_count_change_verification_uses_pre_and_post_not_baseline_plus_completed(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push('{"likeCount":"10"}', 200, ['Content-Type' => 'text/html'])
                ->push('{"likeCount":"11"}', 200, ['Content-Type' => 'text/html']),
        ]);

        $creator = User::factory()->creator()->create();
        $agent = User::factory()->agent()->kycApproved()->create();
        \App\Models\Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 0]);

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'YT Likes',
            'target_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'engagement_metric' => 'likes',
            'baseline_count' => 5,
            'last_verified_count' => 5,
            'verification_mode' => 'count_change',
            'quantity' => 3,
            'completed_count' => 2,
            'locked_creator_price' => 1000,
            'locked_agent_reward' => 50,
            'status' => Campaign::STATUS_ACTIVE,
            'meta' => ['platform' => 'youtube', 'product_slug' => 'youtube-likes'],
        ]);

        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        app(CampaignParticipationService::class)->submit($participation, [
            'proof_url' => 'https://example.com/shot.png',
        ]);

        $service = app(EngagementVerificationService::class);
        $this->assertTrue($service->processNextForCampaign($campaign->id));

        $participation->refresh();
        $this->assertSame(CampaignParticipation::STATUS_VERIFYING, $participation->status);
        $this->assertSame(10, (int) $participation->pre_count);

        // Force settle window elapsed.
        $participation->update(['verification_started_at' => now()->subMinutes(5)]);
        $campaign->refresh();
        $campaign->verification_locked_participation_id = $participation->id;
        $campaign->save();

        $this->assertTrue($service->processNextForCampaign($campaign->id));
        $participation->refresh();
        $this->assertSame(CampaignParticipation::STATUS_PAID, $participation->status);
        $this->assertSame(11, (int) $participation->post_count);
        $this->assertSame(3, (int) $campaign->fresh()->completed_count);
    }

    public function test_creator_cannot_open_marketplace_by_url(): void
    {
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)->get(route('agent.marketplace'))->assertForbidden();
    }

    public function test_watch_claim_rejected_for_likes_campaign(): void
    {
        $creator = User::factory()->creator()->create();
        $agent = User::factory()->agent()->kycApproved()->create();
        \App\Models\Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 0]);

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'YT Likes no watch',
            'target_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'engagement_metric' => 'likes',
            'verification_mode' => 'count_change',
            'quantity' => 1,
            'completed_count' => 0,
            'locked_creator_price' => 1000,
            'locked_agent_reward' => 50,
            'status' => Campaign::STATUS_ACTIVE,
            'meta' => ['platform' => 'youtube', 'product_slug' => 'youtube-likes'],
        ]);

        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);

        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\Campaigns\CampaignWatchSessionService::class)->start($participation);
    }

    public function test_marketplace_shows_task_cards_and_detail_tiles(): void
    {
        $creator = User::factory()->creator()->create();
        $agent = User::factory()->agent()->kycApproved()->create();
        \App\Models\Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 0]);

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Card campaign',
            'target_url' => 'https://example.com/post',
            'quantity' => 3,
            'completed_count' => 0,
            'locked_creator_price' => 3000,
            'locked_agent_reward' => 120,
            'estimated_minutes' => 4,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAs($agent)->get(route('agent.marketplace'))
            ->assertOk()
            ->assertSee('Card campaign')
            ->assertSee('₦120.00', false)
            ->assertSee('View task')
            ->assertDontSee('<table', false);

        $this->actingAs($agent)->get(route('agent.marketplace.show', $campaign))
            ->assertOk()
            ->assertSee('Slots left')
            ->assertSee('Estimated time')
            ->assertSee('https://example.com/post');
    }

    public function test_watch_task_starts_session_without_reload_and_has_no_open_platform_link(): void
    {
        $creator = User::factory()->creator()->create();
        $agent = User::factory()->agent()->kycApproved()->create();
        \App\Models\Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 0]);

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'YT watch hours',
            'target_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'engagement_metric' => 'watch_hours',
            'quantity' => 1,
            'completed_count' => 0,
            'locked_creator_price' => 1000,
            'locked_agent_reward' => 50,
            'estimated_minutes' => 1,
            'status' => Campaign::STATUS_ACTIVE,
            'meta' => ['platform' => 'youtube', 'product_slug' => 'youtube-watch-hours'],
        ]);

        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);

        $this->actingAs($agent)->get(route('agent.tasks.show', $participation))
            ->assertOk()
            ->assertSee('youtube.com/embed/', false)
            ->assertSee('Press the play button on the video below')
            ->assertDontSee('Open on platform');

        $this->actingAs($agent)
            ->postJson(route('agent.tasks.start-watch', $participation))
            ->assertOk()
            ->assertJsonStructure(['token', 'required_seconds'])
            ->assertJson(['required_seconds' => 60]);
    }

    public function test_withdrawal_page_explains_insufficient_balance_instead_of_redirecting(): void
    {
        \App\Models\SystemSetting::set('kyc_required', '0');
        $agent = User::factory()->agent()->kycApproved()->create();
        \App\Models\Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 0, 'locked_balance' => 0]);
        \App\Models\UserBankAccount::create([
            'user_id' => $agent->id,
            'bank_name' => 'GTBank',
            'bank_code' => '058',
            'account_number' => '0123456789',
            'verified_name' => 'Agent Name',
            'verified_at' => now(),
            'verified_by' => 'monnify',
            'active' => true,
        ]);

        $this->actingAs($agent)->get(route('agent.withdrawal.create'))
            ->assertOk()
            ->assertSee('Insufficient balance')
            ->assertSee('Find tasks')
            ->assertDontSee('Send email code');
    }
}
