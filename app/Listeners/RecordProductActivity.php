<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Events\TicketOpened;
use App\Events\TicketReplied;
use App\Events\UserRegistered;
use App\Events\UserVerified;
use App\Events\WalletFunded;
use App\Events\WalletWithdrawalCompleted;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Services\Analytics\UserActivityRecorder;

class RecordProductActivity
{
    public function __construct(private UserActivityRecorder $recorder) {}

    public function handle(object $event): void
    {
        match ($event::class) {
            OrderCompleted::class => $this->handleOrderCompleted($event),
            WalletFunded::class => $this->handleWalletFunded($event),
            WalletWithdrawalCompleted::class => $this->handleWalletWithdrawalCompleted($event),
            UserRegistered::class => $this->recorder->incrementDaily('user.registered'),
            UserVerified::class => $this->recorder->incrementDaily('user.verified'),
            TicketOpened::class => $this->handleTicketOpened($event),
            TicketReplied::class => $this->recorder->incrementDaily(
                $event->isAdminReply ? 'ticket.admin_reply' : 'ticket.user_reply',
                (string) $event->ticketId
            ),
            default => null,
        };
    }

    private function handleOrderCompleted(OrderCompleted $event): void
    {
        $order = Order::query()->find($event->orderId);
        if ($order && $event->buyerId) {
            $this->recorder->record($event->buyerId, 'completed', $order, 'order.completed');

            return;
        }

        $this->recorder->incrementDaily('order.completed', (string) $event->orderId);
    }

    private function handleWalletFunded(WalletFunded $event): void
    {
        $this->recorder->incrementDaily('wallet.funded', $event->currency);
        $this->recorder->record($event->userId, 'funded', null, 'wallet.funded', [
            'transaction_id' => $event->transactionId,
            'amount' => $event->amount,
            'currency' => $event->currency,
        ]);
    }

    private function handleWalletWithdrawalCompleted(WalletWithdrawalCompleted $event): void
    {
        $this->recorder->incrementDaily('wallet.withdrawal_completed', $event->currency);
        $this->recorder->record($event->userId, 'withdrawal_completed', null, 'wallet.withdrawal_completed', [
            'withdrawal_id' => $event->withdrawalId,
            'transaction_id' => $event->transactionId,
            'amount' => $event->amount,
            'currency' => $event->currency,
        ]);
    }

    private function handleTicketOpened(TicketOpened $event): void
    {
        $this->recorder->incrementDaily('ticket.opened', (string) $event->ticketId);

        $ticket = SupportTicket::query()->find($event->ticketId);
        if ($ticket) {
            $this->recorder->record($event->userId, 'opened', $ticket, 'ticket.opened');
        }
    }
}
