<?php

namespace App\Services\Users;

use App\Models\AgentYoutubeIdentity;
use App\Models\Campaign;
use App\Models\User;
use App\Services\Campaigns\CampaignBudgetService;
use Illuminate\Support\Facades\Schema;

/**
 * When an account is permanently deleted (anonymized), retire marketplace data so
 * campaigns/YouTube rows and held budgets do not keep looking "live" in admin.
 */
class AnonymizedAccountCleanup
{
    public function __construct(
        private CampaignBudgetService $budgets,
    ) {}

    public function retireOwnedData(User $user): void
    {
        if (! $user->isAnonymized()) {
            return;
        }

        $this->releaseYoutubeChannels($user->id);
        $this->cancelOpenCampaigns($user->id);
    }

    /** One-shot / cron: clean every anonymized tombstone that still owns live data. */
    public function retireAllAnonymized(): int
    {
        $n = 0;

        User::query()
            ->whereNotNull('anonymized_at')
            ->orderBy('id')
            ->chunkById(50, function ($users) use (&$n) {
                foreach ($users as $user) {
                    $this->retireOwnedData($user);
                    $n++;
                }
            });

        return $n;
    }

    private function releaseYoutubeChannels(int $userId): void
    {
        if (! Schema::hasTable('agent_youtube_identities')) {
            return;
        }

        AgentYoutubeIdentity::query()
            ->where('user_id', $userId)
            ->whereIn('status', [
                AgentYoutubeIdentity::STATUS_VERIFIED,
                AgentYoutubeIdentity::STATUS_PENDING,
                AgentYoutubeIdentity::STATUS_FAILED,
                AgentYoutubeIdentity::STATUS_CHANGE_REQUESTED,
            ])
            ->update([
                'status' => AgentYoutubeIdentity::STATUS_REPLACED,
                'active_channel_id' => null,
                'oauth_token' => null,
                'reclaim_from_user_id' => null,
                'failure_reason' => 'Account permanently deleted.',
                'updated_at' => now(),
            ]);
    }

    private function cancelOpenCampaigns(int $userId): void
    {
        if (! Schema::hasTable('campaigns')) {
            return;
        }

        $open = [
            Campaign::STATUS_DRAFT,
            Campaign::STATUS_PENDING_REVIEW,
            Campaign::STATUS_ACTIVE,
            Campaign::STATUS_PAUSED,
            Campaign::STATUS_SUSPENDED,
        ];

        Campaign::query()
            ->where('creator_id', $userId)
            ->whereIn('status', $open)
            ->orderBy('id')
            ->each(function (Campaign $campaign) {
                $campaign->forceFill([
                    'status' => Campaign::STATUS_CANCELLED,
                    'status_before_hold' => null,
                    'account_hold_reason' => null,
                ])->save();

                try {
                    $this->budgets->refundUndelivered($campaign->fresh());
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }
}
