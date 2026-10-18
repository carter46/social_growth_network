<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200)
            ->assertSee($user->email)
            ->assertSee('Spam or Junk folder');
    }

    public function test_an_earlier_unexpired_code_still_verifies_after_a_resend(): void
    {
        $user = User::factory()->unverified()->create();

        foreach (['111111' => 2, '222222' => 0] as $code => $minutesAgo) {
            DB::table('email_verification_codes')->insert([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(15),
                'attempts' => 0,
                'created_at' => now()->subMinutes($minutesAgo),
                'updated_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        $this->actingAs($user)->post('/verify-email', ['otp' => '111 111']);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_wrong_code_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        DB::table('email_verification_codes')->insert([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->post('/verify-email', ['otp' => '654321'])->assertSessionHasErrors();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_can_be_verified_with_otp(): void
    {
        $user = User::factory()->unverified()->create();
        $code = '123456';

        DB::table('email_verification_codes')->insert([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/verify-email', ['otp' => $code]);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
