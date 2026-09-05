<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_another_users_support_ticket(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        $ticket = SupportTicket::create([
            'user_id' => $owner->id,
            'category' => 'wallet',
            'subject' => 'Test',
            'body' => 'Help',
            'status' => 'open',
        ]);

        $this->actingAs($other)
            ->get(route('dashboard.support.show', $ticket))
            ->assertForbidden();
    }
}
