<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_shows_account_type_choices(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Creator', false);
        $response->assertSee('Agent', false);
        $response->assertSee('Complete paid tasks', false);
        $response->assertSee('Buy campaign packages', false);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'account_type' => 'creator',
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_new_agents_can_register_from_unified_form(): void
    {
        $response = $this->post('/register', [
            'account_type' => 'agent',
            'name' => 'Agent From Form',
            'username' => 'agentfromform',
            'email' => 'agentfromform@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertTrue(\App\Models\User::where('email', 'agentfromform@example.com')->firstOrFail()->hasRole('agent'));
    }

    public function test_registration_requires_account_type(): void
    {
        $this->post('/register', [
            'name' => 'No Type',
            'username' => 'notypeuser',
            'email' => 'notype@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ])->assertSessionHasErrors('account_type');
    }
}
