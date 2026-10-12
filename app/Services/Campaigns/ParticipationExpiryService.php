<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignBudget;
use App\Models\CampaignParticipation;
use App\Models\CampaignParticipationTask;
use App\Models\CampaignWatchSession;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Completion timer: a started task that passes its deadline expires, nothing is paid,
 * and the slot opens for the next agent. Submitted tasks never expire (review time doesn't count).
 */
class ParticipationExpiryService
{
    public function __construct(
        private NotificationDispatcher $notifications,
    ) {}

    public function expire(CampaignParticipation $participation): bool
    {
        return $this->close($participation, false);
    }

    /**
     * Admin frees the slot of a started task (for example an agent who walked away from a
     * campaign with no deadline), so a cancelled campaign's refund is no longer blocked.
     */
    public function release(CampaignParticipation $participation): bool
    {
        return $this->close($participation, true);
    }

    private function close(CampaignParticipation $participation, bool $byAdmin): bool
    {
        $expired = DB::transaction(function () use ($participation, $byAdmin) {
            $participation = CampaignParticipation::query()->whereKey($participation->id)->lockForUpdate()->first();
            $closable = $byAdmin
                ? $participation?->status === CampaignParticipation::STATUS_STARTED
                : (bool) $participation?->isPastDeadline();
            if (! $closable) {
                return null;
            }

            $participation->update([
                'status' => CampaignParticipation::STATUS_EXPIRED,
                'rejection_reason' => $byAdmin
                    ? 'An admin released this task to other agents.'
                    : 'The time to complete this task ran out.',
            ]);

            if (Schema::hasTable('campaign_participation_tasks')) {
                CampaignParticipationTask::query()
                    ->where('participation_id', $participation->id)
                    ->whereIn('status', [
                        CampaignParticipationTask::STATUS_LOCKED,
                        CampaignParticipationTask::STATUS_ACTIVE,
                        CampaignParticipationTask::STATUS_DONE,
                    ])
                    ->update(['status' => CampaignParticipationTask::STATUS_EXPIRED, 'updated_at' => now()]);
            }

            CampaignWatchSession::query()
                ->where('campaign_participation_id', $participation->id)
                ->whereNull('claimed_at')
                ->delete();

            return $participation;
        });

        if (! $expired) {
            return false;
        }

        $this->notifyAgent($expired, $byAdmin);

        return true;
    }

    /** Every-minute job: expire overdue tasks, then refund cancelled campaigns once nothing is in flight. */
    public function processOverdue(int $limit = 200): int
    {
        $n = 0;

        if (Schema::hasColumn('campaign_participations', 'expires_at')) {
            CampaignParticipation::query()
                ->where('status', CampaignParticipation::STATUS_STARTED)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->orderBy('expires_at')
                ->limit($limit)
                ->get()
                ->each(function (CampaignParticipation $participation) use (&$n) {
                    if ($this->expire($participation)) {
                        $n++;
                    }
                });
        }

        if (CampaignBudgetService::enabled()) {
            $budgets = app(CampaignBudgetService::class);
            // Campaigns still waiting on tasks are skipped so they can't crowd out refundable ones.
            Campaign::query()
                ->where('status', Campaign::STATUS_CANCELLED)
                ->whereHas('budget', fn ($q) => $q->where('status', CampaignBudget::STATUS_HELD))
                ->whereDoesntHave('participations', fn ($q) => $q->whereIn('status', CampaignBudgetService::IN_FLIGHT_STATUSES))
                ->orderBy('id')
                ->limit(50)
                ->get()
                ->each(function (Campaign $campaign) use ($budgets) {
                    try {
                        $budgets->refundIfCancelled($campaign);
                    } catch (\Throwable $e) {
                        Log::channel('financial')->error('Campaign refund failed', ['campaign_id' => $campaign->id, 'error' => $e->getMessage()]);
                    }
                });

            // Cancelled campaigns that never had a held budget (unfunded or legacy) are refunded from the platform wallet.
            $refunded = \App\Models\Transaction::query()
                ->where('reference', 'like', 'CMP-REFUND-%')
                ->where('reference', 'not like', '%-P')
                ->pluck('reference')
                ->map(fn (string $ref) => (int) substr($ref, strlen('CMP-REFUND-')))
                ->all();
            Campaign::query()
                ->where('status', Campaign::STATUS_CANCELLED)
                ->whereDoesntHave('budget')
                ->whereColumn('completed_count', '<', 'quantity')
                ->whereDoesntHave('participations', fn ($q) => $q->whereIn('status', CampaignBudgetService::IN_FLIGHT_STATUSES))
                ->when($refunded !== [], fn ($q) => $q->whereNotIn('id', $refunded))
                ->orderBy('id')
                ->limit(50)
                ->get()
                ->each(function (Campaign $campaign) use ($budgets) {
                    try {
                        $budgets->refundIfCancelled($campaign);
                    } catch (\Throwable $e) {
                        Log::channel('financial')->error('Campaign refund failed', ['campaign_id' => $campaign->id, 'error' => $e->getMessage()]);
                    }
                });
        }

        return $n;
    }

    private function notifyAgent(CampaignParticipation $participation, bool $byAdmin = false): void
    {
        try {
            $agent = $participation->agent()->first();
            $campaign = $participation->campaign()->first();
            if (! $agent || ! $campaign) {
                return;
            }

            $this->notifications->notifyUser($agent, new NotificationMessage(
                type: 'campaign_task.expired',
                title: $byAdmin ? __('Task released') : __('Task time ran out'),
                body: $byAdmin
                    ? __('":title" was released to other agents by our team before you submitted it. Nothing was paid for it.', ['title' => $campaign->title])
                    : __('The time to complete ":title" ran out before you submitted it, so the task was released to other agents. Nothing was paid for it.', ['title' => $campaign->title]),
                actionUrl: Route::has('agent.tasks.show') ? route('agent.tasks.show', $participation) : null,
                meta: ['participation_id' => $participation->id, 'campaign_id' => $campaign->id],
                dedupeKey: 'campaign_task.expired.'.$participation->id,
            ), ['database']);
        } catch (\Throwable $e) {
            Log::warning('campaign_task.expired_notify_failed', ['participation_id' => $participation->id, 'error' => $e->getMessage()]);
        }
    }
}
