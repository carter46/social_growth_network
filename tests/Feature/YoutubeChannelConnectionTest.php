<?php

namespace Tests\Feature;

use App\Models\AgentYoutubeIdentity;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Campaigns\YoutubeIdentityService;
use App\Services\Engagement\TargetUrlValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class YoutubeChannelConnectionTest extends TestCase
{
    use RefreshDatabase;

    private const CHANNEL = 'UCconnectchannel00000001';

    private const OTHER_CHANNEL = 'UCconnectchannel00000002';

    /** @var list<array<string, mixed>> */
    private array $oauthChannels = [];

    /** @var 'signup'|'quota'|'server'|'timeout'|'forbidden'|'disabled'|'expired'|null */
    private ?string $oauthChannelFailure = null;

    private string $grantedScope = 'https://www.googleapis.com/auth/youtube.readonly';

    private string $description = '';

    private int $revokeCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('api_youtube_data_key', 'test-key');
        Http::fake(function (HttpRequest $request) {
            $url = $request->url();

            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'token', 'refresh_token' => 'refresh', 'expires_in' => 3600, 'scope' => $this->grantedScope]);
            }
            if (str_contains($url, 'oauth2.googleapis.com/revoke')) {
                $this->revokeCalls++;

                return Http::response([]);
            }
            if (str_contains($url, '/channels') && str_contains($url, 'mine=true')) {
                return match ($this->oauthChannelFailure) {
                    'signup' => Http::response(['error' => ['code' => 401, 'errors' => [['reason' => 'youtubeSignupRequired']]]], 401),
                    'quota' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'quotaExceeded']]]], 403),
                    'forbidden' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'insufficientPermissions']]]], 403),
                    'disabled' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'accessNotConfigured']]]], 403),
                    'expired' => Http::response(['error' => ['code' => 401, 'errors' => [['reason' => 'authError']]]], 401),
                    'server' => Http::response([], 503),
                    'timeout' => throw new ConnectionException('timed out'),
                    default => Http::response(['items' => $this->oauthChannels]),
                };
            }
            if (str_contains($url, '/playlistItems')) {
                return Http::response(['items' => [
                    [
                        'snippet' => ['title' => 'My latest video', 'resourceId' => ['videoId' => 'abcdefghijk'], 'thumbnails' => ['default' => ['url' => 'https://i.ytimg.com/x.jpg']]],
                        'status' => ['privacyStatus' => 'public'],
                    ],
                    [
                        'snippet' => ['title' => 'Private video', 'resourceId' => ['videoId' => 'privatevid1']],
                        'status' => ['privacyStatus' => 'private'],
                    ],
                ]]);
            }
            if (str_contains($url, '/channels') && str_contains($url, 'contentDetails')) {
                return Http::response(['items' => [['id' => self::CHANNEL, 'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUconnectchannel00000001']]]]]);
            }
            if (str_contains($url, '/channels')) {
                $id = str_contains($url, self::OTHER_CHANNEL) ? self::OTHER_CHANNEL : self::CHANNEL;

                return Http::response(['items' => [[
                    'id' => $id,
                    'snippet' => ['title' => 'Channel', 'customUrl' => '@mychannel', 'description' => $this->description],
                ]]]);
            }

            return Http::response([], 404);
        });
    }

    private function enableOAuth(): void
    {
        SystemSetting::set('youtube_oauth_enabled', '1');
        SystemSetting::set('youtube_oauth_client_id', 'client-id');
        SystemSetting::set('youtube_oauth_client_secret', 'client-secret');
    }

    private function agent(): User
    {
        return User::factory()->agent()->kycApproved()->create();
    }

    private function creator(): User
    {
        return User::factory()->creator()->create();
    }

    private function oauthReturn(User $user, string $routeName = 'youtube.oauth.callback', array $query = ['state' => 's', 'code' => 'abc'])
    {
        return $this->actingAs($user)
            ->withSession(['youtube_oauth_state' => 's'])
            ->get(route($routeName, $query));
    }

    private function channelItem(string $id = self::CHANNEL): array
    {
        return ['id' => $id, 'snippet' => ['title' => 'My channel', 'customUrl' => '@mychannel']];
    }

    // Google sign-in

    public function test_redirect_uses_the_shared_callback_and_lets_people_pick_an_account(): void
    {
        $this->enableOAuth();

        $location = $this->actingAs($this->agent())->get(route('agent.youtube-channel.oauth'))->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertStringContainsString('prompt=select_account+consent', $location);
        $this->assertStringContainsString(urlencode(route('youtube.oauth.callback')), $location);
        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/youtube.readonly'), $location);
    }

    public function test_a_returned_channel_is_linked_and_verified(): void
    {
        $this->enableOAuth();
        $agent = $this->agent();
        $this->oauthChannels = [$this->channelItem()];

        $this->oauthReturn($agent)
            ->assertRedirect(route('agent.youtube-channel.show'))
            ->assertSessionHas('status');

        $identity = app(YoutubeIdentityService::class)->verified($agent);
        $this->assertSame(self::CHANNEL, $identity?->channel_id);
        $this->assertSame(AgentYoutubeIdentity::METHOD_OAUTH, $identity->method);
    }

    public function test_the_legacy_agent_callback_still_works(): void
    {
        $this->enableOAuth();
        $agent = $this->agent();
        $this->oauthChannels = [$this->channelItem()];

        $this->oauthReturn($agent, 'agent.youtube-channel.oauth.callback')->assertSessionHas('status');

        $this->assertNotNull(app(YoutubeIdentityService::class)->verified($agent));
    }

    public function test_an_empty_channel_list_shows_the_create_channel_help_and_saves_nothing(): void
    {
        $this->enableOAuth();
        $agent = $this->agent();

        $this->oauthReturn($agent)
            ->assertRedirect(route('agent.youtube-channel.show'))
            ->assertSessionHas('youtube_channel_missing', 'oauth')
            ->assertSessionMissing('error');

        $this->assertFalse(AgentYoutubeIdentity::query()->where('user_id', $agent->id)->exists());

        $this->actingAs($agent)
            ->withSession(['youtube_channel_missing' => 'oauth'])
            ->get(route('agent.youtube-channel.show'))
            ->assertOk()
            ->assertSee('data-youtube-missing-channel', false)
            ->assertSee('Create a YouTube channel')
            ->assertSee('https://www.youtube.com/create_channel', false)
            ->assertSee("I've created my channel, try again", false);
    }

    public function test_youtube_signup_required_counts_as_no_channel(): void
    {
        $this->enableOAuth();
        $this->oauthChannelFailure = 'signup';

        $this->oauthReturn($this->agent())->assertSessionHas('youtube_channel_missing', 'oauth');
    }

    public function test_cancelled_consent_and_missing_permission_give_a_permission_message(): void
    {
        $this->enableOAuth();
        $agent = $this->agent();

        $this->oauthReturn($agent, 'youtube.oauth.callback', ['error' => 'access_denied', 'state' => 's'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'cancelled'))
            ->assertSessionMissing('youtube_channel_missing');

        $this->grantedScope = 'openid email';
        $this->oauthChannels = [$this->channelItem()];
        $this->oauthReturn($agent)
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'permission'))
            ->assertSessionMissing('youtube_channel_missing');

        $this->grantedScope = 'https://www.googleapis.com/auth/youtube.readonly';
        $this->oauthChannelFailure = 'forbidden';
        $this->oauthReturn($agent)->assertSessionHas('error', fn ($m) => str_contains($m, 'permission'));

        $this->assertNull(app(YoutubeIdentityService::class)->verified($agent));
    }

    public function test_temporary_api_failures_never_claim_there_is_no_channel(): void
    {
        $this->enableOAuth();
        $agent = $this->agent();

        foreach (['quota', 'server', 'timeout', 'disabled'] as $failure) {
            $this->oauthChannelFailure = $failure;
            $this->oauthReturn($agent)
                ->assertSessionHas('error', fn ($m) => str_contains($m, 'couldn\'t reach YouTube'))
                ->assertSessionMissing('youtube_channel_missing');
        }

        $this->assertFalse(AgentYoutubeIdentity::query()->where('user_id', $agent->id)->exists());
    }

    public function test_an_invalid_token_asks_to_sign_in_again(): void
    {
        $this->enableOAuth();
        $this->oauthChannelFailure = 'expired';

        $this->oauthReturn($this->agent())
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'expired'))
            ->assertSessionMissing('youtube_channel_missing');
    }

    public function test_other_google_errors_are_reported_without_claiming_no_channel(): void
    {
        $this->enableOAuth();

        $this->oauthReturn($this->agent(), 'youtube.oauth.callback', ['error' => 'admin_policy_enforced', 'state' => 's'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'could not be completed'))
            ->assertSessionMissing('youtube_channel_missing');
    }

    public function test_redirect_does_not_merge_previously_granted_scopes(): void
    {
        $this->enableOAuth();

        $location = $this->actingAs($this->agent())->get(route('agent.youtube-channel.oauth'))->headers->get('Location');

        $this->assertStringNotContainsString('include_granted_scopes', $location);
        $this->assertStringContainsString('access_type=offline', $location);
    }

    public function test_a_link_that_finds_no_channel_shows_link_specific_help(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)
            ->from(route('agent.youtube-channel.show'))
            ->post(route('agent.youtube-channel.store'), ['channel_url' => 'not a channel'])
            ->assertSessionHas('youtube_channel_missing', 'link');

        $this->actingAs($agent)
            ->withSession(['youtube_channel_missing' => 'link'])
            ->get(route('agent.youtube-channel.show'))
            ->assertSee('data-source="link"', false)
            ->assertSee("We couldn't find a YouTube channel at that link")
            ->assertDontSee('on this Google account');
    }

    public function test_retrying_after_creating_a_channel_links_it(): void
    {
        $this->enableOAuth();
        $agent = $this->agent();

        $this->oauthReturn($agent)->assertSessionHas('youtube_channel_missing', 'oauth');

        $this->oauthChannels = [$this->channelItem()];
        $this->oauthReturn($agent)->assertSessionHas('status');

        $this->assertNotNull(app(YoutubeIdentityService::class)->verified($agent));
    }

    public function test_google_sign_in_is_blocked_when_the_admin_setting_is_off(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->get(route('agent.youtube-channel.oauth'))->assertNotFound();
        $this->oauthReturn($agent)->assertNotFound();
    }

    public function test_removing_google_access_revokes_the_token(): void
    {
        $this->enableOAuth();
        $agent = $this->agent();
        $this->oauthChannels = [$this->channelItem()];
        $this->oauthReturn($agent);

        $this->actingAs($agent)->post(route('agent.youtube-channel.oauth.disconnect'))->assertSessionHas('status');

        $this->assertSame(1, $this->revokeCalls);
        $this->assertNull(app(YoutubeIdentityService::class)->verified($agent)->oauth_token);
    }

    // Manual link

    public function test_common_channel_link_forms_are_normalized(): void
    {
        $this->assertSame('https://www.youtube.com/@mychannel', TargetUrlValidator::normalizeChannelInput('@MyChannel'));
        $this->assertSame('https://www.youtube.com/@mychannel', TargetUrlValidator::normalizeChannelInput('youtube.com/@mychannel/videos'));
        $this->assertSame('https://www.youtube.com/@mychannel', TargetUrlValidator::normalizeChannelInput('https://m.youtube.com/@mychannel'));
        $this->assertSame('https://www.youtube.com/channel/'.self::CHANNEL, TargetUrlValidator::normalizeChannelInput(self::CHANNEL));
        $this->assertSame('https://www.youtube.com/channel/'.self::CHANNEL, TargetUrlValidator::normalizeChannelInput('www.youtube.com/channel/'.self::CHANNEL.'/featured'));
        $this->assertNull(TargetUrlValidator::normalizeChannelInput('https://www.youtube.com/watch?v=abcdefghijk'));
        $this->assertNull(TargetUrlValidator::normalizeChannelInput('https://example.com/@mychannel'));
        $this->assertNull(TargetUrlValidator::normalizeChannelInput(''));
    }

    public function test_preview_resolves_a_handle_without_https(): void
    {
        $this->actingAs($this->agent())
            ->postJson(route('agent.youtube-channel.preview'), ['channel_url' => 'youtube.com/@mychannel'])
            ->assertOk()
            ->assertJsonPath('channel.id', self::CHANNEL)
            ->assertJsonPath('channel.resolved', true);
    }

    public function test_invalid_links_get_clear_messages_and_the_create_channel_hint(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)
            ->postJson(route('agent.youtube-channel.preview'), ['channel_url' => 'https://www.youtube.com/watch?v=abcdefghijk'])
            ->assertStatus(422)
            ->assertJsonPath('missing_channel_help', true)
            ->assertJson(fn ($json) => $json->where('message', fn ($m) => str_contains($m, 'video link'))->etc());

        $this->actingAs($agent)
            ->postJson(route('agent.youtube-channel.preview'), ['channel_url' => 'not a channel'])
            ->assertStatus(422)
            ->assertJsonPath('missing_channel_help', true);

        $this->actingAs($agent)
            ->postJson(route('agent.youtube-channel.preview'), ['channel_url' => 'https://www.youtube.com/c/oldname'])
            ->assertStatus(422)
            ->assertJsonPath('missing_channel_help', false)
            ->assertJson(fn ($json) => $json->where('message', fn ($m) => str_contains($m, '@handle'))->etc());
    }

    public function test_a_missing_code_keeps_the_channel_pending_and_the_right_code_verifies_it(): void
    {
        $identities = app(YoutubeIdentityService::class);
        $identity = $identities->begin($this->agent(), '@mychannel');

        $this->description = 'No code here';
        $this->assertSame(AgentYoutubeIdentity::STATUS_PENDING, $identities->check($identity)->status);

        $this->description = 'Hello '.$identity->verification_code;
        $this->assertSame(AgentYoutubeIdentity::STATUS_VERIFIED, $identities->check($identity->fresh())->status);
    }

    public function test_without_an_api_key_the_channel_goes_to_admin_review(): void
    {
        SystemSetting::set('api_youtube_data_key', '');

        $identity = app(YoutubeIdentityService::class)->begin($this->agent(), 'https://www.youtube.com/@mychannel');

        $this->assertSame(AgentYoutubeIdentity::METHOD_ADMIN, $identity->method);
        $this->assertSame(AgentYoutubeIdentity::STATUS_PENDING, $identity->status);
    }

    public function test_the_link_form_includes_find_your_link_help(): void
    {
        $this->actingAs($this->agent())
            ->get(route('agent.youtube-channel.show'))
            ->assertOk()
            ->assertSee('data-youtube-find-link-help', false)
            ->assertSee('Where do I find my channel link?');
    }

    // Rules

    public function test_a_channel_on_an_agent_account_is_blocked_for_a_creator_and_vice_versa(): void
    {
        $this->linkVerifiedYoutubeChannel($this->agent(), self::CHANNEL);

        try {
            app(YoutubeIdentityService::class)->begin($this->creator(), 'https://www.youtube.com/channel/'.self::CHANNEL);
            $this->fail('Expected the duplicate channel to be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already linked to another account', $e->getMessage());
        }

        $this->linkVerifiedYoutubeChannel($this->creator(), self::OTHER_CHANNEL);
        $this->expectException(InvalidArgumentException::class);
        app(YoutubeIdentityService::class)->begin($this->agent(), 'https://www.youtube.com/channel/'.self::OTHER_CHANNEL);
    }

    public function test_creators_disconnect_and_reconnect_without_admin_approval(): void
    {
        $creator = $this->creator();
        $old = $this->linkVerifiedYoutubeChannel($creator, self::CHANNEL);

        $this->actingAs($creator)->post(route('agent.youtube-channel.change'))->assertForbidden();

        $this->actingAs($creator)
            ->post(route('dashboard.youtube-channel.unlink'))
            ->assertRedirect(route('dashboard.youtube-channel.show'));

        $this->assertSame(AgentYoutubeIdentity::STATUS_REPLACED, $old->fresh()->status);
        $this->assertNull($old->fresh()->active_channel_id);

        $identities = app(YoutubeIdentityService::class);
        $pending = $identities->begin($creator, 'https://www.youtube.com/channel/'.self::OTHER_CHANNEL);
        $this->description = $pending->verification_code;
        $this->assertSame(AgentYoutubeIdentity::STATUS_VERIFIED, $identities->check($pending)->status);
    }

    public function test_agents_cannot_unlink_and_still_use_change_requests(): void
    {
        $agent = $this->agent();
        $this->linkVerifiedYoutubeChannel($agent, self::CHANNEL);

        $this->actingAs($agent)->post(route('dashboard.youtube-channel.unlink'))->assertForbidden();

        $this->actingAs($agent)
            ->post(route('agent.youtube-channel.change'), ['channel_url' => 'https://www.youtube.com/channel/'.self::OTHER_CHANNEL])
            ->assertSessionHas('status');
        $this->assertTrue(AgentYoutubeIdentity::query()->where('user_id', $agent->id)->where('status', AgentYoutubeIdentity::STATUS_CHANGE_REQUESTED)->exists());
    }

    public function test_switching_from_the_code_method_to_google_does_not_leave_duplicates(): void
    {
        $this->enableOAuth();
        $agent = $this->agent();
        $pending = app(YoutubeIdentityService::class)->begin($agent, 'https://www.youtube.com/channel/'.self::CHANNEL);

        $this->oauthChannels = [$this->channelItem()];
        $this->oauthReturn($agent)->assertSessionHas('status');

        $this->assertSame(AgentYoutubeIdentity::STATUS_REPLACED, $pending->fresh()->status);
        $this->assertSame(1, AgentYoutubeIdentity::query()->where('user_id', $agent->id)->whereIn('status', ['verified', 'pending'])->count());
    }

    // Creators

    public function test_creator_dashboard_works_without_a_channel_and_shows_a_dismissible_prompt(): void
    {
        $creator = $this->creator();

        $this->actingAs($creator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-creator-channel-prompt', false)
            ->assertSee('Get more accurate campaign insights')
            ->assertSee('Maybe later')
            ->assertSee('creator_channel_prompt_dismissed:'.$creator->id, false)
            ->assertSee(route('dashboard.youtube-channel.show'), false);

        $this->linkVerifiedYoutubeChannel($creator, self::CHANNEL);
        $this->actingAs($creator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Get more accurate campaign insights')
            ->assertSee('data-creator-channel-prompt-reset', false);
    }

    public function test_creator_channel_page_supports_both_methods_and_is_optional(): void
    {
        $this->enableOAuth();
        $creator = $this->creator();

        $this->actingAs($creator)
            ->get(route('dashboard.youtube-channel.show'))
            ->assertOk()
            ->assertSee("It's optional", false)
            ->assertSee(route('dashboard.youtube-channel.oauth'), false)
            ->assertSee(route('dashboard.youtube-channel.store'), false)
            ->assertDontSee('subscriptions private');

        $this->oauthChannels = [$this->channelItem()];
        $this->oauthReturn($creator)->assertRedirect(route('dashboard.youtube-channel.show'));
        $this->assertSame(self::CHANNEL, app(YoutubeIdentityService::class)->verified($creator)?->channel_id);
    }

    public function test_creator_without_a_channel_gets_the_create_channel_help(): void
    {
        $this->enableOAuth();
        $creator = $this->creator();

        $this->oauthReturn($creator)
            ->assertRedirect(route('dashboard.youtube-channel.show'))
            ->assertSessionHas('youtube_channel_missing', 'oauth');
    }

    public function test_creator_manual_link_verifies_with_the_description_code(): void
    {
        $creator = $this->creator();

        $this->actingAs($creator)
            ->post(route('dashboard.youtube-channel.store'), ['channel_url' => '@mychannel'])
            ->assertRedirect(route('dashboard.youtube-channel.show'));

        $identity = app(YoutubeIdentityService::class)->current($creator);
        $this->assertTrue($identity->isPending());

        $this->description = $identity->verification_code;
        $this->actingAs($creator)->post(route('dashboard.youtube-channel.check'))->assertSessionHas('status', 'Your YouTube channel is connected.');
    }

    public function test_videos_route_returns_only_the_creators_own_uploads(): void
    {
        $creator = $this->creator();

        $this->actingAs($creator)->getJson(route('dashboard.youtube-channel.videos'))
            ->assertOk()
            ->assertJsonPath('connected', false)
            ->assertJsonCount(0, 'videos');

        $this->linkVerifiedYoutubeChannel($creator, self::CHANNEL);
        $this->actingAs($creator)->getJson(route('dashboard.youtube-channel.videos'))
            ->assertOk()
            ->assertJsonPath('connected', true)
            ->assertJsonPath('videos.0.id', 'abcdefghijk')
            ->assertJsonPath('videos.0.url', 'https://www.youtube.com/watch?v=abcdefghijk')
            ->assertJsonCount(1, 'videos');

        $this->actingAs($this->agent())->getJson(route('dashboard.youtube-channel.videos'))->assertForbidden();
    }

    public function test_creator_sidebar_links_to_the_channel_page(): void
    {
        $this->actingAs($this->creator())
            ->get(route('dashboard.campaigns'))
            ->assertOk()
            ->assertSee(route('dashboard.youtube-channel.show'), false);
    }

    public function test_product_page_offers_the_picker_when_connected_and_a_hint_when_not(): void
    {
        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');
        \App\Models\PlatformProduct::query()->where('slug', 'youtube-likes')->update(['agent_reward_percent' => 30]);
        $creator = $this->creator();

        $this->actingAs($creator)
            ->get(route('dashboard.services.product', 'youtube-likes'))
            ->assertOk()
            ->assertDontSee('Pricing for this product is not available yet')
            ->assertSee('pkg-video', false)
            ->assertSee('data-connect-channel-hint', false)
            ->assertDontSee('data-my-channel-videos', false);

        $this->linkVerifiedYoutubeChannel($creator, self::CHANNEL);
        $this->actingAs($creator)
            ->get(route('dashboard.services.product', 'youtube-likes'))
            ->assertOk()
            ->assertSee('data-my-channel-videos', false)
            ->assertDontSee('data-connect-channel-hint', false);
    }

    public function test_campaign_page_marks_campaigns_on_the_creators_channel(): void
    {
        $creator = $this->creator();
        $campaign = \App\Models\Campaign::query()->create([
            'creator_id' => $creator->id,
            'title' => 'My campaign',
            'target_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'channel_id' => self::CHANNEL,
            'meta' => ['platform' => 'youtube'],
            'quantity' => 2,
            'completed_count' => 0,
            'locked_creator_price' => 1000,
            'locked_agent_reward' => 100,
            'status' => \App\Models\Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAs($creator)->get(route('dashboard.campaigns.show', $campaign))
            ->assertOk()
            ->assertDontSee('data-own-channel-badge', false);

        $this->linkVerifiedYoutubeChannel($creator, self::CHANNEL);
        $this->actingAs($creator)->get(route('dashboard.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('data-own-channel-badge', false);
    }

    public function test_admin_list_shows_the_account_type_and_filters_by_it(): void
    {
        $this->linkVerifiedYoutubeChannel($this->agent(), self::CHANNEL);
        $creator = $this->creator();
        $this->linkVerifiedYoutubeChannel($creator, self::OTHER_CHANNEL);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.youtube-identities.index', ['filter' => 'all']))
            ->assertOk()
            ->assertSee('Agent')
            ->assertSee('Creator');

        $this->actingAs($admin)->get(route('admin.youtube-identities.index', ['filter' => 'all', 'role' => 'user']))
            ->assertOk()
            ->assertSee(self::OTHER_CHANNEL)
            ->assertDontSee(self::CHANNEL);
    }
}
