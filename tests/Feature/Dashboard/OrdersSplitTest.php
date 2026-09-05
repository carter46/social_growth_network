<?php

namespace Tests\Feature\Dashboard;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_orders_show_platform_purchases_only(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        Order::factory()->platform()->create([
            'user_id' => $user->id,
            'reference' => 'SVC-ORDER-1',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.service-orders'))
            ->assertOk()
            ->assertSee('My Orders')
            ->assertSee('SVC-ORDER-1');

        $emptyUser = User::factory()->create(['email_verified_at' => now()]);
        $emptyUser->assignRole('user');

        $this->actingAs($emptyUser)
            ->get(route('dashboard.service-orders'))
            ->assertOk()
            ->assertSee('Browse services')
            ->assertSee(url('/dashboard/services'), false);
    }

    public function test_legacy_marketplace_orders_route_redirects_to_service_orders(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->get('/dashboard/orders')
            ->assertRedirect('/dashboard/service-orders');
    }
}
