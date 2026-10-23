<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PoorMansCronMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_hit_runs_schedule_once_per_minute(): void
    {
        Cache::flush();

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        Artisan::shouldReceive('call')->once()->with('schedule:run')->andReturn(0);

        $this->actingAs($admin)->get(route('admin'))->assertOk();
    }

    public function test_non_admin_does_not_trigger_schedule(): void
    {
        Cache::flush();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        Artisan::shouldReceive('call')->never();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
