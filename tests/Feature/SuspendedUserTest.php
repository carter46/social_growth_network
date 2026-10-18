<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspendedUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_suspended' => true,
        ]);
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('login'))->assertSee('Your account has been suspended.');
    }

    public function test_suspended_agent_is_sent_to_the_agent_login_with_the_reason(): void
    {
        $agent = User::factory()->agent()->kycApproved()->create(['is_suspended' => true]);

        $response = $this->actingAs($agent)->get(route('agent'));

        $response->assertRedirect(route('agent-login'));
        $this->assertGuest();
        $this->get(route('agent-login'))
            ->assertOk()
            ->assertSee('Agent Login')
            ->assertSee('Your account has been suspended.');
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_suspended' => true,
            'password' => 'password',
        ]);
        $user->assignRole('user');

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_can_toggle_user_suspension(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $user = User::factory()->create(['email_verified_at' => now(), 'is_suspended' => false]);
        $user->assignRole('user');

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $user))
            ->assertRedirect();

        $this->assertTrue($user->fresh()->is_suspended);

        $this->actingAs($admin)
            ->post(route('admin.users.restore', $user))
            ->assertRedirect();

        $this->assertFalse($user->fresh()->is_suspended);
    }

    public function test_suspending_and_restoring_emails_the_person_once_each(): void
    {
        $sent = $this->captureEmails();
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $user = User::factory()->create(['email' => 'member@example.com', 'email_verified_at' => now(), 'is_suspended' => false]);
        $user->assignRole('user');

        $this->actingAs($admin)->post(route('admin.users.suspend', $user));
        $this->actingAs($admin)->post(route('admin.users.suspend', $user));
        $this->assertSame(['Your account has been suspended'], $sent['member@example.com'] ?? []);

        $this->actingAs($admin)->post(route('admin.users.restore', $user));
        $this->actingAs($admin)->post(route('admin.users.restore', $user));
        $this->assertSame(['Your account has been suspended', 'Your account is active again'], $sent['member@example.com'] ?? []);

        $this->travel(1)->days();
        $this->actingAs($admin)->post(route('admin.users.suspend', $user));
        $this->assertCount(3, $sent['member@example.com'] ?? [], 'A later suspension is emailed again.');
    }

    public function test_bulk_suspend_and_restore_email_each_person(): void
    {
        $sent = $this->captureEmails();
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $one = User::factory()->create(['email' => 'one@example.com', 'email_verified_at' => now()]);
        $two = User::factory()->create(['email' => 'two@example.com', 'email_verified_at' => now()]);
        $one->assignRole('user');
        $two->assignRole('agent');

        $this->actingAs($admin)->post(route('admin.users.bulk-suspend'), ['ids' => [$one->id, $two->id]]);
        $this->actingAs($admin)->post(route('admin.users.bulk-restore'), ['ids' => [$one->id, $two->id]]);

        foreach (['one@example.com', 'two@example.com'] as $email) {
            $this->assertSame(['Your account has been suspended', 'Your account is active again'], $sent[$email] ?? []);
        }
    }

    /** @return \ArrayObject<string, list<string>> */
    private function captureEmails(): \ArrayObject
    {
        $sent = new \ArrayObject;
        $mock = \Mockery::mock(\App\Services\Communications\Email\EmailService::class);
        $mock->shouldReceive('sendMailableHtml')->andReturnUsing(function (...$args) use ($sent) {
            $to = $args['to'] ?? $args[0] ?? '';
            $list = $sent[$to] ?? [];
            $list[] = $args['subject'] ?? $args[1] ?? '';
            $sent[$to] = $list;

            return \App\Services\Communications\Email\SendResult::ok('test', 'msg');
        });
        $this->app->instance(\App\Services\Communications\Email\EmailService::class, $mock);

        return $sent;
    }
}
