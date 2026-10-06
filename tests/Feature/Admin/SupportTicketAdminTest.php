<?php

namespace Tests\Feature\Admin;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Services\Communications\Email\EmailService;
use App\Services\Communications\Email\SendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SupportTicketAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reply_and_close_ticket(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'category' => 'wallet',
            'subject' => 'Balance issue',
            'body' => 'My balance looks wrong.',
            'status' => 'open',
        ]);

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Balance issue');

        $this->actingAs($admin)
            ->post(route('admin.tickets.reply', $ticket), ['body' => 'We are reviewing your wallet.'])
            ->assertRedirect();

        $reply = SupportTicketReply::where('support_ticket_id', $ticket->id)->first();
        $this->assertNotNull($reply);
        $this->assertTrue($reply->is_staff);

        $this->actingAs($admin)
            ->post(route('admin.tickets.status', $ticket), ['status' => 'closed'])
            ->assertRedirect();

        $this->assertSame('closed', $ticket->fresh()->status);
    }

    public function test_only_first_staff_reply_is_emailed_and_reminder_follows_after_24h(): void
    {
        [$user, $admin, $ticket] = $this->ticketWithUserAndAdmin();

        $sent = [];
        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendMailableHtml')->andReturnUsing(function (...$args) use (&$sent, $user) {
            if (($args['to'] ?? $args[0] ?? null) === $user->email) {
                $sent[] = $args['subject'] ?? $args[1] ?? '';
            }

            return SendResult::ok('test', 'msg-'.count($sent));
        });
        $this->app->instance(EmailService::class, $mock);

        $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['body' => 'First reply']);
        $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['body' => 'Second reply']);

        $this->assertCount(1, $sent);
        $this->assertSame(2, $user->fresh()->unreadNotificationsCount());

        $this->artisan('support:remind-unanswered')->assertSuccessful();
        $this->assertCount(1, $sent);

        $this->travel(25)->hours();
        $this->artisan('support:remind-unanswered')->assertSuccessful();
        $this->assertCount(2, $sent);

        $this->artisan('support:remind-unanswered')->assertSuccessful();
        $this->assertCount(2, $sent);
    }

    public function test_reminder_skipped_when_user_replied(): void
    {
        [$user, $admin, $ticket] = $this->ticketWithUserAndAdmin();

        $sent = 0;
        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendMailableHtml')->andReturnUsing(function (...$args) use (&$sent, $user) {
            if (($args['to'] ?? $args[0] ?? null) === $user->email) {
                $sent++;
            }

            return SendResult::ok('test', 'msg-'.$sent);
        });
        $this->app->instance(EmailService::class, $mock);

        $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['body' => 'First reply']);
        $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['body' => 'Second reply']);
        $this->actingAs($user)->post(route('dashboard.support.reply', $ticket), ['body' => 'Thanks'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $ticket->fresh()->latestReply->is_staff);

        $this->travel(25)->hours();
        $this->artisan('support:remind-unanswered')->assertSuccessful();

        $this->assertSame(1, $sent);
    }

    public function test_user_ticket_list_counts_unread_staff_replies(): void
    {
        [$user, $admin, $ticket] = $this->ticketWithUserAndAdmin();

        SupportTicketReply::create(['support_ticket_id' => $ticket->id, 'user_id' => $admin->id, 'body' => 'A', 'is_staff' => true]);
        SupportTicketReply::create(['support_ticket_id' => $ticket->id, 'user_id' => $admin->id, 'body' => 'B', 'is_staff' => true]);
        SupportTicketReply::create(['support_ticket_id' => $ticket->id, 'user_id' => $user->id, 'body' => 'C', 'is_staff' => false]);

        $this->assertSame(2, (int) SupportTicket::query()->withUnreadStaffReplies()->find($ticket->id)->unread_staff_replies_count);

        $this->actingAs($user)->get(route('dashboard.support.index'))->assertOk()->assertSee('Balance issue');
        $this->actingAs($user)->get(route('dashboard.support.show', $ticket))->assertOk()->assertSee('B');

        $this->travel(1)->seconds();
        $this->assertSame(0, (int) SupportTicket::query()->withUnreadStaffReplies()->find($ticket->id)->unread_staff_replies_count);
    }

    public function test_user_reply_reopens_awaiting_ticket_and_viewing_clears_ticket_notifications(): void
    {
        [$user, $admin, $ticket] = $this->ticketWithUserAndAdmin();

        $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['body' => 'Please send a screenshot']);
        $this->assertSame('awaiting_user', $ticket->fresh()->status);
        $this->assertSame(1, $user->fresh()->unreadNotificationsCount());

        $this->actingAs($user)->get(route('dashboard.support.show', $ticket))->assertOk();
        $this->assertSame(0, $user->fresh()->unreadNotificationsCount());

        $this->actingAs($user)->post(route('dashboard.support.reply', $ticket), ['body' => 'Here it is']);
        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_admin_ticket_list_shows_username_and_searches_by_it(): void
    {
        [$user, $admin, $ticket] = $this->ticketWithUserAndAdmin();
        $user->forceFill(['username' => 'balancefan'])->save();

        $this->actingAs($admin)->get(route('admin.tickets'))
            ->assertOk()
            ->assertSee('@balancefan')
            ->assertDontSee($user->email);

        $this->actingAs($admin)->get(route('admin.tickets', ['q' => '@balancefan']))
            ->assertOk()
            ->assertSee('Balance issue');
    }

    /** @return array{0: User, 1: User, 2: SupportTicket} */
    private function ticketWithUserAndAdmin(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'category' => 'wallet',
            'subject' => 'Balance issue',
            'body' => 'My balance looks wrong.',
            'status' => 'open',
        ]);

        return [$user, $admin, $ticket];
    }
}
