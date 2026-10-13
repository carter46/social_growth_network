<?php

namespace Tests\Feature;

use App\Enums\CampaignTask;
use App\Enums\PlatformProductStatus;
use App\Models\AgentYoutubeAction;
use App\Models\Campaign;
use App\Models\CampaignBudget;
use App\Models\CampaignParticipation;
use App\Models\CampaignParticipationTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Catalog\Services\PlatformCheckoutService;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Campaigns\AgentEligibilityService;
use App\Services\Campaigns\CampaignBriefException;
use App\Services\Campaigns\CampaignBriefService;
use App\Services\Campaigns\CampaignBudgetService;
use App\Services\Campaigns\CampaignFulfillmentService;
use App\Services\Campaigns\CampaignParticipationService;
use App\Services\Campaigns\CampaignTaskFlowService;
use App\Services\Campaigns\CampaignWatchSessionService;
use App\Services\Campaigns\StepVerificationService;
use App\Services\Engagement\YoutubeChannelApi;
use App\Support\ProductTaskProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class CombinedYouTubeProductsTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'youtube-subscribers-watch-comments';

    private const VIDEO = 'dQw4w9WgXcQ';

    private const CHANNEL = 'UCcreatorchannel000000001';

    private const AGENT_CHANNEL = 'UCagentchannel00000000001';

    private bool $apiSubscribed = false;

    private ?array $apiComment = null;

    /** @var array{channel_id?: string, comments_disabled?: bool} */
    private array $apiVideo = [];

    private bool $apiChannelsDown = false;

    private string $apiChannelDescription = '';

    private int $subscriptionCalls = 0;

    /** @var 'quota'|'server'|'timeout'|'malformed'|null */
    private ?string $apiFailure = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The SQLite schema still references the dropped escrows table from transactions.
        if (! Schema::hasTable('escrows')) {
            Schema::create('escrows', fn (Blueprint $table) => $table->id());
        }

        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');
    }

    private function fakeYoutubeApi(): void
    {
        SystemSetting::set('api_youtube_data_key', 'test-key');

        Http::fake(function (HttpRequest $request) {
            $url = $request->url();
            $video = $this->apiVideo;

            if ($this->apiFailure !== null && (str_contains($url, '/subscriptions') || str_contains($url, '/comments'))) {
                return match ($this->apiFailure) {
                    'quota' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'quotaExceeded']]]], 403),
                    'server' => Http::response([], 500),
                    'timeout' => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out for '.$url),
                    'malformed' => Http::response('<html>Service unavailable</html>', 200),
                };
            }

            if (str_contains($url, '/subscriptions')) {
                $this->subscriptionCalls++;

                return Http::response(['items' => $this->apiSubscribed ? [['id' => 'sub1']] : []]);
            }
            if (str_contains($url, '/comments')) {
                return Http::response(['items' => $this->apiComment ? [$this->apiComment] : []]);
            }
            if (str_contains($url, '/videos')) {
                $statistics = ($video['comments_disabled'] ?? false) ? ['viewCount' => '10'] : ['viewCount' => '10', 'commentCount' => '3'];

                return Http::response(['items' => [[
                    'id' => self::VIDEO,
                    'snippet' => ['channelId' => $video['channel_id'] ?? self::CHANNEL],
                    'statistics' => $statistics,
                ]]]);
            }
            if (str_contains($url, '/channels')) {
                if ($this->apiChannelsDown) {
                    return Http::response([], 500);
                }

                return Http::response(['items' => [[
                    'id' => self::CHANNEL,
                    'snippet' => ['title' => 'Creator channel', 'description' => $this->apiChannelDescription],
                ]]]);
            }

            return Http::response([], 404);
        });
    }

    private function fundPlatformWallet(string $amount = '10000.00'): Wallet
    {
        $wallet = app(WalletService::class)->getPlatformWallet();
        $wallet->forceFill(['balance' => $amount, 'locked_balance' => 0])->save();

        return $wallet->fresh();
    }

    private function agent(?string $channelId = null): User
    {
        $agent = User::factory()->agent()->kycApproved()->create();
        Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 0, 'locked_balance' => 0]);
        $this->linkVerifiedYoutubeChannel($agent, $channelId);

        return $agent;
    }

    private function bundledCampaign(array $overrides = []): Campaign
    {
        return Campaign::query()->create(array_merge([
            'creator_id' => User::factory()->creator()->create()->id,
            'platform_product_id' => null,
            'title' => 'Subscribe, watch and comment',
            'target_url' => 'https://www.youtube.com/watch?v='.self::VIDEO,
            'engagement_metric' => ProductTaskProfile::forSlug(self::SLUG)->legacyMetric()?->value,
            'quantity' => 2,
            'completed_count' => 0,
            'locked_creator_price' => 1000,
            'locked_agent_reward' => 400,
            'status' => Campaign::STATUS_ACTIVE,
            'estimated_minutes' => 2,
            'completion_minutes' => 30,
            'meta' => ['platform' => 'youtube', 'product_slug' => self::SLUG],
            'required_tasks' => ['subscribe', 'watch', 'comment'],
            'channel_url' => 'https://www.youtube.com/channel/'.self::CHANNEL,
            'channel_id' => self::CHANNEL,
            'video_id' => self::VIDEO,
            'comment_instructions' => 'Say what you liked about the video.',
        ], $overrides));
    }

    private function commentLink(string $commentId = 'UgxCommentAbc123', string $video = self::VIDEO): string
    {
        return 'https://www.youtube.com/watch?v='.$video.'&lc='.$commentId;
    }

    /** Does every step in order and submits once. */
    private function completeAllSteps(CampaignParticipation $participation, User $agent, string $commentId = 'UgxCommentAbc123'): CampaignParticipation
    {
        $flow = app(CampaignTaskFlowService::class);

        $flow->saveStep($participation, $agent, CampaignTask::Subscribe, []);
        $flow->proceed($participation, $agent);

        $session = app(CampaignWatchSessionService::class)->start($participation);
        $this->travel(2)->minutes();
        $this->travel(5)->seconds();
        app(CampaignWatchSessionService::class)->claim($participation, $session->token);
        $flow->proceed($participation, $agent);

        $flow->saveStep($participation, $agent, CampaignTask::Comment, ['proof_url' => $this->commentLink($commentId)]);

        return $flow->submit($participation, $agent);
    }

    private function adminProductPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'YouTube Subscribers + Watch Hours + Comments',
            'description' => 'Bundled task',
            'status' => 'draft',
            'pricing' => [
                'min_units' => 10,
                'max_units' => 1000,
                'pricing_units' => 10,
                'price' => 5000,
            ],
            'agent_reward_percent' => 40,
            'estimated_minutes' => 3,
            'completion_minutes' => 20,
            'task_order' => ['comment', 'watch', 'subscribe'],
        ], $overrides);
    }

    public function test_admin_can_reorder_tasks_and_invalid_orders_are_refused(): void
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->adminProductPayload())
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(['comment', 'watch', 'subscribe'], $product->task_order);
        $this->assertSame(20, (int) $product->completion_minutes);
        $this->assertSame(
            ['comment', 'watch', 'subscribe'],
            array_map(fn ($t) => $t->value, $product->taskProfile()->tasks()),
        );

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->adminProductPayload([
                'task_order' => ['comment', 'comment', 'watch'],
            ]))
            ->assertSessionHasErrors('task_order');

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->adminProductPayload([
                'task_order' => ['comment', 'like', 'watch'],
            ]))
            ->assertSessionHasErrors('task_order.1');

        $this->assertSame(['comment', 'watch', 'subscribe'], $product->fresh()->task_order);
    }

    public function test_completion_time_must_be_longer_than_watch_time(): void
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->adminProductPayload([
                'estimated_minutes' => 10,
                'completion_minutes' => 10,
            ]))
            ->assertSessionHasErrors('completion_minutes');

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->adminProductPayload([
                'completion_minutes' => null,
            ]))
            ->assertSessionHasErrors('completion_minutes');
    }

    public function test_admin_saves_companion_limits_and_refuses_bad_ranges(): void
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->adminProductPayload([
                'task_limits' => [
                    'watch' => ['min' => 20, 'max' => 400],
                    'comment' => ['min' => 0, 'max' => 100],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['comment' => ['min' => 0, 'max' => 100], 'watch' => ['min' => 20, 'max' => 400]],
            $product->fresh()->companionLimits(),
        );
        $this->assertNotContains('Needs watch hours limits', $product->fresh()->taskSetupIssues());

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->adminProductPayload([
                'task_limits' => [
                    'watch' => ['min' => 400, 'max' => 20],
                    'comment' => ['min' => 0, 'max' => 100],
                ],
            ]))
            ->assertSessionHasErrors('task_limits.watch.max');

        $this->assertSame(20, $product->fresh()->companionLimits()['watch']['min']);
    }

    public function test_admin_picks_the_main_service_and_the_others_become_companions(): void
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.platform-products.edit', $product))
            ->assertOk()
            ->assertSee('name="main_task"', false)
            ->assertSee('\u0022main\u0022:\u0022subscribe\u0022', false);

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->adminProductPayload([
                'main_task' => 'comment',
                'task_limits' => [
                    'watch' => ['min' => 10, 'max' => 400],
                    'subscribe' => ['min' => 0, 'max' => 50],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('comment', $product->main_task);
        $this->assertSame(CampaignTask::Comment, $product->taskProfile()->primaryTask());
        $this->assertSame(['watch', 'subscribe'], array_map(fn ($t) => $t->value, $product->taskProfile()->companionTasks()));
        $this->assertSame(
            ['watch' => ['min' => 10, 'max' => 400], 'subscribe' => ['min' => 0, 'max' => 50]],
            $product->companionLimits(),
        );

        $layout = app(\App\Services\Catalog\PackageQuoteService::class)->layout($product);
        $this->assertSame(['comment', 'watch', 'subscribe'], array_column($layout['rows'], 'task'));
        $this->assertSame('Comments', $layout['rows'][0]['label']);

        $this->actingAs($admin)
            ->put(route('admin.platform-products.update', $product), $this->adminProductPayload(['main_task' => 'like']))
            ->assertSessionHasErrors('main_task');
        $this->assertSame('comment', $product->fresh()->main_task);
    }

    public function test_combined_product_without_companion_limits_reports_a_setup_issue(): void
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $product->forceFill(['estimated_minutes' => 3, 'completion_minutes' => 20, 'task_limits' => null])->save();

        $issues = $product->fresh()->taskSetupIssues();

        $this->assertContains('Needs watch hours limits', $issues);
        $this->assertContains('Needs comments limits', $issues);
    }

    public function test_likes_orders_keep_single_task_pricing_and_campaigns(): void
    {
        $product = PlatformProduct::query()->where('slug', 'youtube-likes')->firstOrFail();
        $product->forceFill([
            'status' => PlatformProductStatus::Published,
            'agent_reward_percent' => 40,
            'estimated_minutes' => 1,
            'completion_minutes' => 30,
        ])->save();
        $variant = $product->pricingVariant();
        $variant->forceFill([
            'pricing_mode' => PlatformProductVariant::PRICING_PER_UNIT,
            'unit_price' => 5,
            'min_units' => 100,
            'max_units' => 1000,
            'is_active' => true,
        ])->save();

        $order = app(PlatformCheckoutService::class)->createManualBankTransferOrderForUser(
            User::factory()->creator()->create(),
            $product->fresh(),
            [
                'variant_id' => $variant->id,
                'quantity' => 150,
                'task_quantities' => [],
                'target_url' => 'https://www.youtube.com/watch?v='.self::VIDEO,
            ],
            false,
        );

        $item = $order->items()->first();
        $this->assertSame(750.0, (float) $item->line_total);
        $this->assertArrayNotHasKey('task_quantities', $item->options);

        app(CampaignFulfillmentService::class)->createFromPaidOrder($order->fresh('items'));
        $campaign = Campaign::query()->where('order_item_id', $item->id)->firstOrFail();
        $this->assertNull($campaign->task_targets);
        $this->assertSame(150, (int) $campaign->quantity);
        $this->assertFalse($campaign->usesTaskTargets());
    }

    public function test_admin_order_prices_companion_quantities_and_refuses_out_of_range(): void
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $product->forceFill([
            'status' => PlatformProductStatus::Published,
            'agent_reward_percent' => 40,
            'estimated_minutes' => 2,
            'completion_minutes' => 30,
            'task_limits' => ['watch' => ['min' => 10, 'max' => 100], 'comment' => ['min' => 0, 'max' => 50]],
        ])->save();
        $product->pricingVariant()->forceFill([
            'pricing_mode' => PlatformProductVariant::PRICING_PER_UNIT,
            'unit_price' => 100,
            'min_units' => 10,
            'max_units' => 1000,
            'is_active' => true,
        ])->save();
        $buyer = User::factory()->creator()->create();
        $payload = [
            'user_id' => $buyer->id,
            'product_slug' => self::SLUG,
            'quantity' => 20,
            'target_url' => 'https://www.youtube.com/watch?v='.self::VIDEO,
            'channel_url' => 'https://www.youtube.com/channel/'.self::CHANNEL,
            'comment_instructions' => 'Say what you liked.',
            'mark_paid' => '0',
        ];
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.orders.store'), $payload + ['qty' => ['watch' => 500]])
            ->assertSessionHas('error');
        $this->assertSame(0, Order::query()->where('user_id', $buyer->id)->count());

        $this->actingAs($admin)
            ->post(route('admin.orders.store'), $payload + ['qty' => ['watch' => 15, 'comment' => 5]])
            ->assertSessionHasNoErrors();

        $item = Order::query()->where('user_id', $buyer->id)->firstOrFail()->items()->first();
        $this->assertSame(['subscribe' => 20, 'watch' => 15, 'comment' => 5], $item->options['task_quantities']);
        $this->assertSame(3000.0, (float) $item->line_total);
    }

    public function test_product_without_completion_time_reports_a_setup_issue(): void
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $product->forceFill(['estimated_minutes' => 3, 'completion_minutes' => null])->save();

        $this->assertNotEmpty($product->fresh()->taskSetupIssues());
    }

    public function test_bundled_task_pays_once_after_every_step_is_verified(): void
    {
        $this->fakeYoutubeApi();
        $platform = $this->fundPlatformWallet();
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign();

        $budget = app(CampaignBudgetService::class)->holdForCampaign($campaign);
        $this->assertNotNull($budget);
        $this->assertSame('800.00', number_format((float) $budget->funded_amount, 2, '.', ''));
        $this->assertEquals(800.0, (float) $platform->fresh()->locked_balance);

        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $steps = $participation->steps()->orderBy('position')->get();
        $this->assertSame(['subscribe', 'watch', 'comment'], $steps->pluck('task')->all());
        $this->assertSame(CampaignParticipationTask::STATUS_ACTIVE, $steps[0]->status);
        $this->assertSame(CampaignParticipationTask::STATUS_LOCKED, $steps[1]->status);

        $flow = app(CampaignTaskFlowService::class);

        try {
            $flow->submit($participation, $agent);
            $this->fail('Submitting before every step is done must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Complete every step', $e->getMessage());
        }

        try {
            $flow->saveStep($participation, $agent, CampaignTask::Comment, ['proof_url' => $this->commentLink()]);
            $this->fail('Skipping ahead must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('in order', $e->getMessage());
        }

        $flow->saveStep($participation, $agent, CampaignTask::Subscribe, []);
        $flow->proceed($participation, $agent);
        $this->assertSame(
            CampaignParticipationTask::STATUS_DONE,
            $participation->steps()->where('task', 'subscribe')->value('status'),
            'Proceed never accepts a step.',
        );

        $session = app(CampaignWatchSessionService::class)->start($participation);
        $this->travel(2)->minutes();
        $this->travel(5)->seconds();
        app(CampaignWatchSessionService::class)->claim($participation, $session->token);

        $this->assertSame(CampaignParticipation::STATUS_STARTED, $participation->fresh()->status);
        $this->assertEquals(0.0, (float) $agent->wallet()->first()->balance, 'The watch claim on a bundled task never pays.');

        $flow->proceed($participation, $agent);
        $flow->saveStep($participation, $agent, CampaignTask::Comment, ['proof_url' => $this->commentLink()]);

        $this->apiSubscribed = true;
        $this->apiComment = [
            'id' => 'UgxCommentAbc123',
            'snippet' => [
                'videoId' => self::VIDEO,
                'authorChannelId' => ['value' => self::AGENT_CHANNEL],
                'textDisplay' => 'Great video',
            ],
        ];

        $flow->submit($participation, $agent);
        app(StepVerificationService::class)->runForParticipation($participation->id);

        $participation->refresh();
        $this->assertSame(CampaignParticipation::STATUS_PAID, $participation->status);
        $this->assertEquals(400.0, (float) $agent->wallet()->first()->balance);
        $this->assertSame('400.00', number_format((float) $budget->fresh()->paid_amount, 2, '.', ''));
        $this->assertEquals(400.0, (float) $platform->fresh()->locked_balance);

        app(CampaignParticipationService::class)->approve($participation->fresh());
        app(StepVerificationService::class)->runForParticipation($participation->id);

        $this->assertEquals(400.0, (float) $agent->wallet()->first()->balance, 'A second approval never pays twice.');
        $this->assertSame(1, Transaction::query()->where('reference', 'CMP-PAY-'.$participation->id)->count());

        $this->assertTrue(AgentYoutubeAction::query()
            ->where('user_id', $agent->id)
            ->where('action', AgentYoutubeAction::ACTION_SUBSCRIBE)
            ->where('target_channel_id', self::CHANNEL)
            ->exists());

        $sameChannel = $this->bundledCampaign(['title' => 'Same channel again']);
        $this->assertFalse(app(AgentEligibilityService::class)->isEligible($agent, $sameChannel));
    }

    public function test_unconfirmed_steps_go_to_admin_review_and_admin_approval_pays_once(): void
    {
        $platform = $this->fundPlatformWallet();
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign();
        app(CampaignBudgetService::class)->holdForCampaign($campaign);

        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $this->completeAllSteps($participation, $agent);
        app(StepVerificationService::class)->runForParticipation($participation->id);

        $participation->refresh();
        $this->assertSame(CampaignParticipation::STATUS_UNDER_REVIEW, $participation->status);
        $this->assertEquals(0.0, (float) $agent->wallet()->first()->balance, 'No API answer never pays.');
        $this->assertSame(
            CampaignParticipationTask::STATUS_ACCEPTED,
            $participation->steps()->where('task', 'watch')->value('status'),
        );
        $this->assertSame(
            CampaignParticipationTask::STATUS_NEEDS_REVIEW,
            $participation->steps()->where('task', 'comment')->value('status'),
        );

        $admin = User::factory()->admin()->create();
        app(StepVerificationService::class)->adminApprove($participation, $admin);
        app(StepVerificationService::class)->adminApprove($participation->fresh(), $admin);

        $this->assertSame(CampaignParticipation::STATUS_PAID, $participation->fresh()->status);
        $this->assertEquals(400.0, (float) $agent->wallet()->first()->balance);
        $this->assertEquals(400.0, (float) $platform->fresh()->locked_balance);
    }

    public function test_admin_rejection_pays_nothing(): void
    {
        $this->fundPlatformWallet();
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign();
        app(CampaignBudgetService::class)->holdForCampaign($campaign);

        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $this->completeAllSteps($participation, $agent);
        app(StepVerificationService::class)->runForParticipation($participation->id);

        $admin = User::factory()->admin()->create();
        app(StepVerificationService::class)->adminReject($participation->fresh(), $admin, 'Comment was not posted.');

        $this->assertSame(CampaignParticipation::STATUS_REJECTED, $participation->fresh()->status);
        $this->assertEquals(0.0, (float) $agent->wallet()->first()->balance);
        $this->assertSame(
            CampaignParticipationTask::STATUS_REJECTED,
            $participation->steps()->where('task', 'comment')->value('status'),
        );
    }

    public function test_comment_link_must_be_a_new_comment_on_the_campaign_video(): void
    {
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign(['required_tasks' => ['comment', 'subscribe', 'watch']]);
        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $flow = app(CampaignTaskFlowService::class);

        $refused = [
            'https://www.youtube.com/watch?v='.self::VIDEO,
            $this->commentLink('UgxCommentAbc123', 'aaaaaaaaaaa'),
        ];
        foreach ($refused as $link) {
            try {
                $flow->saveStep($participation, $agent, CampaignTask::Comment, ['proof_url' => $link]);
                $this->fail('Comment link should have been refused: '.$link);
            } catch (InvalidArgumentException) {
                $this->assertSame(
                    CampaignParticipationTask::STATUS_ACTIVE,
                    $participation->steps()->where('task', 'comment')->value('status'),
                );
            }
        }

        AgentYoutubeAction::query()->create([
            'user_id' => User::factory()->agent()->create()->id,
            'action' => AgentYoutubeAction::ACTION_COMMENT,
            'external_ref' => 'UgxUsedComment99',
            'target_video_id' => self::VIDEO,
            'evidence' => 'api_verified',
            'status' => AgentYoutubeAction::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        try {
            $flow->saveStep($participation, $agent, CampaignTask::Comment, ['proof_url' => $this->commentLink('UgxUsedComment99')]);
            $this->fail('A comment used for another task must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already been used', $e->getMessage());
        }

        $step = $flow->saveStep($participation, $agent, CampaignTask::Comment, ['proof_url' => $this->commentLink('UgxFreshComment1')]);
        $this->assertSame(CampaignParticipationTask::STATUS_DONE, $step->status);
        $this->assertSame('UgxFreshComment1', $step->proof_meta['comment_id']);
    }

    public function test_previous_like_hides_only_campaigns_for_the_same_video(): void
    {
        $agent = $this->agent();
        $likes = fn (string $video) => Campaign::query()->create([
            'creator_id' => User::factory()->creator()->create()->id,
            'title' => 'Likes '.$video,
            'target_url' => 'https://www.youtube.com/watch?v='.$video,
            'engagement_metric' => 'likes',
            'video_id' => $video,
            'quantity' => 5,
            'completed_count' => 0,
            'locked_creator_price' => 0,
            'locked_agent_reward' => 0,
            'status' => Campaign::STATUS_ACTIVE,
            'meta' => ['platform' => 'youtube', 'product_slug' => 'youtube-likes'],
        ]);

        $liked = $likes(self::VIDEO);
        $other = $likes('bbbbbbbbbbb');

        AgentYoutubeAction::query()->create([
            'user_id' => $agent->id,
            'action' => AgentYoutubeAction::ACTION_LIKE,
            'target_video_id' => self::VIDEO,
            'evidence' => 'api_verified',
            'status' => AgentYoutubeAction::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $eligibility = app(AgentEligibilityService::class);
        $this->assertFalse($eligibility->isEligible($agent, $liked));
        $this->assertTrue($eligibility->isEligible($agent, $other));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(AgentEligibilityService::NOT_AVAILABLE);
        app(CampaignParticipationService::class)->start($agent, $liked);
    }

    public function test_previous_subscriber_cannot_take_a_campaign_for_the_same_channel(): void
    {
        $agent = $this->agent();
        $campaign = $this->bundledCampaign();

        AgentYoutubeAction::query()->create([
            'user_id' => $agent->id,
            'action' => AgentYoutubeAction::ACTION_SUBSCRIBE,
            'target_channel_id' => self::CHANNEL,
            'evidence' => 'api_verified',
            'status' => AgentYoutubeAction::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $this->assertFalse(app(AgentEligibilityService::class)->isEligible($agent, $campaign));
        $this->assertTrue(app(AgentEligibilityService::class)->isEligible($agent, $this->bundledCampaign([
            'channel_id' => 'UCotherchannel00000000001',
            'channel_url' => 'https://www.youtube.com/channel/UCotherchannel00000000001',
        ])));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(AgentEligibilityService::NOT_AVAILABLE);
        app(CampaignParticipationService::class)->start($agent, $campaign);
    }

    public function test_expired_task_frees_the_slot_and_refuses_late_actions(): void
    {
        $campaign = $this->bundledCampaign(['quantity' => 1, 'completion_minutes' => 10]);
        $first = $this->agent();
        $second = $this->agent();

        $participation = app(CampaignParticipationService::class)->start($first, $campaign);
        $this->assertNotNull($participation->expires_at);

        try {
            app(CampaignParticipationService::class)->start($second, $campaign);
            $this->fail('The only slot is taken while the first agent works.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('This campaign is not open for agents.', $e->getMessage());
        }

        $this->travel(11)->minutes();
        $this->artisan('campaigns:expire-participations')->assertSuccessful();

        $this->assertSame(CampaignParticipation::STATUS_EXPIRED, $participation->fresh()->status);
        $this->assertSame(
            CampaignParticipationTask::STATUS_EXPIRED,
            $participation->steps()->where('task', 'subscribe')->value('status'),
        );

        try {
            app(CampaignTaskFlowService::class)->saveStep($participation, $first, CampaignTask::Subscribe, []);
            $this->fail('Actions after the deadline must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Time ran out', $e->getMessage());
        }

        $replacement = app(CampaignParticipationService::class)->start($second, $campaign->fresh());
        $this->assertSame(CampaignParticipation::STATUS_STARTED, $replacement->status);
    }

    public function test_heartbeat_reports_time_left_and_expires_overdue_tasks(): void
    {
        $agent = $this->agent();
        $campaign = $this->bundledCampaign(['completion_minutes' => 10]);
        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);

        $response = $this->actingAs($agent)
            ->postJson(route('agent.tasks.heartbeat', $participation))
            ->assertOk()
            ->assertJson(['status' => CampaignParticipation::STATUS_STARTED, 'expired' => false]);

        $this->assertGreaterThan(500, $response->json('seconds_remaining'));
        $this->assertNotNull($participation->fresh()->last_heartbeat_at);

        $this->travel(11)->minutes();

        $this->actingAs($agent)
            ->postJson(route('agent.tasks.heartbeat', $participation))
            ->assertOk()
            ->assertJson(['status' => CampaignParticipation::STATUS_EXPIRED, 'expired' => true]);

        $this->actingAs($this->agent())
            ->postJson(route('agent.tasks.heartbeat', $participation))
            ->assertForbidden();
    }

    public function test_cancelled_campaign_refunds_unused_units_once_nothing_is_in_flight(): void
    {
        $platform = $this->fundPlatformWallet();
        $campaign = $this->bundledCampaign(['quantity' => 3]);
        $creatorWallet = Wallet::factory()->create(['user_id' => $campaign->creator_id, 'balance' => 0, 'locked_balance' => 0]);

        $budgets = app(CampaignBudgetService::class);
        $budgets->holdForCampaign($campaign);
        $this->assertEquals(1200.0, (float) $platform->fresh()->locked_balance);

        $agent = $this->agent();
        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);

        $campaign->update(['status' => Campaign::STATUS_CANCELLED]);
        $this->assertNull($budgets->refundIfCancelled($campaign->fresh()), 'Refund waits for tasks in flight.');

        $this->travel(31)->minutes();
        $this->artisan('campaigns:expire-participations')->assertSuccessful();
        $this->assertSame(CampaignParticipation::STATUS_EXPIRED, $participation->fresh()->status);

        $budget = CampaignBudget::query()->where('campaign_id', $campaign->id)->first();
        if ($budget->status === CampaignBudget::STATUS_HELD) {
            $this->assertSame('3000.00', $budgets->refundIfCancelled($campaign->fresh()));
        }

        $this->assertSame(CampaignBudget::STATUS_REFUNDED, $budget->fresh()->status);
        $this->assertEquals(3000.0, (float) $creatorWallet->fresh()->balance);
        $this->assertEquals(0.0, (float) $platform->fresh()->locked_balance);
        $this->assertEquals(7000.0, (float) $platform->fresh()->balance);
    }

    public function test_backfill_dry_run_holds_nothing_and_real_run_holds_remaining_units(): void
    {
        $this->fundPlatformWallet();
        $campaign = $this->bundledCampaign(['quantity' => 5, 'completed_count' => 2, 'locked_agent_reward' => 100]);

        $this->artisan('campaigns:backfill-budgets', ['--dry-run' => true])->assertSuccessful();
        $this->assertFalse(CampaignBudget::query()->where('campaign_id', $campaign->id)->exists());

        $this->artisan('campaigns:backfill-budgets')->assertSuccessful();
        $budget = CampaignBudget::query()->where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($budget);
        $this->assertSame('300.00', number_format((float) $budget->funded_amount, 2, '.', ''));
    }

    public function test_checkout_brief_requires_channel_link_and_comment_instructions(): void
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $briefs = app(CampaignBriefService::class);
        $base = [
            'target_url' => 'https://www.youtube.com/watch?v='.self::VIDEO,
            'channel_url' => 'https://www.youtube.com/channel/'.self::CHANNEL,
            'comment_instructions' => 'Mention your favourite part.',
        ];

        foreach (['channel_url', 'comment_instructions'] as $missing) {
            try {
                $briefs->resolve($product, array_merge($base, [$missing => '']));
                $this->fail("Missing {$missing} should be refused.");
            } catch (CampaignBriefException $e) {
                $this->assertSame($missing, $e->field);
            }
        }

        try {
            $briefs->resolve($product, array_merge($base, ['comment_instructions' => str_repeat('a', 1001)]));
            $this->fail('Instructions over 1000 characters should be refused.');
        } catch (CampaignBriefException $e) {
            $this->assertSame('comment_instructions', $e->field);
        }

        $brief = $briefs->resolve($product, $base);
        $this->assertSame(self::VIDEO, $brief['video_id']);
        $this->assertFalse((bool) ($brief['match_verified'] ?? false), 'Without an API key the match is not verified.');
    }

    public function test_checkout_brief_checks_video_channel_and_comments_with_the_api(): void
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $input = [
            'target_url' => 'https://www.youtube.com/watch?v='.self::VIDEO,
            'channel_url' => 'https://www.youtube.com/channel/'.self::CHANNEL,
            'comment_instructions' => 'Mention your favourite part.',
        ];

        $this->fakeYoutubeApi();
        $brief = app(CampaignBriefService::class)->resolve($product, $input);
        $this->assertTrue($brief['match_verified']);
        $this->assertSame(self::CHANNEL, $brief['channel_id']);

        $this->apiVideo = ['channel_id' => 'UCsomeoneelse000000000001'];
        try {
            app(CampaignBriefService::class)->resolve($product, $input);
            $this->fail('A video from another channel should be refused.');
        } catch (CampaignBriefException $e) {
            $this->assertSame('channel_url', $e->field);
        }

        $this->apiVideo = ['comments_disabled' => true];
        try {
            app(CampaignBriefService::class)->resolve($product, $input);
            $this->fail('A video with comments turned off should be refused.');
        } catch (CampaignBriefException $e) {
            $this->assertSame('target_url', $e->field);
        }
    }

    public function test_single_task_products_keep_their_original_profiles(): void
    {
        $this->assertFalse(ProductTaskProfile::forSlug('youtube-likes')->isMultiTask());
        $this->assertFalse(ProductTaskProfile::forSlug('youtube-watch-hours')->isMultiTask());
        $this->assertTrue(ProductTaskProfile::forSlug('youtube-watch-hours')->paysOnWatchClaim());
        $this->assertTrue(ProductTaskProfile::forSlug('youtube-subscribers')->isMultiTask());
        $this->assertFalse(ProductTaskProfile::forCampaign(new Campaign(['engagement_metric' => 'subscribers']))->usesStepFlow());
        $this->assertTrue(ProductTaskProfile::forSlug('youtube-comments')->needsCommentInstructions());
        $this->assertSame(
            ['subscribe', 'watch', 'comment'],
            ProductTaskProfile::forSlug(self::SLUG)->taskValues(),
        );
    }

    private function paidOrderLine(PlatformProduct $product, array $options): OrderItem
    {
        $order = Order::factory()->create([
            'user_id' => User::factory()->creator()->create()->id,
            'amount' => 1000,
            'total_amount' => 1000,
            'status' => 'paid',
        ]);

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'platform_product',
            'item_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100,
            'line_total' => 1000,
            'platform_product_variant_id' => $product->pricingVariant()?->id,
            'options' => array_merge(['pricing_mode' => 'per_unit', 'engagement_quantity' => 10], $options),
        ]);
    }

    public function test_order_paid_after_deploy_without_a_brief_becomes_a_legacy_single_task_campaign(): void
    {
        $product = PlatformProduct::query()->where('slug', 'youtube-comments')->firstOrFail();
        $product->forceFill(['estimated_minutes' => 2, 'completion_minutes' => 30])->save();
        $item = $this->paidOrderLine($product, ['target_url' => 'https://www.youtube.com/watch?v='.self::VIDEO]);

        app(CampaignFulfillmentService::class)->createFromPaidOrder($item->order->fresh('items'));

        $campaign = Campaign::query()->where('order_item_id', $item->id)->firstOrFail();
        $this->assertNull($campaign->required_tasks);
        $this->assertNull($campaign->comment_instructions);
        $this->assertSame('count_change', $campaign->verification_mode);
        $this->assertFalse(ProductTaskProfile::forCampaign($campaign)->usesStepFlow());
    }

    public function test_campaign_uses_the_task_setup_frozen_at_purchase(): void
    {
        $product = PlatformProduct::query()->where('slug', 'youtube-comments')->firstOrFail();
        $product->forceFill(['estimated_minutes' => 2, 'completion_minutes' => 30])->save();
        $snapshot = app(CampaignBriefService::class)->taskSnapshot($product->fresh());
        $this->assertSame(['watch', 'comment'], $snapshot['required_tasks']);

        $item = $this->paidOrderLine($product, array_merge($snapshot, [
            'target_url' => 'https://www.youtube.com/watch?v='.self::VIDEO,
            'video_id' => self::VIDEO,
            'comment_instructions' => 'Say what you liked.',
        ]));
        $product->forceFill(['estimated_minutes' => 9, 'completion_minutes' => 90])->save();

        app(CampaignFulfillmentService::class)->createFromPaidOrder($item->order->fresh('items'));

        $campaign = Campaign::query()->where('order_item_id', $item->id)->firstOrFail();
        $this->assertSame(['watch', 'comment'], $campaign->required_tasks);
        $this->assertSame(30, (int) $campaign->completion_minutes);
        $this->assertSame(2, (int) $campaign->estimated_minutes);
        $this->assertSame('tasks', $campaign->verification_mode);
        $this->assertSame('Say what you liked.', $campaign->comment_instructions);
    }

    public function test_admin_manual_purchase_of_a_product_without_task_setup_is_refused(): void
    {
        $product = PlatformProduct::query()->where('slug', 'youtube-watch-hours')->firstOrFail();
        $product->forceFill([
            'status' => PlatformProductStatus::Published,
            'agent_reward_percent' => 40,
            'estimated_minutes' => 2,
            'completion_minutes' => null,
        ])->save();
        $variant = $product->pricingVariant();
        $variant->forceFill(['pricing_mode' => PlatformProductVariant::PRICING_PER_UNIT, 'is_active' => true])->save();
        $buyer = User::factory()->creator()->create();
        $data = [
            'variant_id' => $variant->id,
            'quantity' => $variant->effectiveMinUnits(),
            'target_url' => 'https://www.youtube.com/watch?v='.self::VIDEO,
        ];
        $checkout = app(PlatformCheckoutService::class);

        try {
            $checkout->createManualBankTransferOrderForUser($buyer, $product->fresh(), $data, false);
            $this->fail('A product without a completion time should not be sold.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('not available to buy yet', $e->getMessage());
        }
        $this->assertSame(0, Order::query()->where('user_id', $buyer->id)->count());

        $product->forceFill(['completion_minutes' => 30])->save();
        $order = $checkout->createManualBankTransferOrderForUser($buyer, $product->fresh(), $data, false);
        $this->assertSame(30, (int) $order->items()->first()->options['completion_minutes']);
    }

    public function test_admin_cannot_complete_or_suspend_a_campaign_with_tasks_in_flight(): void
    {
        $platform = $this->fundPlatformWallet();
        $campaign = $this->bundledCampaign();
        $creatorWallet = Wallet::factory()->create(['user_id' => $campaign->creator_id, 'balance' => 0, 'locked_balance' => 0]);
        app(CampaignBudgetService::class)->holdForCampaign($campaign);
        $participation = app(CampaignParticipationService::class)->start($this->agent(), $campaign);
        $admin = User::factory()->admin()->create();

        foreach ([Campaign::STATUS_COMPLETED, Campaign::STATUS_SUSPENDED, Campaign::STATUS_REJECTED] as $status) {
            $this->actingAs($admin)
                ->post(route('admin.campaigns.status', $campaign), ['status' => $status])
                ->assertSessionHas('error');
        }
        $this->assertSame(Campaign::STATUS_ACTIVE, $campaign->fresh()->status);
        $this->assertSame(CampaignBudget::STATUS_HELD, $campaign->budget()->value('status'));

        $this->actingAs($admin)
            ->post(route('admin.campaigns.status', $campaign), ['status' => Campaign::STATUS_PAUSED])
            ->assertSessionHas('status');
        $this->assertSame(Campaign::STATUS_PAUSED, $campaign->fresh()->status);

        $participation->update(['status' => CampaignParticipation::STATUS_EXPIRED]);
        $this->actingAs($admin)
            ->post(route('admin.campaigns.status', $campaign), ['status' => Campaign::STATUS_COMPLETED])
            ->assertSessionHas('status');
        $this->assertSame(Campaign::STATUS_COMPLETED, $campaign->fresh()->status);
        // Nothing was delivered, so completing refunds every unit to the creator.
        $this->assertSame(CampaignBudget::STATUS_REFUNDED, $campaign->budget()->value('status'));
        $this->assertEquals(0.0, (float) $platform->fresh()->locked_balance);
        $this->assertSame(1, bccomp((string) $creatorWallet->fresh()->balance, '0', 4));
        $this->assertTrue(\App\Models\AuditLog::query()->where('action', 'campaign.status_changed')->exists());
    }

    public function test_failed_budget_hold_flags_the_campaign_and_notifies_admins(): void
    {
        $this->fundPlatformWallet('100.00');
        $admin = User::factory()->admin()->create();
        $campaign = $this->bundledCampaign();
        $budgets = app(CampaignBudgetService::class);

        $this->assertNull($budgets->holdForCampaign($campaign));
        $this->assertTrue(CampaignBudgetService::isUnfunded($campaign->fresh()));
        $this->assertSame(0, bccomp('800', (string) $campaign->fresh()->meta['funding_shortfall'], 4));
        $this->assertDatabaseHas('admin_notifications', ['type' => 'campaign.budget_unfunded']);

        $this->actingAs($admin)
            ->get(route('admin.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Agent budget not held');

        $this->fundPlatformWallet();
        $this->artisan('campaigns:backfill-budgets')->assertSuccessful();
        $this->assertFalse(CampaignBudgetService::isUnfunded($campaign->fresh()));
        $this->assertSame(CampaignBudget::STATUS_HELD, $campaign->budget()->value('status'));
    }

    public function test_api_failures_are_logged_without_the_api_key(): void
    {
        SystemSetting::set('api_youtube_data_key', 'secret-test-key');
        Http::fake(function (HttpRequest $request) {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out for '.$request->url());
        });
        Log::spy();

        $this->assertNull(app(YoutubeChannelApi::class)->videoLookup(self::VIDEO));

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
            $logged = $message.json_encode($context);

            return str_contains($logged, 'key=[redacted]') && ! str_contains($logged, 'secret-test-key');
        })->once();
    }

    private function agentComment(): array
    {
        return [
            'id' => 'UgxCommentAbc123',
            'snippet' => [
                'videoId' => self::VIDEO,
                'authorChannelId' => ['value' => self::AGENT_CHANNEL],
                'textDisplay' => 'Great video',
            ],
        ];
    }

    public function test_admin_approval_is_refused_without_side_effects_when_payment_cannot_happen(): void
    {
        $this->fundPlatformWallet();
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign();
        app(CampaignBudgetService::class)->holdForCampaign($campaign);
        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $this->completeAllSteps($participation, $agent);
        app(StepVerificationService::class)->runForParticipation($participation->id);
        $admin = User::factory()->admin()->create();
        $verifier = app(StepVerificationService::class);

        $campaign->update(['status' => Campaign::STATUS_SUSPENDED]);
        try {
            $verifier->adminApprove($participation->fresh(), $admin);
            $this->fail('A suspended campaign should not pay.');
        } catch (InvalidArgumentException) {
        }
        $this->assertSame(CampaignParticipationTask::STATUS_NEEDS_REVIEW, $participation->steps()->where('task', 'comment')->value('status'));
        $this->assertFalse(AgentYoutubeAction::query()->where('campaign_participation_id', $participation->id)->where('action', AgentYoutubeAction::ACTION_COMMENT)->exists());

        $campaign->update(['status' => Campaign::STATUS_ACTIVE]);
        $participation->update(['status' => CampaignParticipation::STATUS_EXPIRED]);
        try {
            $verifier->adminApprove($participation->fresh(), $admin);
            $this->fail('An expired task should not be approved.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Only submitted tasks', $e->getMessage());
        }
        $this->assertSame(CampaignParticipationTask::STATUS_NEEDS_REVIEW, $participation->steps()->where('task', 'comment')->value('status'));
        $this->assertEquals(0.0, (float) $agent->wallet()->first()->balance);
    }

    public function test_scheduled_run_pays_a_task_whose_steps_were_all_accepted_but_never_settled(): void
    {
        $this->fundPlatformWallet();
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign();
        app(CampaignBudgetService::class)->holdForCampaign($campaign);
        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $this->completeAllSteps($participation, $agent);

        $participation->steps()->update([
            'status' => CampaignParticipationTask::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'checked_at' => now(),
        ]);
        $participation->update(['status' => CampaignParticipation::STATUS_VERIFYING]);
        $this->fakeYoutubeApi();
        $this->apiComment = $this->agentComment();

        $verifier = app(StepVerificationService::class);
        $verifier->processDue();
        $this->assertSame(CampaignParticipation::STATUS_VERIFYING, $participation->fresh()->status, 'Recent runs are left alone.');

        $this->travel(6)->minutes();
        $verifier->processDue();
        $this->assertSame(CampaignParticipation::STATUS_PAID, $participation->fresh()->status);
        $this->assertEquals(400.0, (float) $agent->wallet()->first()->balance);

        $this->travel(10)->minutes();
        $verifier->processDue();
        $verifier->runForParticipation($participation->id);
        $this->assertSame(CampaignParticipation::STATUS_PAID, $participation->fresh()->status, 'A paid task stays paid.');
        $this->assertEquals(400.0, (float) $agent->wallet()->first()->balance);
    }

    public function test_verification_never_moves_a_rejected_task(): void
    {
        $this->fakeYoutubeApi();
        $this->fundPlatformWallet();
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign();
        app(CampaignBudgetService::class)->holdForCampaign($campaign);
        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $this->apiSubscribed = true;
        $this->apiComment = $this->agentComment();

        $lock = \Illuminate\Support\Facades\Cache::lock('campaign-step-verify:'.$participation->id, 600);
        $lock->get();
        $this->completeAllSteps($participation, $agent);
        $this->assertSame(CampaignParticipation::STATUS_SUBMITTED, $participation->fresh()->status, 'A run already in progress blocks a second one.');

        app(StepVerificationService::class)->adminReject($participation->fresh(), User::factory()->admin()->create(), 'Spam');
        $lock->release();
        app(StepVerificationService::class)->runForParticipation($participation->id);

        $this->assertSame(CampaignParticipation::STATUS_REJECTED, $participation->fresh()->status);
        $this->assertEquals(0.0, (float) $agent->wallet()->first()->balance);
        $this->assertSame(0, $participation->steps()->where('status', CampaignParticipationTask::STATUS_ACCEPTED)->count());
    }

    public function test_admin_can_release_an_abandoned_task_so_the_cancelled_campaign_refunds(): void
    {
        $platform = $this->fundPlatformWallet();
        $campaign = $this->bundledCampaign(['completion_minutes' => null]);
        $creatorWallet = Wallet::factory()->create(['user_id' => $campaign->creator_id, 'balance' => 0, 'locked_balance' => 0]);
        app(CampaignBudgetService::class)->holdForCampaign($campaign);
        $participation = app(CampaignParticipationService::class)->start($this->agent(), $campaign);
        $campaign->update(['status' => Campaign::STATUS_CANCELLED]);

        $this->travel(2)->days();
        app(\App\Services\Campaigns\ParticipationExpiryService::class)->processOverdue();
        $this->assertSame(CampaignParticipation::STATUS_STARTED, $participation->fresh()->status, 'No deadline, so it never expires on its own.');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('admin.campaigns.participations.release', [$campaign, $participation]))
            ->assertSessionHas('status');

        $this->assertSame(CampaignParticipation::STATUS_EXPIRED, $participation->fresh()->status);
        $this->assertSame(CampaignBudget::STATUS_REFUNDED, $campaign->budget()->value('status'));
        $this->assertEquals(2000.0, (float) $creatorWallet->fresh()->balance);
        $this->assertEquals(0.0, (float) $platform->fresh()->locked_balance);
        $this->assertTrue(\App\Models\AuditLog::query()->where('action', 'campaign.participation_released')->exists());

        $this->actingAs($admin)
            ->post(route('admin.campaigns.participations.release', [$campaign, $participation]))
            ->assertSessionHas('error');
    }

    public function test_hold_row_closes_when_every_unit_was_paid(): void
    {
        $this->fakeYoutubeApi();
        $this->fundPlatformWallet();
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign(['quantity' => 1]);
        app(CampaignBudgetService::class)->holdForCampaign($campaign);
        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $this->apiSubscribed = true;
        $this->apiComment = $this->agentComment();
        $this->completeAllSteps($participation, $agent);

        $this->assertSame(CampaignParticipation::STATUS_PAID, $participation->fresh()->status);
        $this->assertSame(Campaign::STATUS_COMPLETED, $campaign->fresh()->status);
        $this->assertSame(CampaignBudget::STATUS_SETTLED, $campaign->budget()->value('status'));
        $this->assertSame(
            [\App\Enums\WalletHoldStatus::Released->value],
            \App\Models\WalletHold::query()->where('reason_id', $campaign->id)->pluck('status')->map(fn ($s) => $s instanceof \BackedEnum ? $s->value : $s)->all(),
        );
    }

    public function test_brief_keeps_the_videos_channel_when_the_channel_lookup_fails(): void
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $this->fakeYoutubeApi();
        $this->apiChannelsDown = true;

        $brief = app(CampaignBriefService::class)->resolve($product, [
            'target_url' => 'https://www.youtube.com/watch?v='.self::VIDEO,
            'channel_url' => 'https://www.youtube.com/@creatorchannel',
            'comment_instructions' => 'Mention your favourite part.',
        ]);

        $this->assertSame(self::CHANNEL, $brief['channel_id']);
        $this->assertFalse($brief['match_verified']);
    }

    public function test_expired_code_never_verifies_even_if_it_appears_later(): void
    {
        $this->fakeYoutubeApi();
        $agent = User::factory()->agent()->create();
        $identity = new \App\Models\AgentYoutubeIdentity;
        $identity->forceFill([
            'user_id' => $agent->id,
            'channel_id' => self::CHANNEL,
            'channel_url' => 'https://www.youtube.com/channel/'.self::CHANNEL,
            'method' => \App\Models\AgentYoutubeIdentity::METHOD_CODE,
            'status' => \App\Models\AgentYoutubeIdentity::STATUS_PENDING,
            'verification_code' => 'SGN-ABC123',
            'code_expires_at' => now()->subMinute(),
        ])->save();
        $this->apiChannelDescription = 'My channel SGN-ABC123';

        $checked = app(\App\Services\Campaigns\YoutubeIdentityService::class)->check($identity);

        $this->assertSame(\App\Models\AgentYoutubeIdentity::STATUS_FAILED, $checked->status);
    }

    public function test_agent_is_told_when_a_revoked_channel_puts_open_tasks_on_hold(): void
    {
        $agent = $this->agent(self::AGENT_CHANNEL);
        app(CampaignParticipationService::class)->start($agent, $this->bundledCampaign());
        $identity = \App\Models\AgentYoutubeIdentity::query()->where('user_id', $agent->id)->firstOrFail();

        app(\App\Services\Campaigns\YoutubeIdentityService::class)->adminReject($identity, User::factory()->admin()->create(), 'Channel belongs to someone else.');

        $this->assertTrue(\App\Models\UserNotification::query()
            ->where('user_id', $agent->id)
            ->where('type', 'youtube_identity.open_tasks')
            ->exists());
    }

    public function test_subscription_precheck_is_reused_between_page_views(): void
    {
        $this->fakeYoutubeApi();
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign();

        $this->actingAs($agent)->get(route('agent.marketplace.show', $campaign))->assertOk();
        $this->actingAs($agent)->get(route('agent.marketplace.show', $campaign))->assertOk();

        $this->assertSame(1, $this->subscriptionCalls);
    }

    public function test_admin_verification_decisions_are_audit_logged(): void
    {
        $this->fundPlatformWallet();
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign();
        app(CampaignBudgetService::class)->holdForCampaign($campaign);
        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $this->completeAllSteps($participation, $agent);
        app(StepVerificationService::class)->runForParticipation($participation->id);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.verifications.approve', $participation))
            ->assertRedirect(route('admin.verifications'));

        $this->assertSame(CampaignParticipation::STATUS_PAID, $participation->fresh()->status);
        $log = \App\Models\AuditLog::query()->where('action', 'campaign.participation_approved')->first();
        $this->assertNotNull($log);
    }

    public function test_reward_cannot_be_paid_twice_from_the_hold(): void
    {
        $platform = $this->fundPlatformWallet();
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign();
        app(CampaignBudgetService::class)->holdForCampaign($campaign);
        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $wallets = app(WalletService::class);

        $wallets->payCampaignRewardFromHold($agent, $participation->id, '400.00', 'Campaign reward');
        $before = [(string) $platform->fresh()->balance, (string) $platform->fresh()->locked_balance, (string) $agent->wallet()->first()->balance];

        try {
            $wallets->payCampaignRewardFromHold($agent, $participation->id, '400.00', 'Campaign reward');
            $this->fail('The second payout for the same participation should be refused.');
        } catch (\Throwable) {
        }

        $this->assertSame($before, [(string) $platform->fresh()->balance, (string) $platform->fresh()->locked_balance, (string) $agent->wallet()->first()->balance]);
        $this->assertEquals(400.0, (float) $agent->wallet()->first()->balance);
    }

    public function test_sub_cent_rewards_are_held_and_paid_at_four_decimals(): void
    {
        $this->fundPlatformWallet('100.00');
        $agent = $this->agent(self::AGENT_CHANNEL);
        $campaign = $this->bundledCampaign(['quantity' => 4, 'locked_agent_reward' => '0.0025']);
        $budgets = app(CampaignBudgetService::class);

        $budget = $budgets->holdForCampaign($campaign);
        $this->assertSame(0, bccomp('0.01', (string) $budget->funded_amount, 4));

        $participation = app(CampaignParticipationService::class)->start($agent, $campaign);
        $this->assertSame('0.0025', (string) $participation->reward_amount);

        $budgets->payReward($participation, $campaign->fresh(), $agent, (string) $participation->reward_amount);

        $this->assertSame('0.0025', (string) $agent->wallet()->first()->balance);
        $this->assertSame('0.0025', (string) $budget->fresh()->paid_amount);
    }

    public function test_backfill_refuses_when_the_platform_wallet_cannot_cover_it(): void
    {
        $this->fundPlatformWallet('100.00');
        $campaign = $this->bundledCampaign(['quantity' => 5, 'locked_agent_reward' => 100]);

        $this->artisan('campaigns:backfill-budgets')->assertFailed();

        $this->assertFalse(CampaignBudget::query()->where('campaign_id', $campaign->id)->exists());
    }

    public function test_youtube_api_failures_never_pay_and_leave_steps_for_review(): void
    {
        $this->fakeYoutubeApi();
        $this->fundPlatformWallet();

        foreach (['quota', 'server', 'timeout', 'malformed', null] as $i => $failure) {
            $channel = 'UCfailuremode0000000000'.$i;
            $agent = $this->agent($channel);
            $campaign = $this->bundledCampaign();
            app(CampaignBudgetService::class)->holdForCampaign($campaign);
            $participation = app(CampaignParticipationService::class)->start($agent, $campaign);

            // null: the API answers, but the subscription is private and the comment is held for review.
            $this->apiFailure = $failure;
            $this->apiSubscribed = false;
            $this->apiComment = null;
            $this->completeAllSteps($participation, $agent, 'UgxFailureMode'.$i);
            $this->apiFailure = null;

            $label = $failure ?? 'not found';
            $this->assertSame(CampaignParticipation::STATUS_UNDER_REVIEW, $participation->fresh()->status, $label);
            $this->assertSame(CampaignParticipationTask::STATUS_NEEDS_REVIEW, $participation->steps()->where('task', 'subscribe')->value('status'), $label);
            $this->assertSame(CampaignParticipationTask::STATUS_NEEDS_REVIEW, $participation->steps()->where('task', 'comment')->value('status'), $label);
            $this->assertEquals(0.0, (float) $agent->wallet()->first()->balance, $label);
        }
    }

    public function test_other_agents_and_creators_cannot_act_on_a_task(): void
    {
        $owner = $this->agent(self::AGENT_CHANNEL);
        $participation = app(CampaignParticipationService::class)->start($owner, $this->bundledCampaign());
        $intruder = $this->agent();

        $this->actingAs($intruder)->post(route('agent.tasks.steps.save', [$participation, 'subscribe']))->assertForbidden();
        $this->actingAs($intruder)->post(route('agent.tasks.proceed', $participation))->assertForbidden();
        $this->actingAs($intruder)->post(route('agent.tasks.submit-task', $participation))->assertForbidden();
        $this->actingAs($intruder)->post(route('agent.tasks.claim-watch', $participation), ['token' => 'x'])->assertForbidden();

        $creator = User::factory()->creator()->create();
        $this->actingAs($creator)->get(route('admin.verifications.show', $participation))->assertForbidden();
        $this->actingAs($creator)->post(route('admin.verifications.approve', $participation))->assertForbidden();
        $this->actingAs($creator)->post(route('admin.campaigns.participations.release', [$participation->campaign_id, $participation]))->assertForbidden();

        $this->assertSame(CampaignParticipation::STATUS_STARTED, $participation->fresh()->status);
    }
}
