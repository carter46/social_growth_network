<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Emails (and in-app notifies) the creator whenever their campaign is created or its status changes.
 */
class CampaignStatusNotifier
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
    ) {}

    public function notify(Campaign $campaign, ?string $previousStatus): void
    {
        try {
            $creator = $campaign->creator()->first();
            if (! $creator) {
                return;
            }

            [$title, $body] = $this->copy($campaign, $previousStatus);

            $this->dispatcher->notifyUser(
                $creator,
                new NotificationMessage(
                    type: 'campaign.'.$campaign->status,
                    title: $title,
                    body: $body,
                    actionUrl: Route::has('dashboard.campaigns.show') ? route('dashboard.campaigns.show', $campaign) : null,
                    meta: [
                        'campaign_id' => $campaign->id,
                        'previous_status' => $previousStatus,
                    ],
                    emailSubject: $title,
                    dedupeKey: $previousStatus === null ? 'campaign.'.$campaign->id.'.created' : null,
                ),
                ['database', 'mail']
            );
        } catch (Throwable $e) {
            Log::warning('campaign.status_notify_failed', [
                'campaign_id' => $campaign->id,
                'status' => $campaign->status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function copy(Campaign $campaign, ?string $previousStatus): array
    {
        $name = '"'.$campaign->title.'"';
        $done = number_format((int) $campaign->completed_count);
        $total = number_format((int) $campaign->quantity);

        return match ($campaign->status) {
            Campaign::STATUS_ACTIVE => $previousStatus === null
                ? [__('Your campaign is now active'), __('Payment confirmed. Your campaign :name is now live and open to agents. You can follow its progress from your dashboard.', ['name' => $name])]
                : [__('Your campaign is active again'), __('Your campaign :name is live again and open to agents.', ['name' => $name])],
            Campaign::STATUS_PAUSED => [
                __('Your campaign has been paused'),
                __('Your campaign :name has been paused. New agents cannot join it for now. Tasks already in progress can still be finished.', ['name' => $name]),
            ],
            Campaign::STATUS_COMPLETED => (int) $campaign->completed_count >= (int) $campaign->quantity
                ? [__('Your campaign is complete'), __('All :total units on your campaign :name have been delivered and verified.', ['total' => $total, 'name' => $name])]
                : [__('Your campaign has been marked as completed'), __('Your campaign :name has been marked as completed. :done of :total units were delivered.', ['name' => $name, 'done' => $done, 'total' => $total])],
            Campaign::STATUS_REJECTED => [
                __('Your campaign was rejected'),
                __('Your campaign :name was rejected and is no longer open to agents. Please contact support if you have any questions.', ['name' => $name]),
            ],
            Campaign::STATUS_SUSPENDED => [
                __('Your campaign has been suspended'),
                __('Your campaign :name has been suspended and work on it has stopped for now. Please contact support if you have any questions.', ['name' => $name]),
            ],
            Campaign::STATUS_CANCELLED => [
                __('Your campaign has been cancelled'),
                __('Your campaign :name has been cancelled and is no longer open to agents. :done of :total units were delivered. Please contact support if you have any questions.', ['name' => $name, 'done' => $done, 'total' => $total]),
            ],
            Campaign::STATUS_PENDING_REVIEW => [
                __('Your campaign is under review'),
                __('Your campaign :name is being reviewed by our team. It is not open to agents during the review.', ['name' => $name]),
            ],
            Campaign::STATUS_DRAFT => [
                __('Your campaign has been moved to draft'),
                __('Your campaign :name has been moved to draft and is not open to agents right now.', ['name' => $name]),
            ],
            default => [
                __('Your campaign status changed'),
                __('Your campaign :name is now :status.', ['name' => $name, 'status' => strtolower($campaign->statusLabel())]),
            ],
        };
    }
}
