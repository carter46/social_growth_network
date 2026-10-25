<?php

namespace Tests\Feature;

use App\Models\AgentSocialFollowSubmission;
use App\Models\Campaign;
use App\Models\SocialLink;
use App\Models\User;
use App\Services\Campaigns\AgentSocialFollowService;
use App\Services\Communications\Social\SocialLinkRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentSocialFollowTest extends TestCase
{
    use RefreshDatabase;

    private function agent(): User
    {
        return User::factory()->agent()->kycApproved()->create();
    }

    private function social(string $platform = 'tiktok'): SocialLink
    {
        $link = SocialLink::query()->create([
            'platform' => $platform,
            'url' => 'https://www.'.$platform.'.com/@example',
            'icon' => $platform,
            'enabled' => true,
            'sort_order' => 1,
        ]);
        app(SocialLinkRepository::class)->flush();

        return $link;
    }

    public function test_agent_cannot_start_campaign_before_follow_approval(): void
    {
        $this->social();
        $agent = $this->agent();
        $creator = User::factory()->creator()->create();
        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Follow gate campaign',
            'quantity' => 1,
            'completed_count' => 0,
            'locked_creator_price' => 10,
            'locked_agent_reward' => 1,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAs($agent)
            ->post(route('agent.marketplace.start', $campaign))
            ->assertRedirect(route('agent.social-follow'));
    }

    public function test_follow_task_accepts_proofs_and_admin_can_approve(): void
    {
        Storage::fake('public');
        $tiktok = $this->social('tiktok');
        $this->social('instagram');
        $agent = $this->agent();
        $admin = User::factory()->create();

        $service = app(AgentSocialFollowService::class);
        $submission = $service->ensureSubmission($agent);
        $this->assertNotNull($submission);
        $this->assertCount(2, $submission->proofs);

        $service->start($agent);
        $submission = $submission->fresh();
        $this->assertNotNull($submission->started_at);
        $this->assertNotNull($submission->expires_at);
        $this->assertSame(5, (int) $submission->started_at->diffInMinutes($submission->expires_at));

        foreach ($submission->proofs as $proof) {
            $service->saveProof($agent, $proof, UploadedFile::fake()->image('follow.jpg', 800, 600));
        }

        $service->submit($agent);
        $this->assertSame(AgentSocialFollowSubmission::STATUS_SUBMITTED, $submission->fresh()->status);
        $this->assertFalse($service->isCleared($agent));

        $service->approve($submission->fresh(), $admin);
        $this->assertTrue($service->isCleared($agent->fresh()));
        $this->assertSame(AgentSocialFollowSubmission::STATUS_APPROVED, $submission->fresh()->status);
        $this->assertSame($tiktok->platform, $submission->fresh()->proofs->first()->platform);
        $this->assertNotNull($submission->fresh()->proofs->first()->proof_purge_at);
    }

    public function test_follow_timer_scales_with_social_count(): void
    {
        $agent = $this->agent();
        $service = app(AgentSocialFollowService::class);

        $this->social('tiktok');
        $service->ensureSubmission($agent);
        $one = $service->start($agent);
        $this->assertSame(3, (int) $one->started_at->diffInMinutes($one->expires_at));

        $this->social('instagram');
        $this->social('youtube');
        $this->social('facebook');
        $service->ensureSubmission($agent->fresh());
        // Already timed — start() returns active run; expire then restart with new count.
        $one->forceFill([
            'status' => AgentSocialFollowSubmission::STATUS_EXPIRED,
            'started_at' => null,
            'expires_at' => null,
        ])->save();

        $four = $service->start($agent->fresh());
        $this->assertCount(4, $four->proofs);
        $this->assertSame(10, (int) $four->started_at->diffInMinutes($four->expires_at));
    }

    public function test_expired_follow_blocks_submit_until_restart(): void
    {
        Storage::fake('public');
        $this->social();
        $agent = $this->agent();
        $service = app(AgentSocialFollowService::class);
        $submission = $service->start($agent);

        foreach ($submission->proofs as $proof) {
            $service->saveProof($agent, $proof, UploadedFile::fake()->image('follow.jpg', 800, 600));
        }

        $submission->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->expectException(\InvalidArgumentException::class);
        $service->submit($agent);
    }

    public function test_no_enabled_socials_means_follow_is_not_required(): void
    {
        $agent = $this->agent();
        $this->assertTrue(app(AgentSocialFollowService::class)->isCleared($agent));
    }

    public function test_marketplace_shows_follow_as_regular_task_card(): void
    {
        $this->social();
        $agent = $this->agent();
        $creator = User::factory()->creator()->create();
        Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Hidden until follow',
            'quantity' => 1,
            'completed_count' => 0,
            'locked_creator_price' => 10,
            'locked_agent_reward' => 1,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAs($agent)
            ->get(route('agent.marketplace'))
            ->assertOk()
            ->assertSee('Priority task')
            ->assertSee('Complete these tasks to start earning real money.')
            ->assertSee('View task')
            ->assertSee('3 mins to complete')
            ->assertDontSee('Required first: Follow us')
            ->assertDontSee('Earning tasks locked')
            ->assertDontSee('Hidden until follow');
    }
}
