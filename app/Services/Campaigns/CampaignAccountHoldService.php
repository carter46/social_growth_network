<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a creator account is suspended or moved to trash, their open campaigns are
 * held (status=suspended) so they leave the agent marketplace. Restoring the
 * account puts those campaigns back to the status they had before the hold.
 */
class CampaignAccountHoldService
{
    public const REASON_SUSPENDED = 'account_suspended';

    public const REASON_TRASHED = 'account_trashed';

    /** @var list<string> */
    private const HOLDABLE = [
        Campaign::STATUS_ACTIVE,
        Campaign::STATUS_PAUSED,
        Campaign::STATUS_PENDING_REVIEW,
    ];

    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function holdForCreator(User $creator, string $reason, ?int $actorId = null, ?string $ip = null): int
    {
        if (! $this->ready() || ! $creator->isCreator()) {
            return 0;
        }

        $held = 0;

        DB::transaction(function () use ($creator, $reason, $actorId, $ip, &$held) {
            $campaigns = Campaign::query()
                ->where('creator_id', $creator->id)
                ->whereIn('status', self::HOLDABLE)
                ->lockForUpdate()
                ->get();

            foreach ($campaigns as $campaign) {
                $from = $campaign->status;
                $campaign->forceFill([
                    'status_before_hold' => $from,
                    'account_hold_reason' => $reason,
                    'status' => Campaign::STATUS_SUSPENDED,
                ])->save();

                $this->audit->log($actorId, 'campaign.account_held', $campaign, [
                    'status' => $from,
                ], [
                    'status' => Campaign::STATUS_SUSPENDED,
                    'reason' => $reason,
                    'creator_id' => $creator->id,
                ], $ip);

                $held++;
            }
        });

        return $held;
    }

    public function releaseForCreator(User $creator, ?int $actorId = null, ?string $ip = null): int
    {
        if (! $this->ready()) {
            return 0;
        }

        $released = 0;

        DB::transaction(function () use ($creator, $actorId, $ip, &$released) {
            $campaigns = Campaign::query()
                ->where('creator_id', $creator->id)
                ->where('status', Campaign::STATUS_SUSPENDED)
                ->whereNotNull('status_before_hold')
                ->whereNotNull('account_hold_reason')
                ->lockForUpdate()
                ->get();

            foreach ($campaigns as $campaign) {
                $restoreTo = (string) $campaign->status_before_hold;
                if (! in_array($restoreTo, self::HOLDABLE, true)) {
                    $restoreTo = Campaign::STATUS_PAUSED;
                }

                // Do not reopen a campaign that has no remaining units.
                if ($restoreTo === Campaign::STATUS_ACTIVE && $campaign->remainingSlots() < 1) {
                    $restoreTo = Campaign::STATUS_COMPLETED;
                }

                $from = $campaign->status;
                $campaign->forceFill([
                    'status' => $restoreTo,
                    'status_before_hold' => null,
                    'account_hold_reason' => null,
                ])->save();

                $this->audit->log($actorId, 'campaign.account_hold_released', $campaign, [
                    'status' => $from,
                ], [
                    'status' => $restoreTo,
                    'creator_id' => $creator->id,
                ], $ip);

                $released++;
            }
        });

        return $released;
    }

    private function ready(): bool
    {
        return Schema::hasColumn('campaigns', 'status_before_hold')
            && Schema::hasColumn('campaigns', 'account_hold_reason');
    }
}
