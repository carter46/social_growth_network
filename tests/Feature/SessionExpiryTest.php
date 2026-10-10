<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/_test/expired-session', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });
    }

    public function test_guest_with_expired_session_is_sent_to_login_and_back_afterwards(): void
    {
        $from = url('/dashboard/admin/settings');

        $this->from($from)->post('/_test/expired-session')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your session expired. Please log in again.')
            ->assertSessionHas('url.intended', $from);
    }

    public function test_json_request_with_expired_session_gets_login_redirect_hint(): void
    {
        $this->postJson('/_test/expired-session')
            ->assertStatus(419)
            ->assertJson([
                'message' => 'Your session expired. Please log in again.',
                'redirect' => route('login'),
            ]);
    }

    public function test_signed_in_user_with_stale_token_goes_back_with_message(): void
    {
        $user = User::factory()->create();
        $from = url('/dashboard');

        $this->actingAs($user)->from($from)->post('/_test/expired-session', ['name' => 'Kept'])
            ->assertRedirect($from)
            ->assertSessionHas('error', 'Your session was refreshed. Please try again.')
            ->assertSessionHasInput('name', 'Kept');
    }
}
