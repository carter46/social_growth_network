<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertRedirect(route('dashboard.account.profile'));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.account.profile'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.account.profile'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $user->refresh();
        $this->assertNotNull($user->anonymized_at);
        $this->assertTrue($user->is_suspended);
        $this->assertSame('Deleted User', $user->name);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_agent_profile_page_shows_and_updates_contact_address(): void
    {
        $agent = User::factory()->agent()->create([
            'username' => 'agent_profile_1',
            'email_verified_at' => now(),
            'phone' => '+15551234567',
            'country' => 'US',
            'address_line1' => '1 Main St',
            'address_city' => 'Boston',
            'address_state' => 'MA',
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($agent)
            ->get(route('agent.account.profile'))
            ->assertOk()
            ->assertSee('Contact and address', false)
            ->assertSee('1 Main St', false)
            ->assertSee('Boston', false)
            ->assertSee('5551234567', false);

        $this->actingAs($agent)
            ->patch(route('agent.account.profile.update'), [
                'name' => $agent->name,
                'username' => 'agent_profile_1',
                'email' => $agent->email,
                'phone' => '+447911123456',
                'country' => 'GB',
                'address_line1' => '10 Downing Street',
                'address_city' => 'London',
                'address_state' => 'England',
                'address_postal_code' => 'SW1A 2AA',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('agent.account.profile'));

        $agent->refresh();
        $this->assertSame('+447911123456', $agent->phone);
        $this->assertSame('GB', $agent->country);
        $this->assertSame('10 Downing Street', $agent->address_line1);
        $this->assertSame('London', $agent->address_city);
        $this->assertNotNull($agent->profile_completed_at);
    }
}
