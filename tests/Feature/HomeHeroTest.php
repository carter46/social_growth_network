<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_hero_uses_three_fading_background_images(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('assets/images/homeslider1.jpg', false)
            ->assertSee('assets/images/homeslider2.jpg', false)
            ->assertSee('assets/images/homeslider3.jpg', false)
            ->assertSee('transition-opacity duration-1000', false)
            ->assertSee(route('services'), false);
    }

    public function test_home_ecosystem_uses_catalog_service_names_not_hardcoded_categories(): void
    {
        \Illuminate\Support\Facades\Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        \Illuminate\Support\Facades\Artisan::call('catalog:backfill-hierarchy');

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertDontSee('Crypto Cash Exchange', false)
            ->assertSee('Social media services', false);
    }

    public function test_home_ecosystem_catalog_services_follow_admin_sort_order(): void
    {
        \Illuminate\Support\Facades\Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        \Illuminate\Support\Facades\Artisan::call('catalog:backfill-hierarchy');

        $vpn = \App\Models\ProductType::query()->where('slug', 'vpn')->firstOrFail();
        $email = \App\Models\ProductType::query()->where('slug', 'email')->firstOrFail();

        $vpn->forceFill(['sort_order' => 20])->save();
        $email->forceFill(['sort_order' => 2])->save();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Email Services'),
            strpos($html, 'VPN')
        );
        $this->assertStringNotContainsString('Crypto Cash Exchange', $html);
    }
}
