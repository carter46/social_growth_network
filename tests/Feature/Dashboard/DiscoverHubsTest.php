<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoverHubsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_services_hub(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('dashboard.services'))
            ->assertOk()
            ->assertSee('Services');
    }

    public function test_legacy_discover_and_marketplace_urls_redirect_to_services(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->get('/dashboard/discover/marketplace')
            ->assertRedirect('/dashboard/services');

        $this->actingAs($user)
            ->get('/dashboard/marketplace')
            ->assertRedirect('/dashboard/services');

        $this->actingAs($user)
            ->get('/dashboard/discover/services')
            ->assertRedirect('/dashboard/services');
    }
}
