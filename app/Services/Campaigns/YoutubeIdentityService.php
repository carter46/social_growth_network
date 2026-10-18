<?php

namespace App\Services\Campaigns;

use App\Models\AgentYoutubeIdentity;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\SystemSetting;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use App\Services\Engagement\TargetUrlValidator;
use App\Services\Engagement\YoutubeChannelApi;
use App\Services\Engagement\YoutubeOAuthClient;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Links an account to one public YouTube channel: required for agents at their first YouTube task,
 * optional for creators. A channel can be active on only one account across both roles.
 * Path A: Google OAuth (admin toggle). Path B: a code pasted into the channel description,
 * checked now and then every 30 minutes for 24 hours.
 */
class YoutubeIdentityService
{
    public const CHECK_INTERVAL_MINUTES = 30;

    /** Exception code for links that point to no channel, so the UI can offer the "create a channel" help. */
    public const ERROR_NOT_FOUND = 404;

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private YoutubeChannelApi $api,
        private NotificationDispatcher $notifications,
        private YoutubeOAuthClient $oauth,
    ) {}

    public static function enabled(): bool
    {
        return Schema::hasTable('agent_youtube_identities');
    }

    public static function oauthEnabled(): bool
    {
        return SystemSetting::enabled('youtube_oauth_enabled')
            && trim((string) SystemSetting::get('youtube_oauth_client_id', '')) !== ''
            && trim((string) SystemSetting::get('youtube_oauth_client_secret', '')) !== '';
    }

    /** Every YouTube campaign needs a verified channel before the agent can start it. */
    public static function requiredFor(Campaign $campaign): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $platform = $campaign->meta['platform'] ?? null;
        $slug = $campaign->meta['product_slug'] ?? null;

        return $platform === 'youtube'
            || ($platform === null && is_string($slug) && str_starts_with($slug, 'youtube-'))
            || TargetUrlValidator::platformFromUrl($campaign->target_url) === 'youtube';
    }

    /** The agent's current identity row (pending, verified or failed). */
    public function current(User $agent): ?AgentYoutubeIdentity
    {
        if (! self::enabled()) {
            return null;
        }

        return AgentYoutubeIdentity::query()
            ->where('user_id', $agent->id)
            ->whereIn('status', [
                AgentYoutubeIdentity::STATUS_VERIFIED,
                AgentYoutubeIdentity::STATUS_PENDING,
                AgentYoutubeIdentity::STATUS_FAILED,
            ])
            ->orderByRaw("CASE status WHEN 'verified' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->first();
    }

    public function verified(User $agent): ?AgentYoutubeIdentity
    {
        if (! self::enabled()) {
            return null;
        }

        return AgentYoutubeIdentity::query()
            ->where('user_id', $agent->id)
            ->where('status', AgentYoutubeIdentity::STATUS_VERIFIED)
            ->latest('id')
            ->first();
    }

    public function pendingChangeRequest(User $agent): ?AgentYoutubeIdentity
    {
        if (! self::enabled()) {
            return null;
        }

        return AgentYoutubeIdentity::query()
            ->where('user_id', $agent->id)
            ->where('status', AgentYoutubeIdentity::STATUS_CHANGE_REQUESTED)
            ->latest('id')
            ->first();
    }

    /**
     * Channel preview for the popup.
     *
     * @return array{id: ?string, title: ?string, handle: ?string, thumbnail: ?string, url: string, resolved: bool}
     */
    public function preview(string $url): array
    {
        $normalized = TargetUrlValidator::normalizeChannelInput($url);
        $ref = TargetUrlValidator::extractYoutubeChannelRef($normalized);
        if (! $normalized || ! $ref) {
            if (TargetUrlValidator::extractYoutubeVideoId(TargetUrlValidator::normalize($url) ?? '') !== null) {
                throw new InvalidArgumentException('That is a video link. Paste the link to your channel instead, for example https://www.youtube.com/@yourchannel.', self::ERROR_NOT_FOUND);
            }

            throw new InvalidArgumentException('Paste your YouTube channel link, for example https://www.youtube.com/@yourchannel.', self::ERROR_NOT_FOUND);
        }
        if ($ref['type'] === 'custom') {
            throw new InvalidArgumentException('Links like youtube.com/c/name can\'t be checked reliably. Please use your @handle link or your youtube.com/channel/UC... link.');
        }

        if (! $this->api->hasKey()) {
            return [
                'id' => $ref['type'] === 'id' ? $ref['value'] : null,
                'title' => null,
                'handle' => $ref['type'] === 'handle' ? '@'.$ref['value'] : null,
                'thumbnail' => null,
                'url' => $normalized,
                'resolved' => false,
            ];
        }

        $channel = $this->api->resolveChannel($normalized);
        if (! $channel) {
            throw new InvalidArgumentException('We couldn\'t find that channel. Check the link and make sure the channel is public.', self::ERROR_NOT_FOUND);
        }

        return [
            'id' => $channel['id'],
            'title' => $channel['title'],
            'handle' => $channel['handle'],
            'thumbnail' => $channel['thumbnail'],
            'url' => $normalized,
            'resolved' => true,
        ];
    }

    /** Path B: start (or restart) linking with a fresh description code. */
    public function begin(User $agent, string $url): AgentYoutubeIdentity
    {
        $preview = $this->preview($url);

        $identity = DB::transaction(function () use ($agent, $preview) {
            User::query()->whereKey($agent->id)->lockForUpdate()->first();

            if ($this->verified($agent)) {
                throw new InvalidArgumentException($agent->isAgent()
                    ? 'Your channel is already verified. To use a different channel, send a change request.'
                    : 'Your channel is already connected. Disconnect it first to connect a different one.');
            }

            $this->assertChannelFree($agent, $preview['id']);

            AgentYoutubeIdentity::query()
                ->where('user_id', $agent->id)
                ->whereIn('status', [AgentYoutubeIdentity::STATUS_PENDING, AgentYoutubeIdentity::STATUS_FAILED])
                ->update(['status' => AgentYoutubeIdentity::STATUS_REPLACED]);

            return AgentYoutubeIdentity::query()->create([
                'user_id' => $agent->id,
                'channel_id' => $preview['id'],
                'channel_url' => $preview['url'],
                'handle' => $preview['handle'],
                'title' => $preview['title'],
                'thumbnail_url' => $preview['thumbnail'],
                'method' => $preview['resolved'] ? AgentYoutubeIdentity::METHOD_CODE : AgentYoutubeIdentity::METHOD_ADMIN,
                'status' => AgentYoutubeIdentity::STATUS_PENDING,
                'verification_code' => $this->newCode(),
                'code_expires_at' => now()->addHours(AgentYoutubeIdentity::CHECK_WINDOW_HOURS),
            ]);
        });

        if ($identity->method === AgentYoutubeIdentity::METHOD_ADMIN) {
            $this->notifyAdminsManualReview($identity);
        }

        return $identity;
    }

    /** Retry after a failure: a new code and a new 24-hour window. */
    public function retry(User $agent): AgentYoutubeIdentity
    {
        $current = $this->current($agent);
        if (! $current || $current->status !== AgentYoutubeIdentity::STATUS_FAILED) {
            throw new InvalidArgumentException('There is no failed verification to retry.');
        }

        return $this->begin($agent, (string) $current->channel_url);
    }

    /**
     * Look for the code in the channel description. Returns the refreshed identity.
     * Without an API key the row stays pending for admin approval.
     */
    public function check(AgentYoutubeIdentity $identity): AgentYoutubeIdentity
    {
        if ($identity->status !== AgentYoutubeIdentity::STATUS_PENDING) {
            return $identity;
        }

        if (! $this->api->hasKey() || ! $identity->channel_id) {
            return $identity;
        }

        // An expired code never verifies, even if it appears in the description later.
        if ($identity->code_expires_at && now()->greaterThanOrEqualTo($identity->code_expires_at)) {
            return $this->markFailed($identity, 'The code was not found in your channel description within 24 hours.');
        }

        $channel = $this->api->channelById($identity->channel_id);

        $identity->forceFill([
            'last_checked_at' => now(),
            'check_attempts' => (int) $identity->check_attempts + 1,
        ])->save();

        if ($channel && $identity->verification_code
            && str_contains(strtoupper($channel['description']), strtoupper($identity->verification_code))) {
            return $this->markVerified($identity, AgentYoutubeIdentity::METHOD_CODE, null, [
                'title' => $channel['title'] ?: $identity->title,
                'handle' => $channel['handle'] ?: $identity->handle,
                'thumbnail_url' => $channel['thumbnail'] ?: $identity->thumbnail_url,
            ]);
        }

        return $identity->fresh();
    }

    /** Background job: re-check pending codes every 30 minutes and fail them after 24 hours. */
    public function runScheduledChecks(int $limit = 50): int
    {
        if (! self::enabled()) {
            return 0;
        }

        $n = 0;

        AgentYoutubeIdentity::query()
            ->where('status', AgentYoutubeIdentity::STATUS_PENDING)
            ->where('method', AgentYoutubeIdentity::METHOD_CODE)
            ->where(fn ($q) => $q->whereNull('last_checked_at')
                ->orWhere('last_checked_at', '<=', now()->subMinutes(self::CHECK_INTERVAL_MINUTES))
                ->orWhere('code_expires_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (AgentYoutubeIdentity $identity) use (&$n) {
                try {
                    if ($identity->code_expires_at && now()->greaterThanOrEqualTo($identity->code_expires_at) && ! $this->api->hasKey()) {
                        return;
                    }
                    $this->check($identity);
                    $n++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return $n;
    }

    /** Path A: Google confirmed the agent owns this channel. */
    public function linkFromOAuth(User $agent, array $channel, string $token): AgentYoutubeIdentity
    {
        return DB::transaction(function () use ($agent, $channel, $token) {
            User::query()->whereKey($agent->id)->lockForUpdate()->first();

            $verified = $this->verified($agent);
            if ($verified && $verified->channel_id !== $channel['id']) {
                throw new InvalidArgumentException($agent->isAgent()
                    ? 'Your account is already linked to a different channel. Send a change request to switch.'
                    : 'Your account is already connected to a different channel. Disconnect it first to switch.');
            }

            if ($verified) {
                $verified->forceFill(['oauth_token' => $token, 'method' => AgentYoutubeIdentity::METHOD_OAUTH])->save();

                return $verified;
            }

            $this->assertChannelFree($agent, $channel['id']);

            AgentYoutubeIdentity::query()
                ->where('user_id', $agent->id)
                ->whereIn('status', [AgentYoutubeIdentity::STATUS_PENDING, AgentYoutubeIdentity::STATUS_FAILED])
                ->update(['status' => AgentYoutubeIdentity::STATUS_REPLACED]);

            $identity = AgentYoutubeIdentity::query()->create([
                'user_id' => $agent->id,
                'channel_id' => $channel['id'],
                'channel_url' => 'https://www.youtube.com/channel/'.$channel['id'],
                'handle' => $channel['handle'] ?? null,
                'title' => $channel['title'] ?? null,
                'thumbnail_url' => $channel['thumbnail'] ?? null,
                'method' => AgentYoutubeIdentity::METHOD_OAUTH,
                'status' => AgentYoutubeIdentity::STATUS_PENDING,
                'oauth_token' => $token,
            ]);

            return $this->markVerified($identity, AgentYoutubeIdentity::METHOD_OAUTH);
        });
    }

    public function disconnectOAuth(User $agent): void
    {
        $this->revokeStoredTokens($agent);

        AgentYoutubeIdentity::query()
            ->where('user_id', $agent->id)
            ->whereNotNull('oauth_token')
            ->update(['oauth_token' => null]);
    }

    /** Creators only: drop the linked channel (no admin step) so another one can be connected. */
    public function unlink(User $creator): void
    {
        if ($creator->isAgent()) {
            throw new InvalidArgumentException('Agents change channels through a change request.');
        }

        $this->revokeStoredTokens($creator);

        AgentYoutubeIdentity::query()
            ->where('user_id', $creator->id)
            ->whereIn('status', [
                AgentYoutubeIdentity::STATUS_VERIFIED,
                AgentYoutubeIdentity::STATUS_PENDING,
                AgentYoutubeIdentity::STATUS_FAILED,
            ])
            ->update([
                'status' => AgentYoutubeIdentity::STATUS_REPLACED,
                'active_channel_id' => null,
                'oauth_token' => null,
                'updated_at' => now(),
            ]);
    }

    private function revokeStoredTokens(User $user): void
    {
        AgentYoutubeIdentity::query()
            ->where('user_id', $user->id)
            ->whereNotNull('oauth_token')
            ->get()
            ->each(fn (AgentYoutubeIdentity $identity) => $this->oauth->revoke($identity->oauth_token));
    }

    /** Change of channel: needs admin approval; the current verified channel stays active meanwhile. */
    public function requestChange(User $agent, string $url): AgentYoutubeIdentity
    {
        if (! $agent->isAgent()) {
            throw new InvalidArgumentException('Disconnect your current channel to connect a different one.');
        }

        $preview = $this->preview($url);
        $current = $this->verified($agent);
        if (! $current) {
            throw new InvalidArgumentException('You don\'t have a verified channel yet. Link a channel instead.');
        }
        if ($preview['id'] && $preview['id'] === $current->channel_id) {
            throw new InvalidArgumentException('That is already your linked channel.');
        }

        $this->assertChannelFree($agent, $preview['id']);

        AgentYoutubeIdentity::query()
            ->where('user_id', $agent->id)
            ->where('status', AgentYoutubeIdentity::STATUS_CHANGE_REQUESTED)
            ->update(['status' => AgentYoutubeIdentity::STATUS_REJECTED, 'failure_reason' => 'Superseded by a newer change request.']);

        $request = AgentYoutubeIdentity::query()->create([
            'user_id' => $agent->id,
            'channel_id' => $preview['id'],
            'channel_url' => $preview['url'],
            'handle' => $preview['handle'],
            'title' => $preview['title'],
            'thumbnail_url' => $preview['thumbnail'],
            'method' => AgentYoutubeIdentity::METHOD_CODE,
            'status' => AgentYoutubeIdentity::STATUS_CHANGE_REQUESTED,
        ]);

        $this->notifications->notifyAdmins(new NotificationMessage(
            type: 'youtube_identity.change_requested',
            title: 'YouTube channel change request',
            body: 'An agent asked to change their linked YouTube channel.',
            actionUrl: Route::has('admin.youtube-identities.index') ? route('admin.youtube-identities.index') : null,
            meta: ['identity_id' => $request->id, 'user_id' => $agent->id],
        ), ['database']);

        return $request;
    }

    /** Admin approves a change: old row is kept as "replaced", the new channel must prove ownership with a code. */
    public function approveChange(AgentYoutubeIdentity $request, User $admin): AgentYoutubeIdentity
    {
        if ($request->status !== AgentYoutubeIdentity::STATUS_CHANGE_REQUESTED) {
            throw new InvalidArgumentException('This change request is no longer open.');
        }

        return DB::transaction(function () use ($request, $admin) {
            User::query()->whereKey($request->user_id)->lockForUpdate()->first();

            AgentYoutubeIdentity::query()
                ->where('user_id', $request->user_id)
                ->where('id', '!=', $request->id)
                ->whereIn('status', [AgentYoutubeIdentity::STATUS_VERIFIED, AgentYoutubeIdentity::STATUS_PENDING, AgentYoutubeIdentity::STATUS_FAILED])
                ->update(['status' => AgentYoutubeIdentity::STATUS_REPLACED, 'active_channel_id' => null]);

            $request->forceFill([
                'status' => AgentYoutubeIdentity::STATUS_PENDING,
                'method' => $this->api->hasKey() && $request->channel_id ? AgentYoutubeIdentity::METHOD_CODE : AgentYoutubeIdentity::METHOD_ADMIN,
                'verification_code' => $this->newCode(),
                'code_expires_at' => now()->addHours(AgentYoutubeIdentity::CHECK_WINDOW_HOURS),
                'reviewed_by' => $admin->id,
            ])->save();

            $this->audit($admin->id, 'youtube_identity.change_approved', $request);
            $this->warnAboutOpenTasks($request);

            return $request->fresh();
        });
    }

    /** Admin manual approval (no API key, or a reviewed exception). */
    public function adminApprove(AgentYoutubeIdentity $identity, User $admin, ?string $channelId = null): AgentYoutubeIdentity
    {
        if (! in_array($identity->status, [AgentYoutubeIdentity::STATUS_PENDING, AgentYoutubeIdentity::STATUS_FAILED], true)) {
            throw new InvalidArgumentException('Only pending or failed verifications can be approved.');
        }

        $channelId = $channelId ?: $identity->channel_id;
        if (! $channelId || ! preg_match('/^UC[A-Za-z0-9_-]{22}$/', $channelId)) {
            throw new InvalidArgumentException('Enter the channel ID (starts with UC, 24 characters) before approving.');
        }

        $identity->channel_id = $channelId;
        $verified = $this->markVerified($identity, AgentYoutubeIdentity::METHOD_ADMIN, $admin->id);
        $this->audit($admin->id, 'youtube_identity.admin_approved', $verified);

        return $verified;
    }

    public function adminReject(AgentYoutubeIdentity $identity, User $admin, string $reason): AgentYoutubeIdentity
    {
        $wasVerified = $identity->status === AgentYoutubeIdentity::STATUS_VERIFIED;

        $identity->forceFill([
            'status' => AgentYoutubeIdentity::STATUS_REJECTED,
            'active_channel_id' => null,
            'failure_reason' => $reason,
            'reviewed_by' => $admin->id,
        ])->save();

        $this->audit($admin->id, 'youtube_identity.rejected', $identity, ['reason' => $reason]);
        if ($wasVerified) {
            $this->warnAboutOpenTasks($identity);
        }

        return $identity;
    }

    /** Bundled tasks are paid only while the agent has a verified channel, so tell them before it bites. */
    private function warnAboutOpenTasks(AgentYoutubeIdentity $identity): void
    {
        try {
            $open = CampaignParticipation::query()
                ->where('agent_id', $identity->user_id)
                ->whereIn('status', [
                    CampaignParticipation::STATUS_STARTED,
                    CampaignParticipation::STATUS_SUBMITTED,
                    CampaignParticipation::STATUS_UNDER_REVIEW,
                    CampaignParticipation::STATUS_VERIFYING,
                ])
                ->whereHas('campaign', fn ($q) => $q->whereNotNull('required_tasks'))
                ->count();
            if ($open === 0) {
                return;
            }

            $this->notify($identity, 'youtube_identity.open_tasks', 'Your YouTube tasks are on hold',
                trans_choice(
                    '{1} You have 1 YouTube task in progress or awaiting review. It can only be paid once your YouTube channel is verified again, so verify your channel from your dashboard.|[2,*] You have :count YouTube tasks in progress or awaiting review. They can only be paid once your YouTube channel is verified again, so verify your channel from your dashboard.',
                    $open,
                    ['count' => $open],
                ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function markVerified(AgentYoutubeIdentity $identity, string $method, ?int $reviewerId = null, array $extra = []): AgentYoutubeIdentity
    {
        try {
            $identity->forceFill(array_merge($extra, [
                'status' => AgentYoutubeIdentity::STATUS_VERIFIED,
                'active_channel_id' => $identity->channel_id,
                'method' => $method,
                'verified_at' => now(),
                'failure_reason' => null,
                'reviewed_by' => $reviewerId,
            ]))->save();
        } catch (QueryException $e) {
            $identity->refresh();
            $identity->forceFill([
                'status' => AgentYoutubeIdentity::STATUS_REJECTED,
                'active_channel_id' => null,
                'failure_reason' => 'This channel is already linked to another account.',
            ])->save();
            $this->flagDuplicate($identity);

            throw new InvalidArgumentException('This YouTube channel is already linked to another account.');
        }

        $isAgent = (bool) $identity->user()->first()?->isAgent();
        $this->notify($identity, 'youtube_identity.verified', 'YouTube channel verified', $isAgent
            ? 'Your YouTube channel is verified. You can now start YouTube tasks. You may remove the code from your channel description.'
            : 'Your YouTube channel is connected. You may remove the code from your channel description.');

        return $identity->fresh();
    }

    private function markFailed(AgentYoutubeIdentity $identity, string $reason): AgentYoutubeIdentity
    {
        $identity->forceFill([
            'status' => AgentYoutubeIdentity::STATUS_FAILED,
            'failure_reason' => $reason,
        ])->save();

        $this->notify($identity, 'youtube_identity.failed', 'YouTube channel verification failed',
            'We could not find your code in your channel description within 24 hours. Open your dashboard and choose Retry to get a new code.');

        return $identity->fresh();
    }

    private function assertChannelFree(User $agent, ?string $channelId): void
    {
        if (! $channelId) {
            return;
        }

        $taken = AgentYoutubeIdentity::query()
            ->where('active_channel_id', $channelId)
            ->where('user_id', '!=', $agent->id)
            ->exists();

        if ($taken) {
            $this->flagDuplicate(new AgentYoutubeIdentity(['user_id' => $agent->id, 'channel_id' => $channelId]));

            throw new InvalidArgumentException('This YouTube channel is already linked to another account.');
        }
    }

    private function flagDuplicate(AgentYoutubeIdentity $identity): void
    {
        try {
            $this->notifications->notifyAdmins(new NotificationMessage(
                type: 'youtube_identity.duplicate',
                title: 'Duplicate YouTube channel attempt',
                body: 'An account tried to link a YouTube channel that is already linked to another account.',
                actionUrl: Route::has('admin.youtube-identities.index') ? route('admin.youtube-identities.index') : null,
                meta: ['user_id' => $identity->user_id, 'channel_id' => $identity->channel_id],
            ), ['database']);
            $this->audit(null, 'youtube_identity.duplicate_channel', null, ['user_id' => $identity->user_id, 'channel_id' => $identity->channel_id]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyAdminsManualReview(AgentYoutubeIdentity $identity): void
    {
        try {
            $this->notifications->notifyAdmins(new NotificationMessage(
                type: 'youtube_identity.manual_review',
                title: 'YouTube channel needs manual approval',
                body: ($identity->user()->first()?->isAgent() ? 'An agent' : 'A creator').' linked a YouTube channel, but no YouTube API key is set, so it must be approved manually.',
                actionUrl: Route::has('admin.youtube-identities.index') ? route('admin.youtube-identities.index') : null,
                meta: ['identity_id' => $identity->id],
            ), ['database']);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notify(AgentYoutubeIdentity $identity, string $type, string $title, string $body): void
    {
        try {
            $user = $identity->user()->first();
            if (! $user) {
                return;
            }
            $this->notifications->notifyUser($user, new NotificationMessage(
                type: $type,
                title: $title,
                body: $body,
                actionUrl: Route::has($user->isAgent() ? 'agent.youtube-channel.show' : 'dashboard.youtube-channel.show')
                    ? route($user->isAgent() ? 'agent.youtube-channel.show' : 'dashboard.youtube-channel.show')
                    : null,
                meta: ['identity_id' => $identity->id],
                emailSubject: $title,
                dedupeKey: $type.'.'.$identity->id,
            ), ['database', 'mail']);
        } catch (\Throwable $e) {
            Log::warning('youtube_identity.notify_failed', ['identity_id' => $identity->id, 'error' => $e->getMessage()]);
        }
    }

    private function audit(?int $adminId, string $action, ?AgentYoutubeIdentity $identity, array $extra = []): void
    {
        try {
            app(AuditLogService::class)->log($adminId, $action, $identity, null, array_merge([
                'user_id' => $identity?->user_id,
                'channel_id' => $identity?->channel_id,
            ], $extra));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function newCode(): string
    {
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return 'TV-'.$code;
    }
}
