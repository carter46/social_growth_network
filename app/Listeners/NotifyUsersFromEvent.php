<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Events\TicketReplied;
use App\Events\WalletFunded;
use App\Events\WalletWithdrawalCompleted;
use App\Events\WithdrawalPayoutFailed;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationEmailRenderer;
use App\Services\Notifications\NotificationMessage;
use Illuminate\Support\Facades\Route;

/**
 * User-facing notifications from domain events (mail + in-app).
 * Listing approve/reject also notify from admin controllers for immediate UX;
 * this listener covers events that have no controller notification path.
 */
class NotifyUsersFromEvent
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private NotificationEmailRenderer $emailRenderer,
    ) {}

    public function handle(object $event): void
    {
        match ($event::class) {
            WalletFunded::class => $this->walletFunded($event),
            WalletWithdrawalCompleted::class => $this->walletWithdrawalCompleted($event),
            WithdrawalPayoutFailed::class => $this->withdrawalPayoutFailed($event),
            OrderCompleted::class => $this->orderCompleted($event),
            TicketReplied::class => $this->ticketReplied($event),
            default => null,
        };
    }

    private function walletFunded(WalletFunded $event): void
    {
        $user = User::query()->find($event->userId);
        if (! $user) {
            return;
        }

        $this->dispatcher->notifyUser(
            $user,
            new NotificationMessage(
                type: 'wallet.deposit_credited',
                title: __('Deposit credited'),
                body: __('Your wallet was credited with :amount :currency.', [
                    'amount' => number_format($event->amount, 2),
                    'currency' => $event->currency,
                ]),
                actionUrl: Route::has('dashboard.wallet') ? route('dashboard.wallet') : null,
                meta: ['transaction_id' => $event->transactionId],
                emailSubject: __('Deposit credited'),
            ),
            ['database', 'mail']
        );
    }

    private function walletWithdrawalCompleted(WalletWithdrawalCompleted $event): void
    {
        $user = User::query()->find($event->userId);
        if (! $user) {
            return;
        }

        $this->dispatcher->notifyUser(
            $user,
            new NotificationMessage(
                type: 'wallet.withdrawal_completed',
                title: __('Withdrawal completed'),
                body: __('Your withdrawal of :amount :currency was sent to your bank.', [
                    'amount' => number_format($event->amount, 2),
                    'currency' => $event->currency,
                ]),
                actionUrl: $this->withdrawalsUrl($user),
                meta: [
                    'withdrawal_id' => $event->withdrawalId,
                    'transaction_id' => $event->transactionId,
                ],
                emailSubject: __('Withdrawal completed'),
            ),
            ['database', 'mail']
        );
    }

    private function withdrawalPayoutFailed(WithdrawalPayoutFailed $event): void
    {
        $user = User::query()->find($event->userId);
        if (! $user) {
            return;
        }

        $type = match ($event->outcome) {
            'expired' => 'wallet.withdrawal_expired',
            'reversed' => 'wallet.withdrawal_reversed',
            default => 'wallet.withdrawal_failed',
        };

        $title = match ($event->outcome) {
            'expired' => __('Withdrawal expired'),
            'reversed' => __('Withdrawal reversed'),
            default => __('Withdrawal failed'),
        };

        $body = match ($event->outcome) {
            'expired' => __('Your withdrawal of :amount :currency expired. Funds have been returned to your wallet.', [
                'amount' => number_format($event->amount, 2),
                'currency' => $event->currency,
            ]),
            'reversed' => __('Your withdrawal of :amount :currency was reversed. Please contact support if you need assistance.', [
                'amount' => number_format($event->amount, 2),
                'currency' => $event->currency,
            ]),
            default => __('Your withdrawal of :amount :currency could not be completed. Funds have been returned to your wallet.', [
                'amount' => number_format($event->amount, 2),
                'currency' => $event->currency,
            ]),
        };

        $this->dispatcher->notifyUser(
            $user,
            new NotificationMessage(
                type: $type,
                title: $title,
                body: $body,
                actionUrl: $this->withdrawalsUrl($user),
                meta: ['withdrawal_id' => $event->withdrawalId],
                emailSubject: $title,
            ),
            ['database', 'mail']
        );
    }

    private function orderCompleted(OrderCompleted $event): void
    {
        $order = Order::query()->with(['items', 'user'])->find($event->orderId);
        if (! $order?->user) {
            return;
        }

        $context = $this->emailRenderer->orderContext($order);

        $this->dispatcher->notifyUser(
            $order->user,
            new NotificationMessage(
                type: 'order.completed',
                title: __('Order confirmed'),
                body: __('Thank you for your purchase. Order :ref for ₦:amount has been confirmed.', [
                    'ref' => $order->reference,
                    'amount' => number_format((float) ($order->total_amount ?? $order->amount), 2),
                ]),
                actionUrl: Route::has('dashboard.orders') ? route('dashboard.orders') : null,
                meta: [
                    'order_id' => $order->id,
                    'email_context' => $context,
                ],
                emailSubject: __('Order :ref confirmed', ['ref' => $order->reference]),
            ),
            ['database', 'mail']
        );
    }

    private function ticketReplied(TicketReplied $event): void
    {
        if (! $event->isAdminReply) {
            return;
        }

        $ticket = SupportTicket::query()->find($event->ticketId);
        if (! $ticket?->user) {
            return;
        }

        // Only the first staff reply is emailed; later ones are in-app until
        // support:remind-unanswered emails a reminder after 24h without a user reply.
        $isFirstStaffReply = $this->claimFirstStaffReplyEmail($ticket);

        $this->dispatcher->notifyUser(
            $ticket->user,
            new NotificationMessage(
                type: 'ticket.replied',
                title: __('Support replied'),
                body: __('A staff member replied to ticket #:id.', ['id' => $ticket->id]),
                actionUrl: $ticket->memberUrl(),
                meta: ['ticket_id' => $ticket->id],
                emailSubject: __('Support replied to your ticket'),
            ),
            $isFirstStaffReply ? ['database', 'mail'] : ['database']
        );
    }

    /** Atomically marks the ticket's first staff reply as emailed; false if any staff reply was already emailed. */
    private function claimFirstStaffReplyEmail(SupportTicket $ticket): bool
    {
        $staffReplies = $ticket->replies()->where('is_staff', true);

        if ((clone $staffReplies)->whereNotNull('emailed_at')->exists()) {
            return false;
        }

        $firstId = (clone $staffReplies)->orderBy('id')->value('id');

        return $firstId !== null
            && SupportTicketReply::query()->whereKey($firstId)->whereNull('emailed_at')->update(['emailed_at' => now()]) === 1;
    }

    private function withdrawalsUrl(User $user): ?string
    {
        if ($user->hasRole('agent') && Route::has('agent.withdrawal.index')) {
            return route('agent.withdrawal.index');
        }

        return Route::has('dashboard.wallet') ? route('dashboard.wallet') : null;
    }
}
