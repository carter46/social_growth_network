<?php

namespace Tests\Feature;

use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\User;
use App\Modules\Catalog\Http\Controllers\PackageConfiguratorController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GuestPackageJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO_URL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('escrows')) {
            Schema::create('escrows', fn (Blueprint $table) => $table->id());
        }

        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');
    }

    private function product(string $slug = 'youtube-likes', array $attributes = []): PlatformProduct
    {
        $product = PlatformProduct::query()->where('slug', $slug)->firstOrFail();
        $product->forceFill(array_merge([
            'agent_reward_percent' => 40,
            'estimated_minutes' => 2,
            'completion_minutes' => 30,
        ], $attributes))->save();
        $product->pricingVariant()->forceFill([
            'pricing_mode' => PlatformProductVariant::PRICING_PER_UNIT,
            'unit_price' => 5,
            'min_units' => 100,
            'max_units' => 1000,
            'is_active' => true,
        ])->save();

        return $product->fresh();
    }

    public function test_guest_package_is_saved_and_resumed_at_checkout_after_login(): void
    {
        $product = $this->product();
        $resume = route('dashboard.services.resume', $product->slug);

        $this->post(route('services.configure', $product->slug), [
            'units' => '250',
            'target_url' => self::VIDEO_URL,
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $resume)
            ->assertSessionHas('purchase_draft');

        $creator = User::factory()->creator()->create();

        $response = $this->actingAs($creator)->get($resume);
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(route('dashboard.services.checkout', $product->slug), $location);
        $this->assertStringContainsString('units=250', $location);
        $this->assertStringContainsString(urlencode(self::VIDEO_URL), $location);
    }

    public function test_logged_in_creator_goes_straight_to_resume(): void
    {
        $product = $this->product();
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)
            ->post(route('services.configure', $product->slug), [
                'units' => '100',
                'target_url' => self::VIDEO_URL,
            ])
            ->assertRedirect(route('dashboard.services.resume', $product->slug));
    }

    public function test_agents_get_a_friendly_creator_only_message(): void
    {
        $product = $this->product();
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)
            ->from(route('services'))
            ->post(route('services.configure', $product->slug), [
                'units' => '100',
                'target_url' => self::VIDEO_URL,
            ])
            ->assertRedirect(route('services'))
            ->assertSessionHas('error', PackageConfiguratorController::CREATOR_ONLY_MESSAGE)
            ->assertSessionMissing('purchase_draft');
    }

    public function test_units_out_of_range_keep_the_input_and_save_nothing(): void
    {
        $product = $this->product();

        $this->from(route('services'))
            ->post(route('services.configure', $product->slug), [
                'units' => '5',
                'target_url' => self::VIDEO_URL,
            ])
            ->assertRedirect(route('services'))
            ->assertSessionHasErrors('units')
            ->assertSessionHasInput('units', '5')
            ->assertSessionMissing('purchase_draft');
    }

    public function test_missing_link_is_refused_before_login(): void
    {
        $product = $this->product();

        $this->from(route('services'))
            ->post(route('services.configure', $product->slug), ['units' => '100'])
            ->assertSessionHasErrors('target_url')
            ->assertSessionMissing('purchase_draft');
    }

    public function test_resume_without_a_draft_sends_the_creator_back_to_the_product(): void
    {
        $product = $this->product();

        $this->actingAs(User::factory()->creator()->create())
            ->get(route('dashboard.services.resume', $product->slug))
            ->assertRedirect(route('dashboard.services.product', $product->slug))
            ->assertSessionHas('error');
    }

    public function test_companion_quantities_travel_through_the_draft(): void
    {
        $product = $this->product('youtube-subscribers-watch-comments', [
            'status' => \App\Enums\PlatformProductStatus::Published,
            'task_limits' => [
                'watch' => ['min' => 50, 'max' => 500],
                'comment' => ['min' => 0, 'max' => 200],
            ],
        ]);
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)
            ->post(route('services.configure', $product->slug), [
                'units' => '120',
                'qty' => ['watch' => '80', 'comment' => '10'],
                'target_url' => self::VIDEO_URL,
                'channel_url' => 'https://www.youtube.com/channel/UCcreatorchannel000000001',
                'comment_instructions' => 'Say what you liked.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.services.resume', $product->slug));

        $location = $this->actingAs($creator)
            ->get(route('dashboard.services.resume', $product->slug))
            ->headers->get('Location');

        $this->assertStringContainsString(urlencode('qty[watch]').'=80', $location);
        $this->assertStringContainsString(urlencode('qty[comment]').'=10', $location);
    }

    public function test_package_saved_note_shows_on_register_page(): void
    {
        $product = $this->product();

        $this->post(route('services.configure', $product->slug), [
            'units' => '100',
            'target_url' => self::VIDEO_URL,
        ]);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Your package is saved. Create a Creator account to finish your order.');
    }
}
