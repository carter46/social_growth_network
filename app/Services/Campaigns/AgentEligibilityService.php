<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignTask;
use App\Models\AgentYoutubeAction;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use App\Services\Engagement\TargetUrlValidator;
use App\Services\Engagement\YoutubeChannelApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Hides campaigns an agent can't complete because YouTube counts the action only once:
 * a recorded (or in-flight) subscribe to the same channel, or like of the exact same video.
 * Watch and comment are never conflicts.
 */
class AgentEligibilityService
{
    public const NOT_AVAILABLE = "This task isn't available for your account.";

    /** Participation statuses that still count as "this agent did / is doing it". */
    private const BLOCKING_STATUSES = [
        CampaignParticipation::STATUS_STARTED,
        CampaignParticipation::STATUS_SUBMITTED,
        CampaignParticipation::STATUS_UNDER_REVIEW,
        CampaignParticipation::STATUS_VERIFYING,
        CampaignParticipation::STATUS_APPROVED,
        CampaignParticipation::STATUS_PAID,
    ];

    public function __construct(
        private YoutubeChannelApi $api,
        private YoutubeIdentityService $identities,
    ) {}

    /** Marketplace filter: no API calls, database records only. */
    public function applyToQuery(Builder $query, User $agent): Builder
    {
        if (! Schema::hasTable('agent_youtube_actions') || ! Schema::hasColumn('campaigns', 'channel_id')) {
            return $query;
        }

        $agentId = (int) $agent->id;
        $hasTargets = Schema::hasColumn('campaigns', 'task_targets');
        [$subscribeOpenSql, $subscribeOpenBindings] = $hasTargets ? $this->subscribeOpenSql($query) : ['1 = 1', []];

        return $query
            ->whereNotExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                ->from('agent_youtube_actions as a')
                ->leftJoin('campaigns as ac', 'ac.id', '=', 'a.campaign_id')
                ->where('a.user_id', $agentId)
                ->where('a.action', AgentYoutubeAction::ACTION_SUBSCRIBE)
                ->where('a.status', AgentYoutubeAction::STATUS_VERIFIED)
                ->where('campaigns.engagement_metric', 'subscribers')
                ->whereRaw($subscribeOpenSql, $subscribeOpenBindings)
                ->where(fn ($w) => $w->whereColumn('a.target_channel_id', 'campaigns.channel_id')
                    ->orWhere(fn ($legacy) => $legacy->whereNull('a.target_channel_id')
                        ->whereColumn('ac.channel_id', 'campaigns.channel_id'))))
            ->whereNotExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                ->from('campaign_participations as p')
                ->join('campaigns as pc', 'pc.id', '=', 'p.campaign_id')
                ->where('p.agent_id', $agentId)
                ->whereColumn('p.campaign_id', '!=', 'campaigns.id')
                ->whereIn('p.status', self::BLOCKING_STATUSES)
                ->where('pc.engagement_metric', 'subscribers')
                ->where('campaigns.engagement_metric', 'subscribers')
                ->whereColumn('pc.channel_id', 'campaigns.channel_id')
                ->whereRaw($subscribeOpenSql, $subscribeOpenBindings)
                ->when($hasTargets, fn ($w) => $w->where(fn ($held) => $held->whereNull('pc.task_targets')
                    ->orWhereExists(fn (QueryBuilder $step) => $step->selectRaw('1')
                        ->from('campaign_participation_tasks as pst')
                        ->whereColumn('pst.participation_id', 'p.id')
                        ->where('pst.task', CampaignTask::Subscribe->value)))))
            ->whereNotExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                ->from('agent_youtube_actions as a')
                ->where('a.user_id', $agentId)
                ->where('a.action', AgentYoutubeAction::ACTION_LIKE)
                ->where('a.status', AgentYoutubeAction::STATUS_VERIFIED)
                ->where('campaigns.engagement_metric', 'likes')
                ->whereColumn('a.target_video_id', 'campaigns.video_id'))
            ->whereNotExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                ->from('campaign_participations as p')
                ->join('campaigns as pc', 'pc.id', '=', 'p.campaign_id')
                ->where('p.agent_id', $agentId)
                ->whereColumn('p.campaign_id', '!=', 'campaigns.id')
                ->whereIn('p.status', self::BLOCKING_STATUSES)
                ->where('pc.engagement_metric', 'likes')
                ->where('campaigns.engagement_metric', 'likes')
                ->whereColumn('pc.video_id', 'campaigns.video_id'));
    }

    public function isEligible(User $agent, Campaign $campaign): bool
    {
        return $this->conflictReason($agent, $campaign) === null;
    }

    /** Null when eligible; otherwise an internal reason (never shown to the agent). */
    public function conflictReason(User $agent, Campaign $campaign): ?string
    {
        $visible = $this->applyToQuery(Campaign::query()->whereKey($campaign->id), $agent)->exists();
        if (! $visible) {
            return $campaign->engagement_metric === 'likes' ? 'previous_like' : 'previous_subscribe';
        }

        if ($this->hasSubscribe($campaign) && $this->legacyChannelKeyTaken($agent, $campaign)) {
            return 'previous_subscribe_link';
        }

        return null;
    }

    /**
     * Public API check when the agent opens or starts a campaign (never while listing).
     * A confirmed existing subscription is recorded, which hides the campaign from then on.
     */
    public function precheckWithApi(User $agent, Campaign $campaign): ?string
    {
        if (! $this->hasSubscribe($campaign) || ! $campaign->channel_id) {
            return null;
        }

        $identity = $this->identities->verified($agent);
        if (! $identity?->channel_id) {
            return null;
        }

        // Each page view would otherwise spend YouTube API quota; a "not subscribed" answer is reused for 10 minutes.
        $cacheKey = 'yt-precheck:'.$identity->channel_id.':'.$campaign->channel_id;
        if (Cache::has($cacheKey)) {
            return null;
        }

        if ($this->api->isSubscribed($identity->channel_id, $campaign->channel_id) !== true) {
            Cache::put($cacheKey, true, now()->addMinutes(10));

            return null;
        }

        AgentYoutubeAction::query()->create([
            'user_id' => $agent->id,
            'agent_channel_id' => $identity->channel_id,
            'action' => AgentYoutubeAction::ACTION_SUBSCRIBE,
            'target_channel_id' => $campaign->channel_id,
            'evidence' => 'api_precheck',
            'status' => AgentYoutubeAction::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        return 'already_subscribed_public';
    }

    /** Throws (and audit-logs) when the agent may not take this campaign. */
    public function assertEligible(User $agent, Campaign $campaign): void
    {
        $reason = $this->conflictReason($agent, $campaign);
        if ($reason === null) {
            return;
        }

        $this->logRefusal($agent, $campaign, $reason);

        throw new InvalidArgumentException(self::NOT_AVAILABLE);
    }

    public function logRefusal(User $agent, Campaign $campaign, string $reason): void
    {
        try {
            app(AuditLogService::class)->log(null, 'campaign.eligibility_refused', $campaign, null, [
                'agent_id' => $agent->id,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The campaign asks new agents to subscribe (its subscribe target is not yet full). */
    private function hasSubscribe(Campaign $campaign): bool
    {
        if (! $campaign->taskProfile()->has(CampaignTask::Subscribe)) {
            return false;
        }

        return ! $campaign->usesTaskTargets() || in_array(CampaignTask::Subscribe, $campaign->openTasks(), true);
    }

    /**
     * SQL (correlated to "campaigns") that is true while new agents would be given the subscribe step:
     * campaigns without targets always, otherwise until held subscribe steps reach the target.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function subscribeOpenSql(Builder $query): array
    {
        $target = $query->getQuery()->getGrammar()->wrap('campaigns.task_targets->'.CampaignTask::Subscribe->value);
        $placeholders = implode(', ', array_fill(0, count(Campaign::SLOT_STATUSES), '?'));

        $sql = '(campaigns.task_targets IS NULL OR ('
            .'SELECT COUNT(*) FROM campaign_participation_tasks st'
            .' INNER JOIN campaign_participations sp ON sp.id = st.participation_id'
            .' WHERE st.campaign_id = campaigns.id AND st.task = ? AND sp.status IN ('.$placeholders.')'
            .') < '.$target.')';

        return [$sql, array_merge([CampaignTask::Subscribe->value], Campaign::SLOT_STATUSES)];
    }

    /** Older campaigns without a canonical channel ID: compare channel links instead. */
    private function legacyChannelKeyTaken(User $agent, Campaign $campaign): bool
    {
        $link = $campaign->channel_url ?: $campaign->target_url;
        $channelKey = TargetUrlValidator::youtubeChannelKey($link);
        if ($channelKey === null) {
            return false;
        }

        $others = CampaignParticipation::query()
            ->where('campaign_participations.agent_id', $agent->id)
            ->where('campaign_participations.campaign_id', '!=', $campaign->id)
            ->whereIn('campaign_participations.status', self::BLOCKING_STATUSES)
            ->join('campaigns', 'campaigns.id', '=', 'campaign_participations.campaign_id')
            ->where('campaigns.engagement_metric', 'subscribers')
            ->get(['campaigns.target_url', 'campaigns.channel_url']);

        foreach ($others as $row) {
            if (TargetUrlValidator::youtubeChannelKey($row->channel_url ?: $row->target_url) === $channelKey) {
                return true;
            }
        }

        return false;
    }
}
