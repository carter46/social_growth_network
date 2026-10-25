<?php

namespace App\Support;

use App\Models\AgentSocialFollowSubmission;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\KycSubmission;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Schema;

/**
 * Waiting counts for the admin sidebar. Zero means the menu shows no number.
 */
class AdminQueueCounts
{
    /**
     * @return array<string, int> route name => waiting count
     */
    public static function all(): array
    {
        $counts = [];

        try {
            if (Schema::hasTable('campaign_participations')) {
                $counts['admin.verifications'] = CampaignParticipation::query()
                    ->whereIn('status', [
                        CampaignParticipation::STATUS_SUBMITTED,
                        CampaignParticipation::STATUS_UNDER_REVIEW,
                        CampaignParticipation::STATUS_VERIFYING,
                    ])
                    ->count();
            }

            if (Schema::hasTable('agent_social_follow_submissions')) {
                $counts['admin.social-follows'] = AgentSocialFollowSubmission::query()
                    ->where('status', AgentSocialFollowSubmission::STATUS_SUBMITTED)
                    ->count();
            }

            if (Schema::hasTable('kyc_submissions')) {
                $counts['admin.kyc'] = KycSubmission::query()->where('status', 'pending')->count();
            }

            if (Schema::hasTable('support_tickets')) {
                $counts['admin.tickets'] = SupportTicket::query()
                    ->whereIn('status', ['open', 'pending', 'awaiting_user'])
                    ->count();
            }

            if (Schema::hasTable('campaigns')) {
                $counts['admin.campaigns'] = Campaign::query()
                    ->where('status', Campaign::STATUS_PENDING_REVIEW)
                    ->count();
            }
        } catch (\Throwable) {
            return [];
        }

        return $counts;
    }
}
