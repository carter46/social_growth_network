<?php

namespace Tests\Feature;

use App\Enums\CampaignTask;
use App\Models\AgentYoutubeAction;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformProduct;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Campaigns\AgentEligibilityService;
use App\Services\Campaigns\CampaignBriefService;
use App\Services\Campaigns\CampaignBudgetService;
use App\Services\Campaigns\CampaignFulfillmentService;
use App\Services\Campaigns\CampaignParticipationService;
use App\Support\ProductTaskProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class TaskTargetFulfilmentTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'youtube-subscribers-watch-comments';

    private const VIDEO = 'dQw4w9WgXcQ';

    private const CHANNEL = 'UCcreatorchannel000000001';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('escrows')) {
            Schema::create('escrows', fn (Blueprint $table) => $table->id());
        }

        Artisan::call('catalog:backfill-hierarchy');
        $this->seed(\Database\Seeders\PlatformCatalogSeeder::class);
        Artisan::call('catalog:backfill-hierarchy');

        $wallet = app(WalletService::class)->getPlatformWallet();
        $wallet->forceFill(['balance' => '100000.00', 'locked_balance' => 0])->save();
    }

    private function agent(): User
    {
        $agent = User::factory()->agent()->kycApproved()->create();
        Wallet::factory()->create(['user_id' => $agent->id, 'balance' => 0, 'locked_balance' => 0]);
        $this->linkVerifiedYoutubeChannel($agent);

        return $agent;
    }

    /** @param  array<string, int>|null  $targets */
    private function campaign(?array $targets): Campaign
    {
        $campaign = Campaign::query()->create([
            'creator_id' => User::factory()->creator()->create()->id,
            'platform_product_id' => null,
            'title' => 'Subscribe, watch and comment',
            'target_url' => 'https://www.youtube.com/watch?v='.self::VIDEO,
            'engagement_metric' => ProductTaskProfile::forSlug(self::SLUG)->legacyMetric()?->value,
            'quantity' => $targets ? max($targets) : 3,
            'completed_count' => 0,
            'locked_creator_price' => 100,
            'locked_agent_reward' => 40,
            'status' => Campaign::STATUS_ACTIVE,
            'estimated_minutes' => 2,
            'completion_minutes' => 30,
            'meta' => ['platform' => 'youtube', 'product_slug' => self::SLUG],
            'required_tasks' => ['subscribe', 'watch', 'comment'],
            'task_targets' => $targets,
            'channel_url' => 'https://www.youtube.com/channel/'.self::CHANNEL,
            'channel_id' => self::CHANNEL,
            'video_id' => self::VIDEO,
            'comment_instructions' => 'Say what you liked about the video.',
        ]);
        app(CampaignBudgetService::class)->holdForCampaign($campaign);

        return $campaign->fresh();
    }

    /** @return list<string> */
    private function stepTasks(CampaignParticipation $participation): array
    {
        return $participation->steps()->get()
            ->map(fn ($step) => $step->task instanceof CampaignTask ? $step->task->value : (string) $step->task)
            ->all();
    }

    public function test_paid_order_with_task_quantities_creates_targets_and_one_reward_per_agent(): void
    {
        $product = PlatformProduct::query()->where('slug', self::SLUG)->firstOrFail();
        $product->forceFill(['estimated_minutes' => 2, 'completion_minutes' => 30, 'agent_reward_percent' => 40])->save();
        $snapshot = app(CampaignBriefService::class)->taskSnapshot($product->fresh());

        $order = Order::factory()->create([
            'user_id' => User::factory()->creator()->create()->id,
            'amount' => 600,
            'total_amount' => 600,
            'status' => 'paid',
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'platform_product',
            'item_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 100,
            'line_total' => 600,
            'platform_product_variant_id' => $product->pricingVariant()?->id,
            'options' => array_merge($snapshot, [
                'pricing_mode' => 'per_unit',
                'engagement_quantity' => 3,
                'target_url' => 'https://www.youtube.com/watch?v='.self::VIDEO,
                'video_id' => self::VIDEO,
                'channel_url' => 'https://www.youtube.com/channel/'.self::CHANNEL,
                'channel_id' => self::CHANNEL,
                'comment_instructions' => 'Say what you liked.',
                'unit_price' => '100.0000',
                'main_task' => 'subscribe',
                'task_quantities' => ['subscribe' => 3, 'watch' => 2, 'comment' => 1],
            ]),
        ]);

        app(CampaignFulfillmentService::class)->createFromPaidOrder($order->fresh('items'));

        $campaign = Campaign::query()->where('order_item_id', $item->id)->firstOrFail();
        $this->assertSame(['subscribe' => 3, 'watch' => 2, 'comment' => 1], $campaign->task_targets);
        $this->assertSame(3, (int) $campaign->quantity);
        $this->assertSame(40.0, (float) $campaign->locked_agent_reward);
        $this->assertTrue($campaign->usesTaskTargets());
    }

    public function test_each_new_agent_takes_every_open_step_until_the_largest_target_is_reserved(): void
    {
        $campaign = $this->campaign(['subscribe' => 3, 'watch' => 2, 'comment' => 1]);
        $participations = app(CampaignParticipationService::class);

        $first = $participations->start($this->agent(), $campaign);
        $this->assertSame(['subscribe', 'watch', 'comment'], $this->stepTasks($first));

        $second = $participations->start($this->agent(), $campaign->fresh());
        $this->assertSame(['subscribe', 'watch'], $this->stepTasks($second));

        $third = $participations->start($this->agent(), $campaign->fresh());
        $this->assertSame(['subscribe'], $this->stepTasks($third));

        $this->assertSame([], $campaign->fresh()->openTasks());
        $this->assertFalse($campaign->fresh()->isOpenForAgents());

        $this->expectException(InvalidArgumentException::class);
        $participations->start($this->agent(), $campaign->fresh());
    }

    public function test_step_progress_counts_reserved_steps_per_service(): void
    {
        $campaign = $this->campaign(['subscribe' => 2, 'watch' => 2, 'comment' => 1]);
        app(CampaignParticipationService::class)->start($this->agent(), $campaign);

        $progress = collect($campaign->fresh()->stepProgress())->keyBy(fn ($row) => $row['task']->value);

        $this->assertSame(2, $progress['subscribe']['target']);
        $this->assertSame(1, $progress['comment']['in_progress']);
        $this->assertSame(0, $progress['comment']['delivered']);
        $this->assertSame(
            [CampaignTask::Subscribe, CampaignTask::Watch],
            $campaign->fresh()->openTasks(),
        );
    }

    public function test_previous_subscriber_can_join_once_the_subscribe_step_is_full(): void
    {
        $campaign = $this->campaign(['subscribe' => 1, 'watch' => 3, 'comment' => 1]);
        $subscriber = $this->agent();
        AgentYoutubeAction::query()->create([
            'user_id' => $subscriber->id,
            'action' => AgentYoutubeAction::ACTION_SUBSCRIBE,
            'target_channel_id' => self::CHANNEL,
            'evidence' => 'api_verified',
            'status' => AgentYoutubeAction::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
        $eligibility = app(AgentEligibilityService::class);

        $this->assertFalse($eligibility->isEligible($subscriber, $campaign));

        app(CampaignParticipationService::class)->start($this->agent(), $campaign);

        $this->assertTrue($eligibility->isEligible($subscriber, $campaign->fresh()));
        $joined = app(CampaignParticipationService::class)->start($subscriber, $campaign->fresh());
        $this->assertSame(['watch'], $this->stepTasks($joined));
    }

    public function test_campaigns_without_targets_keep_every_step_for_every_agent(): void
    {
        $campaign = $this->campaign(null);
        $participations = app(CampaignParticipationService::class);

        $this->assertFalse($campaign->usesTaskTargets());
        $this->assertSame(['subscribe', 'watch', 'comment'], $this->stepTasks($participations->start($this->agent(), $campaign)));
        $this->assertSame(['subscribe', 'watch', 'comment'], $this->stepTasks($participations->start($this->agent(), $campaign->fresh())));
    }
}
