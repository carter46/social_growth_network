<?php

namespace App\Services\SiteIntegrations;

use App\Models\User;
use App\Models\UserTool;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use Illuminate\Support\Facades\Route;

class UserToolLifecycleNotifier
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
    ) {}

    public function notifyNaturallyExpired(UserTool $tool): void
    {
        $user = $this->owner($tool);
        if (! $user) {
            return;
        }

        $productName = $this->productName($tool);
        $toolUrl = $this->toolUrl($tool);

        $this->dispatcher->notifyUser(
            $user,
            new NotificationMessage(
                type: 'tool.subscription_expired',
                title: __('Your subscription has expired'),
                body: __(':product has expired. Renew to restore access to your website and admin tools.', [
                    'product' => $productName,
                ]),
                actionUrl: $toolUrl,
                meta: ['user_tool_id' => $tool->id],
                emailSubject: __(':product: subscription expired', ['product' => $productName]),
                dedupeKey: 'tool.subscription_expired.'.$tool->id.'.'.$tool->expires_at?->timestamp,
            ),
            ['database', 'mail']
        );
    }

    public function notifyExtendedAfterNaturalExpiry(UserTool $tool): void
    {
        $user = $this->owner($tool);
        if (! $user) {
            return;
        }

        $productName = $this->productName($tool);
        $toolUrl = $this->toolUrl($tool);
        $expiresLabel = $tool->expires_at?->timezone(config('app.timezone'))->format('M j, Y');

        $this->dispatcher->notifyUser(
            $user,
            new NotificationMessage(
                type: 'tool.subscription_extended',
                title: __('Your subscription was extended'),
                body: $expiresLabel
                    ? __(':product is active again until :date.', [
                        'product' => $productName,
                        'date' => $expiresLabel,
                    ])
                    : __(':product is active again.', ['product' => $productName]),
                actionUrl: $toolUrl,
                meta: [
                    'user_tool_id' => $tool->id,
                    'expires_at' => $tool->expires_at?->toIso8601String(),
                ],
                emailSubject: __(':product: subscription extended', ['product' => $productName]),
                dedupeKey: 'tool.subscription_extended.'.$tool->id.'.'.$tool->expires_at?->timestamp,
            ),
            ['database', 'mail']
        );
    }

    private function owner(UserTool $tool): ?User
    {
        $tool->loadMissing('user');

        return $tool->user;
    }

    private function productName(UserTool $tool): string
    {
        $tool->loadMissing('product');

        return $tool->product?->title ?? $tool->resolvedDisplayName();
    }

    private function toolUrl(UserTool $tool): ?string
    {
        return Route::has('dashboard.my-tools.show')
            ? route('dashboard.my-tools.show', $tool)
            : null;
    }
}
