<?php

namespace Tests\Feature\Auth;

use App\Models\IntegrationProvider;
use App\Models\User;
use App\Models\UserAuthProvider;
use App\Services\Auth\Identity\GoogleIdentityProvider;
use App\Services\Auth\Identity\VerifiedIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleIdentityAuthTest extends TestCase
{
    use RefreshDatabase;

    private function enableGoogleIdentity(): void
    {
        $row = IntegrationProvider::forProvider(IntegrationProvider::GOOGLE_IDENTITY);
        $row->enabled = true;
        $row->mergeCredentials(['client_id' => 'test-client-id.apps.googleusercontent.com']);
        $row->meta = array_merge($row->meta ?? [], [
            'one_tap_enabled' => true,
            'one_tap_show_home' => true,
            'one_tap_show_login' => true,
            'one_tap_show_register' => false,
            'one_tap_disable_after_dismiss' => true,
            'one_tap_prompt_cooldown_hours' => 24,
        ]);
        $row->status = 'connected';
        $row->save();
    }

    private function mockGoogleIdentity(VerifiedIdentity $identity): void
    {
        $mock = Mockery::mock(GoogleIdentityProvider::class);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('name')->andReturn(UserAuthProvider::GOOGLE);
        $mock->shouldReceive('verifyCredential')
            ->with('valid-credential')
            ->andReturn($identity);

        $this->app->instance(GoogleIdentityProvider::class, $mock);
        $this->app->forgetInstance(\App\Services\Auth\Identity\SocialAuthService::class);
    }

    public function test_new_google_user_is_created_verified_and_logged_in(): void
    {
        $this->enableGoogleIdentity();
        $this->mockGoogleIdentity(new VerifiedIdentity(
            provider: UserAuthProvider::GOOGLE,
            providerUserId: 'google-sub-1',
            email: 'new.google@example.com',
            emailVerified: true,
            name: 'Google User',
            avatarUrl: 'https://example.com/a.png',
        ));

        $response = $this->postJson(route('auth.google'), [
            'credential' => 'valid-credential',
        ]);

        $response->assertOk()->assertJsonStructure(['redirect']);
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'new.google@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password_set_at);
        $this->assertTrue($user->hasRole('user'));
        $this->assertDatabaseHas('user_auth_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-1',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.google.signup']);
    }

    public function test_existing_email_is_linked_without_duplicate(): void
    {
        $this->enableGoogleIdentity();
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'email_verified_at' => null,
        ]);
        $user->assignRole('user');

        $this->mockGoogleIdentity(new VerifiedIdentity(
            provider: UserAuthProvider::GOOGLE,
            providerUserId: 'google-sub-2',
            email: 'existing@example.com',
            emailVerified: true,
            name: 'Existing',
        ));

        $this->postJson(route('auth.google'), ['credential' => 'valid-credential'])
            ->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->where('email', 'existing@example.com')->count());
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('user_auth_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-2',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.google.linked']);
    }

    public function test_invalid_token_fails(): void
    {
        $this->enableGoogleIdentity();

        $mock = Mockery::mock(GoogleIdentityProvider::class);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('verifyCredential')
            ->andThrow(new \InvalidArgumentException('Invalid Google ID token.'));
        $this->app->instance(GoogleIdentityProvider::class, $mock);
        $this->app->forgetInstance(\App\Services\Auth\Identity\SocialAuthService::class);

        $this->postJson(route('auth.google'), ['credential' => 'bad'])
            ->assertStatus(422);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.google.login_failed']);
    }

    public function test_trashed_email_cannot_sign_up_again_via_google(): void
    {
        $this->enableGoogleIdentity();
        $prior = User::factory()->create(['email' => 'trashed-google@example.com']);
        $prior->assignRole('user');
        $prior->delete();

        $this->mockGoogleIdentity(new VerifiedIdentity(
            provider: UserAuthProvider::GOOGLE,
            providerUserId: 'google-sub-trashed',
            email: 'trashed-google@example.com',
            emailVerified: true,
            name: 'Trashed',
        ));

        $this->postJson(route('auth.google'), ['credential' => 'valid-credential', 'account_type' => 'user'])
            ->assertStatus(422);

        $this->assertGuest();
        $this->assertSame(1, User::withTrashed()->where('email', 'trashed-google@example.com')->count());
        $this->assertFalse(User::query()->where('email', 'trashed-google@example.com')->exists());
    }

    public function test_cannot_unlink_google_without_password(): void
    {
        $user = User::factory()->withoutPasswordSet()->create();
        $user->assignRole('user');
        UserAuthProvider::query()->create([
            'user_id' => $user->id,
            'provider' => UserAuthProvider::GOOGLE,
            'provider_user_id' => 'google-sub-3',
            'provider_email' => $user->email,
        ]);

        $this->actingAs($user)
            ->delete(route('auth.google.unlink'))
            ->assertSessionHasErrors('google');

        $this->assertDatabaseHas('user_auth_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }

    public function test_can_unlink_google_after_setting_password(): void
    {
        $user = User::factory()->withoutPasswordSet()->create();
        $user->assignRole('user');
        UserAuthProvider::query()->create([
            'user_id' => $user->id,
            'provider' => UserAuthProvider::GOOGLE,
            'provider_user_id' => 'google-sub-4',
            'provider_email' => $user->email,
        ]);

        $this->actingAs($user)
            ->put(route('password.update'), [
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->password_set_at);

        $this->actingAs($user)
            ->delete(route('auth.google.unlink'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('user_auth_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.google.unlinked']);
    }

    public function test_login_page_hides_google_when_disabled(): void
    {
        $row = IntegrationProvider::forProvider(IntegrationProvider::GOOGLE_IDENTITY);
        $row->enabled = false;
        $row->save();

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-google-identity', false);
    }

    public function test_login_page_shows_google_when_enabled(): void
    {
        $this->enableGoogleIdentity();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-google-identity', false);
    }

    public function test_admin_can_save_google_identity_with_client_id_only(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('system.manage');

        $this->actingAs($admin)
            ->post(route('admin.settings.google-identity'), [
                'google_identity_enabled' => '1',
                'google_identity_client_id' => 'abc.apps.googleusercontent.com',
                'google_identity_client_secret' => '',
                'google_identity_one_tap_enabled' => '1',
                'google_identity_auto_select_enabled' => '0',
                'google_identity_one_tap_show_home' => '1',
                'google_identity_one_tap_show_login' => '0',
                'google_identity_one_tap_show_register' => '0',
                'google_identity_one_tap_disable_after_dismiss' => '1',
                'google_identity_one_tap_prompt_cooldown_hours' => '48',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = IntegrationProvider::forProvider(IntegrationProvider::GOOGLE_IDENTITY);
        $this->assertTrue($row->enabled);
        $this->assertSame('abc.apps.googleusercontent.com', $row->credential('client_id'));
        $this->assertTrue((bool) ($row->meta['one_tap_enabled'] ?? false));
        $this->assertSame(48, (int) ($row->meta['one_tap_prompt_cooldown_hours'] ?? 0));
        $this->assertTrue(app(GoogleIdentityProvider::class)->isAvailable());
    }

    public function test_suspended_user_cannot_login_with_google(): void
    {
        $this->enableGoogleIdentity();
        $user = User::factory()->suspended()->create([
            'email' => 'suspended@example.com',
        ]);
        $user->assignRole('user');
        UserAuthProvider::query()->create([
            'user_id' => $user->id,
            'provider' => UserAuthProvider::GOOGLE,
            'provider_user_id' => 'google-sub-suspended',
            'provider_email' => $user->email,
        ]);

        $this->mockGoogleIdentity(new VerifiedIdentity(
            provider: UserAuthProvider::GOOGLE,
            providerUserId: 'google-sub-suspended',
            email: 'suspended@example.com',
            emailVerified: true,
            name: 'Suspended',
        ));

        $this->postJson(route('auth.google'), ['credential' => 'valid-credential'])
            ->assertStatus(422);

        $this->assertGuest();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.google.login']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.google.login_failed']);
    }

    public function test_link_rejects_different_google_account(): void
    {
        $this->enableGoogleIdentity();
        $user = User::factory()->create();
        $user->assignRole('user');
        UserAuthProvider::query()->create([
            'user_id' => $user->id,
            'provider' => UserAuthProvider::GOOGLE,
            'provider_user_id' => 'google-sub-original',
            'provider_email' => $user->email,
        ]);

        $this->mockGoogleIdentity(new VerifiedIdentity(
            provider: UserAuthProvider::GOOGLE,
            providerUserId: 'google-sub-other',
            email: $user->email,
            emailVerified: true,
            name: $user->name,
        ));

        $this->actingAs($user)
            ->postJson(route('auth.google.link'), ['credential' => 'valid-credential'])
            ->assertStatus(422);

        $this->assertDatabaseHas('user_auth_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-original',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.google.link_failed']);
    }

    public function test_anonymize_removes_google_links(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        UserAuthProvider::query()->create([
            'user_id' => $user->id,
            'provider' => UserAuthProvider::GOOGLE,
            'provider_user_id' => 'google-sub-anon',
            'provider_email' => $user->email,
        ]);

        $this->assertTrue($user->anonymize());

        $this->assertDatabaseMissing('user_auth_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }

    public function test_admin_link_redirects_to_admin_security(): void
    {
        $this->enableGoogleIdentity();
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $this->mockGoogleIdentity(new VerifiedIdentity(
            provider: UserAuthProvider::GOOGLE,
            providerUserId: 'google-sub-admin',
            email: $admin->email,
            emailVerified: true,
            name: $admin->name,
        ));

        $this->actingAs($admin)
            ->postJson(route('auth.google.link'), ['credential' => 'valid-credential'])
            ->assertOk()
            ->assertJsonPath('redirect', route('admin.account.security'));
    }

    public function test_one_tap_show_home_defaults_true_when_meta_missing(): void
    {
        $row = IntegrationProvider::forProvider(IntegrationProvider::GOOGLE_IDENTITY);
        $row->enabled = true;
        $row->mergeCredentials(['client_id' => 'test-client-id.apps.googleusercontent.com']);
        $row->meta = null;
        $row->save();

        $config = GoogleIdentityProvider::configForFrontend();
        $this->assertTrue($config['one_tap_show_home']);
    }
}
