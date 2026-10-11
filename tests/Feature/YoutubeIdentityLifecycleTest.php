<?php

namespace Tests\Feature;

use App\Enums\PlatformProductStatus;
use App\Models\AgentYoutubeIdentity;
use App\Models\PlatformProduct;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Campaigns\YoutubeIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class YoutubeIdentityLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const CHANNEL = 'UCidentitychannel0000001';

    private const OTHER_CHANNEL = 'UCidentitychannel0000002';

    private string $description = '';

    /** @var list<array<string, mixed>> */
    private array $oauthChannels = [];

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('api_youtube_data_key', 'test-key');
        Http::fake(function (HttpRequest $request) {
            $url = $request->url();

            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'token', 'expires_in' => 3600]);
            }
            if (str_contains($url, '/channels') && str_contains($url, 'mine=true')) {
                return Http::response(['items' => $this->oauthChannels]);
            }
            if (str_contains($url, '/channels')) {
                $id = str_contains($url, self::OTHER_CHANNEL) ? self::OTHER_CHANNEL : self::CHANNEL;

                return Http::response(['items' => [[
                    'id' => $id,
                    'snippet' => ['title' => 'Channel', 'description' => $this->description],
                ]]]);
            }

            return Http::response([], 404);
        });
    }

    private function agent(): User
    {
        return User::factory()->agent()->kycApproved()->create();
    }

    public function test_code_in_the_description_verifies_the_channel(): void
    {
        $identities = app(YoutubeIdentityService::class);
        $identity = $identities->begin($this->agent(), 'https://www.youtube.com/channel/'.self::CHANNEL);
        $this->assertSame(AgentYoutubeIdentity::STATUS_PENDING, $identity->status);

        $this->description = 'Welcome to my channel '.$identity->verification_code;

        $this->assertSame(AgentYoutubeIdentity::STATUS_VERIFIED, $identities->check($identity)->status);
    }

    public function test_channel_linked_to_another_account_is_refused(): void
    {
        $this->linkVerifiedYoutubeChannel($this->agent(), self::CHANNEL);

        $this->expectException(InvalidArgumentException::class);
        app(YoutubeIdentityService::class)->begin($this->agent(), 'https://www.youtube.com/channel/'.self::CHANNEL);
    }

    public function test_change_request_then_approval_needs_the_new_channel_to_prove_ownership(): void
    {
        $agent = $this->agent();
        $old = $this->linkVerifiedYoutubeChannel($agent, self::CHANNEL);
        $identities = app(YoutubeIdentityService::class);

        $request = $identities->requestChange($agent, 'https://www.youtube.com/channel/'.self::OTHER_CHANNEL);
        $this->assertSame(AgentYoutubeIdentity::STATUS_CHANGE_REQUESTED, $request->status);
        $this->assertSame(AgentYoutubeIdentity::STATUS_VERIFIED, $old->fresh()->status, 'The old channel stays linked until approval.');

        $pending = $identities->approveChange($request, User::factory()->admin()->create());
        $this->assertSame(AgentYoutubeIdentity::STATUS_PENDING, $pending->status);
        $this->assertSame(AgentYoutubeIdentity::STATUS_REPLACED, $old->fresh()->status);

        $this->description = $pending->verification_code;
        $verified = $identities->check($pending);
        $this->assertSame(AgentYoutubeIdentity::STATUS_VERIFIED, $verified->status);
        $this->assertSame(self::OTHER_CHANNEL, $verified->active_channel_id);
    }

    public function test_oauth_callback_refuses_a_bad_state_and_an_account_without_a_channel(): void
    {
        SystemSetting::set('youtube_oauth_enabled', '1');
        SystemSetting::set('youtube_oauth_client_id', 'client-id');
        SystemSetting::set('youtube_oauth_client_secret', 'client-secret');
        $agent = $this->agent();

        $this->actingAs($agent)
            ->withSession(['youtube_oauth_state' => 'expected-state'])
            ->get(route('agent.youtube-channel.oauth.callback', ['state' => 'forged', 'code' => 'abc']))
            ->assertRedirect(route('agent.youtube-channel.show'))
            ->assertSessionHas('error');

        $this->actingAs($agent)
            ->withSession(['youtube_oauth_state' => 'expected-state'])
            ->get(route('agent.youtube-channel.oauth.callback', ['state' => 'expected-state', 'code' => 'abc']))
            ->assertRedirect(route('agent.youtube-channel.show'))
            ->assertSessionHas('error');

        $this->assertFalse(AgentYoutubeIdentity::query()->where('user_id', $agent->id)->exists());
    }

    public function test_four_products_data_migration_can_run_again_safely(): void
    {
        $migration = require database_path('migrations/2026_10_11_000200_four_youtube_products_data.php');
        $migration->up();
        $migration->up();

        $products = PlatformProduct::query()->where('slug', 'youtube-subscribers-watch-comments')->get();
        $this->assertCount(1, $products);
        $this->assertSame(PlatformProductStatus::Draft, $products->first()->status);
        $this->assertSame(1, PlatformProduct::query()->where('slug', 'youtube-comments')->count());
    }
}
