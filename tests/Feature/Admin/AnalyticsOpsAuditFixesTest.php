<?php

namespace Tests\Feature\Admin;

use App\Events\TicketOpened;
use App\Models\AdminNotification;
use App\Models\AnalyticsKpiSnapshot;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AnalyticsOpsAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_kyc_tab_partial_uses_dashboard_tab_header(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->withHeaders(['X-Dashboard-Tab' => '1'])
            ->get(route('admin.kyc', ['status' => 'pending']))
            ->assertOk()
            ->assertDontSee('<html', false);
    }

    public function test_notification_dispatcher_writes_user_and_admin_database_rows(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        /** @var NotificationDispatcher $dispatcher */
        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->notifyUser(
            $user,
            new NotificationMessage(
                type: 'test.user',
                title: 'Hello user',
                body: 'Body',
            ),
            ['database']
        );

        $dispatcher->notifyAdmins(
            new NotificationMessage(
                type: 'test.admin',
                title: 'Hello admin',
                body: 'Body',
                permission: 'support.manage',
            ),
            ['database']
        );

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'test.user',
            'title' => 'Hello user',
        ]);
        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'test.admin',
            'title' => 'Hello admin',
        ]);
        $this->assertInstanceOf(UserNotification::class, UserNotification::first());
        $this->assertInstanceOf(AdminNotification::class, AdminNotification::first());
    }

    public function test_ticket_opened_dispatches_domain_event(): void
    {
        Event::fake([TicketOpened::class]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('dashboard.support.store'), [
                'category' => 'wallet',
                'subject' => 'Need help',
                'body' => 'Please assist.',
            ])
            ->assertRedirect();

        Event::assertDispatched(TicketOpened::class);
        $this->assertDatabaseHas('support_tickets', ['subject' => 'Need help']);
    }

    public function test_overview_uses_snapshots_for_support_waiting(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        AnalyticsKpiSnapshot::create([
            'kpi_key' => 'revenue.today',
            'period' => 'today',
            'value' => 100,
            'captured_at' => now(),
        ]);
        AnalyticsKpiSnapshot::create([
            'kpi_key' => 'fundings.today',
            'period' => 'today',
            'value' => 2,
            'captured_at' => now(),
        ]);
        AnalyticsKpiSnapshot::create([
            'kpi_key' => 'kyc.pending',
            'period' => 'current',
            'value' => 1,
            'captured_at' => now(),
        ]);
        AnalyticsKpiSnapshot::create([
            'kpi_key' => 'support.waiting',
            'period' => 'current',
            'value' => 3,
            'captured_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin'))
            ->assertOk()
            ->assertViewHas('supportWaiting', 3);
    }

    public function test_analytics_section_acl_blocks_finance_without_permission(): void
    {
        $adminRole = \Spatie\Permission\Models\Role::findByName('admin');
        $adminRole->revokePermissionTo('finance.manage');

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.analytics', ['section' => 'revenue']))
            ->assertForbidden();
    }

    public function test_admin_search_returns_groups(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $member = User::factory()->create([
            'email_verified_at' => now(),
            'name' => 'Searchable Member',
            'email' => 'searchable@example.com',
        ]);
        $member->assignRole('user');

        $this->actingAs($admin)
            ->getJson(route('admin.search', ['q' => 'Searchable']))
            ->assertOk()
            ->assertJsonFragment(['label' => 'Searchable Member']);
    }

    public function test_monitoring_page_loads_for_system_manage(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.monitoring'))
            ->assertOk()
            ->assertSee('N/A');
    }

    public function test_analytics_connection_test_does_not_persist_credentials(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $provider = \App\Models\AnalyticsProvider::forProvider(\App\Models\AnalyticsProvider::PROVIDER_GOOGLE_ANALYTICS);
        $before = $provider->credentials;

        $this->actingAs($admin)
            ->post(route('admin.settings.analytics.test'), [
                'provider' => 'google_analytics',
                'google_measurement_id' => 'G-TESTONLY123',
            ])
            ->assertRedirect();

        $provider->refresh();
        $this->assertSame($before, $provider->credentials);
    }
}
