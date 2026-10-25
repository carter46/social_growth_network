<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\CampaignParticipationTask;
use App\Models\User;
use App\Services\Campaigns\CampaignParticipationService;
use App\Services\Media\TaskProofRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskProofRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_proof_urls_are_detected_and_external_urls_are_ignored(): void
    {
        $retention = app(TaskProofRetentionService::class);

        $this->assertTrue($retention->isLocalProofUrl('/storage/task-proofs/2026/10/shot.jpg'));
        $this->assertTrue($retention->isLocalProofUrl('https://example.test/storage/social-follow-proofs/2026/10/a.webp'));
        $this->assertFalse($retention->isLocalProofUrl('https://www.youtube.com/watch?v=abc&lc=Ug'));
        $this->assertSame(
            'task-proofs/2026/10/shot.jpg',
            $retention->relativePathFromUrl('https://example.test/storage/task-proofs/2026/10/shot.jpg')
        );
    }

    public function test_approve_schedules_purge_and_command_deletes_due_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('task-proofs/2026/10/proof.jpg', 'fake-image');

        $creator = User::factory()->creator()->create();
        $agent = User::factory()->agent()->kycApproved()->create();
        \App\Models\Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 0, 'locked_balance' => 0]);

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Proof purge campaign',
            'quantity' => 1,
            'completed_count' => 0,
            'locked_creator_price' => 10,
            'locked_agent_reward' => 1,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $participation = CampaignParticipation::query()->create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'status' => CampaignParticipation::STATUS_SUBMITTED,
            'proof_url' => '/storage/task-proofs/2026/10/proof.jpg',
            'reward_amount' => 1,
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(10),
        ]);

        CampaignParticipationTask::query()->create([
            'participation_id' => $participation->id,
            'campaign_id' => $campaign->id,
            'task' => 'subscribe',
            'position' => 1,
            'status' => CampaignParticipationTask::STATUS_ACCEPTED,
            'proof_url' => '/storage/task-proofs/2026/10/proof.jpg',
            'accepted_at' => now(),
        ]);

        // Non-step campaigns: approve path does not require steps. Clear steps for simple path.
        CampaignParticipationTask::query()->where('participation_id', $participation->id)->delete();

        $admin = User::factory()->admin()->create();
        app(CampaignParticipationService::class)->approve($participation->fresh(), $admin);

        $participation->refresh();
        $this->assertSame(CampaignParticipation::STATUS_PAID, $participation->status);
        $this->assertNotNull($participation->proof_purge_at);
        $this->assertTrue($participation->proof_purge_at->greaterThan(now()->addDays(6)));

        $participation->forceFill(['proof_purge_at' => now()->subMinute()])->save();

        $this->artisan('proofs:purge-expired')->assertSuccessful();

        Storage::disk('public')->assertMissing('task-proofs/2026/10/proof.jpg');
        $this->assertNull($participation->fresh()->proof_url);
        $this->assertNull($participation->fresh()->proof_purge_at);
    }
}
