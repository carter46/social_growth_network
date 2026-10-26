<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_index_excludes_administrators(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now(), 'name' => 'Member Only']);
        $member->assignRole('user');

        $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Member Only')
            ->assertDontSee($admin->email);
    }

    public function test_users_table_shows_role_badges_and_join_date_under_email(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $creator = User::factory()->creator()->create(['name' => 'Casey Creator', 'email' => 'casey@example.com']);
        $agent = User::factory()->agent()->create(['name' => 'Avery Agent', 'email' => 'avery@example.com']);

        $html = $this->actingAs($admin)->get(route('admin.users'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/Casey Creator\s*<\/a>\s*<div[^>]*>\s*<span[^>]*>\s*Creator\s*<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Avery Agent\s*<\/a>\s*<div[^>]*>\s*<span[^>]*>\s*Agent\s*<\/span>/', $html);
        $this->assertMatchesRegularExpression('/casey@example\.com<\/div>\s*<div[^>]*>Joined /', $html);
        $this->assertStringNotContainsString('>Search</button>', $html);
        $this->assertStringContainsString('id="users-results"', $html);
    }

    public function test_live_filter_returns_results_fragment_with_partial_search_and_role(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        User::factory()->creator()->create(['name' => 'Jordan Creator', 'email' => 'jordan@example.com']);
        User::factory()->agent()->create(['name' => 'Jordan Agent', 'email' => 'jagent@example.com']);
        User::factory()->creator()->create(['name' => 'Morgan Other', 'email' => 'morgan@example.com']);

        $response = $this->actingAs($admin)
            ->withHeaders(['X-Users-Live' => '1'])
            ->get(route('admin.users', ['q' => 'jor', 'role' => 'agent']))
            ->assertOk();

        $response->assertSee('id="dashboard-tab-panel"', false)
            ->assertDontSee('<html', false)
            ->assertSee('Jordan Agent')
            ->assertDontSee('Jordan Creator')
            ->assertDontSee('Morgan Other')
            ->assertSee('q=jor', false);
    }

    public function test_search_suggestions_return_at_most_five_matches_with_roles(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        foreach (range(1, 7) as $i) {
            User::factory()->creator()->create(['name' => 'Taylor '.$i, 'email' => "taylor{$i}@example.com"]);
        }
        User::factory()->agent()->create(['name' => 'Taylor Agent', 'email' => 'tagent@example.com']);
        User::factory()->creator()->create(['name' => 'Someone Else', 'email' => 'else@example.com']);

        $data = $this->actingAs($admin)
            ->withHeaders(['X-Users-Suggest' => '1'])
            ->getJson(route('admin.users', ['q' => 'tay']))
            ->assertOk()
            ->json('data');

        $this->assertCount(5, $data);
        foreach ($data as $row) {
            $this->assertStringStartsWith('Taylor', $row['name']);
            $this->assertNotEmpty($row['roles']);
            $this->assertStringContainsString('/admin/users/', $row['url']);
        }

        $agents = $this->actingAs($admin)
            ->withHeaders(['X-Users-Suggest' => '1'])
            ->getJson(route('admin.users', ['q' => 'tay', 'role' => 'agent']))
            ->json('data');
        $this->assertSame(['Taylor Agent'], array_column($agents, 'name'));
        $this->assertSame(['Agent'], $agents[0]['roles']);
    }

    public function test_suspended_tab_lists_suspended_users(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $active = User::factory()->create(['email_verified_at' => now(), 'name' => 'Active Member']);
        $active->assignRole('user');
        $suspended = User::factory()->create([
            'email_verified_at' => now(),
            'name' => 'Suspended Member',
            'is_suspended' => true,
        ]);
        $suspended->assignRole('user');

        $this->actingAs($admin)
            ->get(route('admin.users', ['status' => 'suspended']))
            ->assertOk()
            ->assertSee('Suspended Member')
            ->assertDontSee('Active Member');
    }

    public function test_destroy_moves_account_to_trash_instead_of_anonymizing(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create([
            'email_verified_at' => now(),
            'is_suspended' => true,
            'email' => 'keep-history@example.com',
            'username' => 'keep_history',
            'name' => 'Keep History',
        ]);
        $member->assignRole('user');
        $memberId = $member->id;

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $member))
            ->assertRedirect(route('admin.trash'));

        $this->assertSoftDeleted('users', ['id' => $memberId]);
        $trashed = User::onlyTrashed()->find($memberId);
        $this->assertNotNull($trashed);
        $this->assertNull($trashed->anonymized_at);
        $this->assertSame('Keep History', $trashed->name);
        $this->assertSame('keep-history@example.com', $trashed->email);

        $this->actingAs($admin)
            ->get(route('admin.users', ['status' => 'suspended']))
            ->assertOk()
            ->assertDontSee('Keep History');

        $this->actingAs($admin)
            ->get(route('admin.trash'))
            ->assertOk()
            ->assertSee('Keep History')
            ->assertSee('keep-history@example.com');
    }

    public function test_trash_restore_and_admin_permanent_delete(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create([
            'email_verified_at' => now(),
            'email' => 'restore-me@example.com',
            'name' => 'Restore Me',
        ]);
        $member->assignRole('user');

        $this->actingAs($admin)->delete(route('admin.users.destroy', $member));
        $this->assertSoftDeleted('users', ['id' => $member->id]);

        $this->actingAs($admin)
            ->post(route('admin.trash.restore', $member->id))
            ->assertRedirect();

        $this->assertNull($member->fresh()->deleted_at);
        $this->assertFalse($member->fresh()->is_suspended);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $member->fresh()));
        $this->actingAs($admin)
            ->delete(route('admin.trash.destroy', $member->id))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNotNull(\App\Models\PurgedUserRecord::query()->where('original_user_id', $member->id)->first());
        $tombstone = User::withTrashed()->find($member->id);
        $this->assertNotNull($tombstone?->anonymized_at);
        $this->assertTrue($tombstone->trashed());
    }

    public function test_purge_anonymized_removes_tombstones_after_retention(): void
    {
        $member = User::factory()->create([
            'email_verified_at' => now(),
            'is_suspended' => true,
            'anonymized_at' => now()->subHours(25),
            'name' => 'Deleted User',
            'email' => 'deleted+99@invalid.local',
            'username' => 'deleted_99',
        ]);
        $member->assignRole('user');
        $id = $member->id;

        $this->artisan('users:purge-anonymized', ['--hours' => 24])
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $id]);
    }

    public function test_retire_only_cleans_leftover_data_for_anonymized_accounts(): void
    {
        $creator = User::factory()->creator()->create([
            'anonymized_at' => now(),
            'name' => 'Deleted User',
            'email' => 'deleted+71@invalid.local',
            'username' => 'deleted_71',
            'is_suspended' => true,
        ]);
        $campaign = \App\Models\Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Orphan monetization',
            'quantity' => 10,
            'completed_count' => 0,
            'locked_creator_price' => 100,
            'locked_agent_reward' => 50,
            'status' => \App\Models\Campaign::STATUS_ACTIVE,
        ]);

        $this->artisan('users:purge-anonymized', ['--retire-only' => true])
            ->assertSuccessful();

        $this->assertSame(\App\Models\Campaign::STATUS_CANCELLED, $campaign->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $creator->id]);
    }

    public function test_purge_anonymized_keeps_recent_tombstones(): void
    {
        $member = User::factory()->create([
            'email_verified_at' => now(),
            'is_suspended' => true,
            'anonymized_at' => now()->subHours(2),
            'name' => 'Deleted User',
            'email' => 'deleted+88@invalid.local',
            'username' => 'deleted_88',
        ]);
        $member->assignRole('user');

        $this->artisan('users:purge-anonymized', ['--hours' => 24])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $member->id]);
    }

    public function test_user_workspace_overview_loads(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('user');

        $this->actingAs($admin)
            ->get(route('admin.users.show', $member))
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee('Overview');
    }

    public function test_bulk_suspend_suspends_active_members(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $one = User::factory()->create(['email_verified_at' => now(), 'is_suspended' => false]);
        $one->assignRole('user');
        $two = User::factory()->create(['email_verified_at' => now(), 'is_suspended' => false]);
        $two->assignRole('agent');

        $this->actingAs($admin)
            ->post(route('admin.users.bulk-suspend'), ['ids' => [$one->id, $two->id]])
            ->assertRedirect(route('admin.users', ['status' => 'active']));

        $this->assertTrue($one->fresh()->is_suspended);
        $this->assertTrue($two->fresh()->is_suspended);
    }

    public function test_bulk_restore_restores_suspended_members(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $one = User::factory()->create(['email_verified_at' => now(), 'is_suspended' => true]);
        $one->assignRole('user');
        $two = User::factory()->create(['email_verified_at' => now(), 'is_suspended' => true]);
        $two->assignRole('user');

        $this->actingAs($admin)
            ->post(route('admin.users.bulk-restore'), ['ids' => [$one->id, $two->id]])
            ->assertRedirect(route('admin.users', ['status' => 'suspended']));

        $this->assertFalse($one->fresh()->is_suspended);
        $this->assertFalse($two->fresh()->is_suspended);
    }

    public function test_bulk_destroy_moves_selected_accounts_to_trash(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $suspended = User::factory()->create([
            'email_verified_at' => now(),
            'is_suspended' => true,
            'name' => 'Bulk Delete Me',
        ]);
        $suspended->assignRole('user');
        $active = User::factory()->create([
            'email_verified_at' => now(),
            'is_suspended' => false,
            'name' => 'Still Active',
        ]);
        $active->assignRole('user');

        $this->actingAs($admin)
            ->delete(route('admin.users.bulk-destroy'), ['ids' => [$suspended->id, $active->id]])
            ->assertRedirect(route('admin.trash'));

        $this->assertSoftDeleted('users', ['id' => $suspended->id]);
        $this->assertSoftDeleted('users', ['id' => $active->id]);
    }

    public function test_suspending_a_creator_holds_their_open_campaigns(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $creator = User::factory()->creator()->create();
        $campaign = \App\Models\Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'Open campaign',
            'quantity' => 5,
            'completed_count' => 0,
            'locked_creator_price' => 10,
            'locked_agent_reward' => 1,
            'status' => \App\Models\Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $creator))
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame(\App\Models\Campaign::STATUS_SUSPENDED, $campaign->status);
        $this->assertSame(\App\Models\Campaign::STATUS_ACTIVE, $campaign->status_before_hold);

        $this->actingAs($admin)
            ->post(route('admin.users.restore', $creator))
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame(\App\Models\Campaign::STATUS_ACTIVE, $campaign->status);
        $this->assertNull($campaign->status_before_hold);
    }
}
