<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_creator_only(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Create your account', false)
            ->assertSee('data-account-type="creator"', false)
            ->assertDontSee('name="referral_code"', false)
            ->assertDontSee('I want to join as', false)
            ->assertDontSee('Agent', false);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertTrue(User::where('email', 'test@example.com')->firstOrFail()->hasRole('user'));
    }

    public function test_register_form_ignores_an_agent_account_type(): void
    {
        $this->post('/register', [
            'account_type' => 'agent',
            'name' => 'Agent From Form',
            'username' => 'agentfromform',
            'email' => 'agentfromform@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice', absolute: false));

        $user = User::where('email', 'agentfromform@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('agent'));
    }

    public function test_soft_deleted_email_and_username_cannot_register_again(): void
    {
        $prior = User::factory()->create([
            'email' => 'trashed@example.com',
            'username' => 'trasheduser',
        ]);
        $prior->assignRole('user');
        $prior->delete();

        $this->post('/register', [
            'name' => 'Someone Else',
            'username' => 'trasheduser',
            'email' => 'fresh@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ])->assertSessionHasErrors('username');

        $this->post('/register', [
            'name' => 'Someone Else',
            'username' => 'newusername',
            'email' => 'trashed@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::withTrashed()->where('email', 'trashed@example.com')->count());
    }
}
