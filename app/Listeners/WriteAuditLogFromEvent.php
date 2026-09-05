<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Events\TicketOpened;
use App\Events\UserRegistered;
use App\Events\WalletFunded;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use Illuminate\Support\Facades\Log;
use Throwable;

class WriteAuditLogFromEvent
{
    public function __construct(private AuditLogService $audit) {}

    public function handle(object $event): void
    {
        try {
            [$action, $model, $payload] = match ($event::class) {
                OrderCompleted::class => [
                    'event.order.completed',
                    Order::query()->find($event->orderId),
                    ['order_id' => $event->orderId],
                ],
                TicketOpened::class => [
                    'event.ticket.opened',
                    SupportTicket::query()->find($event->ticketId),
                    ['ticket_id' => $event->ticketId, 'user_id' => $event->userId],
                ],
                WalletFunded::class => [
                    'event.wallet.funded',
                    User::query()->find($event->userId),
                    ['transaction_id' => $event->transactionId, 'amount' => $event->amount],
                ],
                \App\Events\WalletWithdrawalCompleted::class => [
                    'event.wallet.withdrawal_completed',
                    User::query()->find($event->userId),
                    [
                        'withdrawal_id' => $event->withdrawalId,
                        'transaction_id' => $event->transactionId,
                        'amount' => $event->amount,
                    ],
                ],
                UserRegistered::class => [
                    'event.user.registered',
                    User::query()->find($event->userId),
                    ['user_id' => $event->userId],
                ],
                default => [null, null, []],
            };

            if ($action === null) {
                return;
            }

            $this->audit->log(null, $action, $model, null, $payload, request()?->ip());
        } catch (Throwable $e) {
            Log::warning('analytics.audit_from_event_failed', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
