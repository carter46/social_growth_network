<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\KycSubmission;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_uses_grouped_persisted_navigation(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin'))
            ->assertOk()
            ->assertSee('data-dashboard-nav="admin"', false)
            ->assertSee('Campaigns')
            ->assertSee('Task verification')
            ->assertSee('Operations')
            ->assertSee('Overview')
            ->assertSee('Media Library')
            ->assertSee('Platform Catalog')
            ->assertSee('Categories')
            ->assertSee('Products')
            ->assertSee('Administrators')
            ->assertSee('Finance')
            ->assertSee('System')
            ->assertSee('Trash')
            ->assertSee('Support')
            ->assertDontSee(route('admin.services'), false)
            ->assertDontSee('>Commerce<', false)
            ->assertDontSee('Platform Services')
            ->assertDontSee('Identity & Users')
            ->assertDontSee('Service Categories', false)
            ->assertDontSee('data-dashboard-nav-search', false)
            ->assertSee("7th.dashboard.nav.admin.{$admin->id}", false)
            ->assertSee('scrollbar-hide', false)
            ->assertSee('aria-expanded=', false)
            ->assertSee('data-mobile-theme-switcher', false)
            ->assertSee('data-desktop-theme-switcher', false);
    }

    public function test_user_sidebar_uses_grouped_persisted_navigation(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-dashboard-nav="user"', false)
            ->assertSee('Wallet')
            ->assertSee('Campaign packages')
            ->assertSee('My Campaigns')
            ->assertDontSee('data-dashboard-nav-search', false)
            ->assertSee("7th.dashboard.nav.user.{$user->id}", false)
            ->assertSee('scrollbar-hide', false)
            ->assertSee('data-mobile-theme-switcher', false);
    }

    public function test_admin_child_page_does_not_mark_overview_active(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.users'))->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('admin.users'), '/').'"\\s+class="[^"]*sidebar-link-active/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/href="'.preg_quote(route('admin'), '/').'"\\s+class="[^"]*sidebar-link-active/',
            $html,
        );
    }

    public function test_user_child_page_does_not_mark_dashboard_active(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('dashboard.wallet'))->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('dashboard.wallet'), '/').'"\\s+class="[^"]*sidebar-link-active/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/href="'.preg_quote(route('dashboard'), '/').'"\\s+class="[^"]*sidebar-link-active/',
            $html,
        );
    }

    public function test_admin_sidebar_shows_waiting_counts_on_the_group_and_each_queue(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $creator = User::factory()->creator()->create();
        $agent = User::factory()->agent()->create();

        $campaign = Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Waiting task',
            'quantity' => 1,
            'locked_creator_price' => 10,
            'locked_agent_reward' => 5,
            'status' => Campaign::STATUS_ACTIVE,
        ]);
        CampaignParticipation::query()->create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'status' => CampaignParticipation::STATUS_UNDER_REVIEW,
            'reward_amount' => 5,
        ]);
        Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Needs review',
            'quantity' => 1,
            'locked_creator_price' => 10,
            'locked_agent_reward' => 5,
            'status' => Campaign::STATUS_PENDING_REVIEW,
        ]);
        KycSubmission::query()->create([
            'user_id' => $agent->id,
            'level_requested' => 1,
            'status' => 'pending',
        ]);
        SupportTicket::factory()->create(['user_id' => $agent->id, 'status' => 'open']);

        $html = $this->actingAs($admin)->get(route('admin'))->assertOk()->getContent();

        foreach ([
            'operations' => '4',
            'admin.verifications' => '1',
            'admin.kyc' => '1',
            'admin.tickets' => '1',
            'admin.campaigns' => '1',
        ] as $key => $count) {
            $this->assertMatchesRegularExpression(
                '/data-nav-count="'.preg_quote($key, '/').'"[^>]*>\s*'.$count.'\s*</',
                $html,
            );
        }
    }
}
