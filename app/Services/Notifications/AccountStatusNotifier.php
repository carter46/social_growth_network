<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Tells a person when their account is suspended or restored. Always emailed: these are
 * account-status messages, not marketing, so preferences do not apply.
 */
class AccountStatusNotifier
{
    public function __construct(private NotificationDispatcher $notifications) {}

    public function suspended(User $user): void
    {
        $this->send($user, new NotificationMessage(
            type: 'security.account_suspended',
            title: 'Your account has been suspended',
            body: 'Your account has been suspended, so you can\'t sign in or use it for now. If you think this is a mistake, please contact our support team and we\'ll look into it.',
            actionUrl: Route::has('contact') ? route('contact') : null,
            meta: ['user_id' => $user->id, 'event' => 'suspended'],
            emailSubject: 'Your account has been suspended',
            priority: 'high',
            dedupeKey: 'account.suspended.'.$user->id.'.'.now()->timestamp,
        ));
    }

    public function restored(User $user): void
    {
        $this->send($user, new NotificationMessage(
            type: 'security.account_restored',
            title: 'Your account is active again',
            body: 'Your account suspension has been lifted. You can sign in and use your account as before.',
            actionUrl: Route::has('login') ? route('login') : null,
            meta: ['user_id' => $user->id, 'event' => 'restored'],
            emailSubject: 'Your account is active again',
            dedupeKey: 'account.restored.'.$user->id.'.'.now()->timestamp,
        ));
    }

    private function send(User $user, NotificationMessage $message): void
    {
        if ($user->anonymized_at !== null || blank($user->email)) {
            return;
        }

        try {
            $this->notifications->notifyUser($user, $message, ['database', 'mail']);
        } catch (\Throwable $e) {
            Log::warning('account_status.notify_failed', ['user_id' => $user->id, 'type' => $message->type, 'error' => $e->getMessage()]);
        }
    }
}
