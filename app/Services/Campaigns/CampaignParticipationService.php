<?php

namespace App\Services\Campaigns;

use App\Models\AgentYoutubeIdentity;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\CampaignParticipationTask;
use App\Models\CampaignWatchSession;
use App\Models\ReferralCommission;
use App\Models\User;
use App\Services\Referrals\ReferralCommissionService;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CampaignParticipationService
{
    public function __construct(
        private CampaignBudgetService $budgets,
    ) {}

    public function start(User $agent, Campaign $campaign): CampaignParticipation
    {
        $agent->loadMissing('wallet');
        if ((float) $campaign->locked_agent_reward > 0 && ! $agent->wallet) {
            throw new InvalidArgumentException('Create a wallet before joining paid campaigns.');
        }

        if (YoutubeIdentityService::requiredFor($campaign) && ! app(YoutubeIdentityService::class)->verified($agent)) {
            throw new YoutubeChannelRequiredException;
        }

        $eligibility = app(AgentEligibilityService::class);

        // Public API check outside the locks (network call); a confirmed conflict is recorded.
        if ($reason = $eligibility->precheckWithApi($agent, $campaign)) {
            $eligibility->logRefusal($agent, $campaign, $reason);

            throw new InvalidArgumentException(AgentEligibilityService::NOT_AVAILABLE);
        }

        return DB::transaction(function () use ($agent, $campaign, $eligibility) {
            // Serialises this agent's starts so two tabs can't take conflicting campaigns at once.
            User::query()->whereKey($agent->id)->lockForUpdate()->first();

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

            if ($existing && $existing->status !== CampaignParticipation::STATUS_EXPIRED) {
                throw new InvalidArgumentException('You already joined this campaign.');
            }

            // Under the campaign lock, so two agents can't both take the last place on a step.
            $openTasks = $campaign->openTasks();
            if ($campaign->usesTaskTargets() && $openTasks === []) {
                throw new InvalidArgumentException('This campaign has no remaining slots.');
            }

            $eligibility->assertEligible($agent, $campaign);

            $minutes = (int) ($campaign->completion_minutes ?? 0);
            $now = now();

            if ($existing) {
                $existing->steps()->delete();
                CampaignWatchSession::query()
                    ->where('campaign_participation_id', $existing->id)
                    ->delete();

                $existing->update([
                    'status' => CampaignParticipation::STATUS_STARTED,
                    'proof_url' => null,
                    'proof_notes' => null,
                    'pre_count' => null,
                    'post_count' => null,
                    'verify_attempts' => 0,
                    'verification_started_at' => null,
                    'started_at' => $now,
                    'submitted_at' => null,
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                    'rejection_reason' => null,
                    'paid_at' => null,
                    'reward_amount' => $campaign->locked_agent_reward,
                    'expires_at' => $minutes > 0 ? $now->copy()->addMinutes($minutes) : null,
                    'last_heartbeat_at' => null,
                ]);

                if ($campaign->usesStepFlow()) {
                    app(CampaignTaskFlowService::class)->createSteps($existing, $campaign, $openTasks);
                }

                return $existing->fresh();
            }

            $participation = CampaignParticipation::query()->create([
                'campaign_id' => $campaign->id,
                'agent_id' => $agent->id,
                'status' => CampaignParticipation::STATUS_STARTED,
                'started_at' => $now,
                'expires_at' => $minutes > 0 ? $now->copy()->addMinutes($minutes) : null,
                'reward_amount' => $campaign->locked_agent_reward,
            ]);

            if ($campaign->usesStepFlow()) {
                app(CampaignTaskFlowService::class)->createSteps($participation, $campaign, $openTasks);
            }

            return $participation;
        });
    }

    /**
     * Single-task proof submission (legacy flows). Bundled tasks submit through CampaignTaskFlowService.
     *
     * @param  array{proof_url?: string|null, proof_notes?: string|null}  $data
     */
    public function submit(CampaignParticipation $participation, array $data): CampaignParticipation
    {
        $fresh = CampaignParticipation::query()->find($participation->id);
        if ($fresh && $fresh->isPastDeadline()) {
            app(ParticipationExpiryService::class)->expire($fresh);

            throw new InvalidArgumentException('Time ran out for this task, so it can no longer be submitted.');
        }

        return DB::transaction(function () use ($participation, $data) {
            $participation = CampaignParticipation::query()
                ->whereKey($participation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($participation->status !== CampaignParticipation::STATUS_STARTED) {
                throw new InvalidArgumentException('This task can\'t be submitted right now.');
            }
            if ($participation->isPastDeadline()) {
                throw new InvalidArgumentException('Time ran out for this task, so it can no longer be submitted.');
            }

            $campaign = Campaign::query()->whereKey($participation->campaign_id)->firstOrFail();
            $campaign->assertAllowsAgentWork();
            if ($campaign->usesStepFlow()) {
                throw new InvalidArgumentException('Complete every step, then use Submit task.');
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

    /**
     * Pays the reward once. Re-checked under locks: every step accepted (bundled tasks), not
     * expired, identity not revoked, campaign workable, budget room, unique payout reference.
     */
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

            if ($participation->status === CampaignParticipation::STATUS_EXPIRED) {
                throw new InvalidArgumentException('This task expired, so it cannot be paid.');
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
            $campaign->assertAllowsAgentWork();

            if ($campaign->usesStepFlow()) {
                if ($allowStarted) {
                    throw new InvalidArgumentException('Bundled tasks are paid only after the whole task is submitted and accepted.');
                }
                $this->assertAllStepsAccepted($participation);
                $this->assertIdentityNotRevoked($participation);
            }

            if ($campaign->remainingSlots() < 1) {
                throw new InvalidArgumentException('Campaign has no remaining slots.');
            }

            $agent = User::query()->with('wallet')->findOrFail($participation->agent_id);
            // Financial integrity: always pay the participation snapshot, never product price.
            $amount = Money::round((string) $participation->reward_amount, 4);

            if (bccomp($amount, '0', 4) > 0) {
                if (! $agent->wallet) {
                    throw new InvalidArgumentException('Agent has no wallet to credit.');
                }
                $this->budgets->payReward($participation, $campaign, $agent, $amount);
            }

            $completedCount = (int) $campaign->completed_count + 1;
            $campaign->completed_count = $completedCount;
            $justCompleted = false;
            if ($completedCount >= (int) $campaign->quantity) {
                $campaign->status = Campaign::STATUS_COMPLETED;
                $justCompleted = true;
            }
            if ((int) $campaign->verification_locked_participation_id === (int) $participation->id) {
                $campaign->verification_locked_participation_id = null;
            }
            $campaign->save();

            if ($justCompleted) {
                $this->budgets->settle($campaign);
            }

            $participation->update([
                'status' => CampaignParticipation::STATUS_PAID,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer?->id,
                'rejection_reason' => null,
                'paid_at' => now(),
            ]);

            if (bccomp($amount, '0', 4) > 0 && $agent->referred_by_id) {
                $paid = $participation->fresh();
                DB::afterCommit(function () use ($agent, $paid) {
                    try {
                        app(ReferralCommissionService::class)->record(
                            $agent,
                            ReferralCommission::KIND_AGENT_EARNING,
                            $paid,
                            (string) $paid->reward_amount,
                        );
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
            }

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

    private function assertAllStepsAccepted(CampaignParticipation $participation): void
    {
        $steps = CampaignParticipationTask::query()
            ->where('participation_id', $participation->id)
            ->lockForUpdate()
            ->get();

        if ($steps->isEmpty() || $steps->contains(fn ($s) => $s->status !== CampaignParticipationTask::STATUS_ACCEPTED)) {
            throw new InvalidArgumentException('Every step must be accepted before this task is paid.');
        }
    }

    private function assertIdentityNotRevoked(CampaignParticipation $participation): void
    {
        if (! YoutubeIdentityService::enabled()) {
            return;
        }

        $verified = AgentYoutubeIdentity::query()
            ->where('user_id', $participation->agent_id)
            ->where('status', AgentYoutubeIdentity::STATUS_VERIFIED)
            ->exists();

        if (! $verified) {
            throw new InvalidArgumentException('The agent\'s YouTube channel is no longer verified, so this task cannot be paid.');
        }
    }
}
