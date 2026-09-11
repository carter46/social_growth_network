<?php

namespace App\Services\Campaigns;

use App\Enums\TransactionType;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\User;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CampaignParticipationService
{
    public function __construct(
        private WalletService $walletService,
    ) {}

    public function start(User $agent, Campaign $campaign): CampaignParticipation
    {
        $agent->loadMissing('wallet');
        if ((float) $campaign->locked_agent_reward > 0 && ! $agent->wallet) {
            throw new InvalidArgumentException('Create a wallet before joining paid campaigns.');
        }

        return DB::transaction(function () use ($agent, $campaign) {
            $campaign = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if (! $campaign->isOpenForAgents()) {
                throw new InvalidArgumentException('This campaign is not open for agents.');
            }

            if ($campaign->availableStartSlots() < 1) {
                throw new InvalidArgumentException('This campaign has no remaining slots.');
            }

            $existing = CampaignParticipation::query()
                ->where('campaign_id', $campaign->id)
                ->where('agent_id', $agent->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // No retake — including after rejection.
                throw new InvalidArgumentException('You already joined this campaign.');
            }

            return CampaignParticipation::query()->create([
                'campaign_id' => $campaign->id,
                'agent_id' => $agent->id,
                'status' => CampaignParticipation::STATUS_STARTED,
                'started_at' => now(),
                'reward_amount' => $campaign->locked_agent_reward,
            ]);
        });
    }

    /**
     * @param  array{proof_url?: string|null, proof_notes?: string|null}  $data
     */
    public function submit(CampaignParticipation $participation, array $data): CampaignParticipation
    {
        return DB::transaction(function () use ($participation, $data) {
            $participation = CampaignParticipation::query()
                ->whereKey($participation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($participation->status !== CampaignParticipation::STATUS_STARTED) {
                throw new InvalidArgumentException('Participation cannot be submitted in status '.$participation->status);
            }

            $participation->update([
                'status' => CampaignParticipation::STATUS_SUBMITTED,
                'proof_url' => $data['proof_url'] ?? $participation->proof_url,
                'proof_notes' => $data['proof_notes'] ?? $participation->proof_notes,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ]);

            return $participation->fresh();
        });
    }

    public function approve(CampaignParticipation $participation, ?User $reviewer = null, bool $allowStarted = false): CampaignParticipation
    {
        return DB::transaction(function () use ($participation, $reviewer, $allowStarted) {
            $participation = CampaignParticipation::query()
                ->whereKey($participation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($participation->status, [
                CampaignParticipation::STATUS_APPROVED,
                CampaignParticipation::STATUS_PAID,
            ], true)) {
                return $participation;
            }

            $allowed = [
                CampaignParticipation::STATUS_SUBMITTED,
                CampaignParticipation::STATUS_UNDER_REVIEW,
                CampaignParticipation::STATUS_VERIFYING,
            ];
            if ($allowStarted) {
                $allowed[] = CampaignParticipation::STATUS_STARTED;
            }

            if (! in_array($participation->status, $allowed, true)) {
                throw new InvalidArgumentException('Participation cannot be approved in status '.$participation->status);
            }

            $campaign = Campaign::query()->whereKey($participation->campaign_id)->lockForUpdate()->firstOrFail();

            if ($campaign->remainingSlots() < 1) {
                throw new InvalidArgumentException('Campaign has no remaining slots.');
            }

            $agent = User::query()->with('wallet')->findOrFail($participation->agent_id);
            // Financial integrity: always pay the participation snapshot, never product price.
            $amount = (float) $participation->reward_amount;

            if ($amount > 0) {
                if (! $agent->wallet) {
                    throw new InvalidArgumentException('Agent has no wallet to credit.');
                }
                $this->walletService->creditReward(
                    $agent,
                    $amount,
                    'Campaign reward: '.$campaign->title,
                    TransactionType::CampaignReward->value,
                );
            }

            $completedCount = (int) $campaign->completed_count + 1;
            $campaign->completed_count = $completedCount;
            if ($completedCount >= (int) $campaign->quantity) {
                $campaign->status = Campaign::STATUS_COMPLETED;
            }
            if ((int) $campaign->verification_locked_participation_id === (int) $participation->id) {
                $campaign->verification_locked_participation_id = null;
            }
            $campaign->save();

            $participation->update([
                'status' => CampaignParticipation::STATUS_PAID,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer?->id,
                'rejection_reason' => null,
                'paid_at' => now(),
            ]);

            return $participation->fresh();
        });
    }

    public function reject(CampaignParticipation $participation, ?User $reviewer, string $reason): CampaignParticipation
    {
        return DB::transaction(function () use ($participation, $reviewer, $reason) {
            $participation = CampaignParticipation::query()
                ->whereKey($participation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($participation->status, [
                CampaignParticipation::STATUS_APPROVED,
                CampaignParticipation::STATUS_PAID,
            ], true)) {
                throw new InvalidArgumentException('Approved or paid participations cannot be rejected.');
            }

            if (! in_array($participation->status, [
                CampaignParticipation::STATUS_SUBMITTED,
                CampaignParticipation::STATUS_UNDER_REVIEW,
                CampaignParticipation::STATUS_VERIFYING,
            ], true)) {
                throw new InvalidArgumentException('Participation cannot be rejected in status '.$participation->status);
            }

            $campaign = Campaign::query()->whereKey($participation->campaign_id)->lockForUpdate()->firstOrFail();
            if ((int) $campaign->verification_locked_participation_id === (int) $participation->id) {
                $campaign->verification_locked_participation_id = null;
                $campaign->save();
            }

            $participation->update([
                'status' => CampaignParticipation::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer?->id,
                'rejection_reason' => $reason,
            ]);

            return $participation->fresh();
        });
    }
}
