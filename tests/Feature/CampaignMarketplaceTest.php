<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Campaigns\CampaignParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_creator_cannot_open_marketplace_by_url(): void
    {
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)->get(route('agent.marketplace'))->assertForbidden();
    }
}
