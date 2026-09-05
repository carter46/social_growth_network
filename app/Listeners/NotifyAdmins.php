<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Events\OrderManualBankTransferPaymentFailed;
use App\Events\OrderManualBankTransferSubmitted;
use App\Events\TicketOpened;
use App\Events\TicketReplied;
use App\Events\UserRegistered;
use App\Events\UserVerified;
use App\Events\WalletFunded;
use App\Events\WalletFundingSubmitted;
use App\Events\WalletWithdrawalCompleted;
use App\Events\WithdrawalAwaitingProviderAuthorization;
use App\Events\WithdrawalPayoutFailed;
use App\Events\WithdrawalRequested;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletFunding;
use App\Models\Withdrawal;
use App\Services\Notifications\AdminNotificationChannels;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationEmailRenderer;
use App\Services\Notifications\NotificationMessage;
use App\Services\Notifications\OrderNotificationTypeResolver;
use Illuminate\Support\Facades\Route;

class NotifyAdmins
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private OrderNotificationTypeResolver $orderTypes,
        private NotificationEmailRenderer $emailRenderer,
    ) {}

    public function handle(object $event): void
    {
        $payload = match ($event::class) {
            TicketOpened::class => [
                'type' => 'ticket.opened',
                'title' => 'New support ticket',
                'body' => 'Ticket #'.$event->ticketId.' was opened.',
                'actionUrl' => Route::has('admin.tickets') ? route('admin.tickets') : null,
                'meta' => ['ticket_id' => $event->ticketId, 'user_id' => $event->userId, 'event' => $event::class],
                'permission' => 'support.manage',
                'dedupeKey' => 'ticket.opened.'.$event->ticketId,
            ],
            TicketReplied::class => $event->isAdminReply ? null : [
                'type' => 'ticket.replied',
                'title' => 'Support ticket reply',
                'body' => 'Ticket #'.$event->ticketId.' received a user reply.',
                'actionUrl' => Route::has('admin.tickets') ? route('admin.tickets') : null,
                'meta' => ['ticket_id' => $event->ticketId, 'replier_id' => $event->replierId, 'event' => $event::class],
                'permission' => 'support.manage',
                'dedupeKey' => 'ticket.replied.'.$event->ticketId.'.'.$event->replierId,
            ],
            UserRegistered::class => $this->userRegisteredPayload($event),
            UserVerified::class => $this->userVerifiedPayload($event),
            WalletFundingSubmitted::class => $this->depositSubmittedPayload($event),
            WalletFunded::class => $this->depositCreditedPayload($event),
            WithdrawalRequested::class => $this->withdrawalRequestedPayload($event),
            WithdrawalAwaitingProviderAuthorization::class => $this->withdrawalAwaitingProviderAuthPayload($event),
            WalletWithdrawalCompleted::class => $this->withdrawalCompletedPayload($event),
            WithdrawalPayoutFailed::class => $this->withdrawalFailedPayload($event),
            OrderCompleted::class => $this->orderCompletedPayload($event),
            OrderManualBankTransferSubmitted::class => $this->orderManualBankTransferSubmittedPayload($event),
            OrderManualBankTransferPaymentFailed::class => $this->orderManualBankTransferPaymentFailedPayload($event),
            default => null,
        };

        if ($payload === null) {
            return;
        }

        $channels = str_starts_with($payload['type'], 'ticket.')
            ? AdminNotificationChannels::SUPPORT
            : (str_starts_with($payload['type'], 'order.')
                ? AdminNotificationChannels::SALES
                : (str_starts_with($payload['type'], 'user.')
                    ? AdminNotificationChannels::GENERAL
                    : AdminNotificationChannels::FINANCE));

        $this->dispatcher->notifyAdmins(
            new NotificationMessage(
                type: $payload['type'],
                title: $payload['title'],
                body: $payload['body'],
                actionUrl: $payload['actionUrl'],
                meta: $payload['meta'],
                permission: $payload['permission'],
                dedupeKey: $payload['dedupeKey'],
                emailSubject: $payload['emailSubject'] ?? $payload['title'],
            ),
            $channels
        );
    }

    private function userVerifiedPayload(UserVerified $event): array
    {
        $user = User::query()->find($event->userId);

        return [
            'type' => 'user.verified',
            'title' => 'User email verified',
            'body' => $user
                ? $user->name.' ('.$user->email.') verified their email.'
                : 'User #'.$event->userId.' verified their email.',
            'actionUrl' => Route::has('admin.users') ? route('admin.users') : null,
            'meta' => ['user_id' => $event->userId, 'event' => $event::class],
            'permission' => 'users.manage',
            'dedupeKey' => 'user.verified.'.$event->userId,
        ];
    }

    private function userRegisteredPayload(UserRegistered $event): array
    {
        $user = User::query()->find($event->userId);

        return [
            'type' => 'user.registered',
            'title' => 'New user registration',
            'body' => $user
                ? $user->name.' ('.$user->email.') signed up.'
                : 'User #'.$event->userId.' signed up.',
            'actionUrl' => Route::has('admin.users') ? route('admin.users') : null,
            'meta' => ['user_id' => $event->userId, 'event' => $event::class],
            'permission' => 'users.manage',
            'dedupeKey' => 'user.registered.'.$event->userId,
        ];
    }

    private function depositSubmittedPayload(WalletFundingSubmitted $event): array
    {
        $funding = WalletFunding::query()->find($event->fundingId);

        return [
            'type' => 'wallet.deposit_submitted',
            'title' => 'Deposit submitted',
            'body' => sprintf(
                'User #%d submitted a %s deposit of %s %s%s.',
                $event->userId,
                $event->method,
                $event->currency,
                number_format($event->amount, 2),
                $funding?->reference ? ' (ref '.$funding->reference.')' : ''
            ),
            'actionUrl' => Route::has('admin.fundings') ? route('admin.fundings') : null,
            'meta' => ['funding_id' => $event->fundingId, 'user_id' => $event->userId, 'event' => $event::class],
            'permission' => 'finance.manage',
            'dedupeKey' => 'wallet.deposit_submitted.'.$event->fundingId,
        ];
    }

    private function depositCreditedPayload(WalletFunded $event): array
    {
        return [
            'type' => 'wallet.deposit_credited',
            'title' => 'Deposit credited',
            'body' => 'User #'.$event->userId.' wallet credited ('.$event->currency.' '.number_format($event->amount, 2).').',
            'actionUrl' => Route::has('admin.transactions') ? route('admin.transactions') : null,
            'meta' => [
                'user_id' => $event->userId,
                'transaction_id' => $event->transactionId,
                'event' => $event::class,
            ],
            'permission' => 'finance.manage',
            'dedupeKey' => 'wallet.deposit_credited.'.$event->transactionId,
        ];
    }

    private function withdrawalRequestedPayload(WithdrawalRequested $event): array
    {
        $withdrawal = Withdrawal::query()->find($event->withdrawalId);

        return [
            'type' => 'wallet.withdrawal_requested',
            'title' => 'Withdrawal requested',
            'body' => sprintf(
                'User #%d requested withdrawal of %s %s%s.',
                $event->userId,
                $event->currency,
                number_format($event->amount, 2),
                $withdrawal?->reference ? ' (ref '.$withdrawal->reference.')' : ''
            ),
            'actionUrl' => Route::has('admin.withdrawals') ? route('admin.withdrawals') : null,
            'meta' => ['withdrawal_id' => $event->withdrawalId, 'user_id' => $event->userId, 'event' => $event::class],
            'permission' => 'finance.manage',
            'dedupeKey' => 'wallet.withdrawal_requested.'.$event->withdrawalId,
        ];
    }

    private function withdrawalAwaitingProviderAuthPayload(WithdrawalAwaitingProviderAuthorization $event): array
    {
        $detailUrl = Route::has('admin.withdrawals.show')
            ? route('admin.withdrawals.show', $event->withdrawalId)
            : (Route::has('admin.withdrawals') ? route('admin.withdrawals') : null);

        return [
            'type' => 'wallet.withdrawal_awaiting_provider_auth',
            'title' => 'Withdrawal needs Monnify OTP',
            'body' => sprintf(
                'Withdrawal %s (₦%s) is pending Monnify authorization. Enter the OTP from your Monnify merchant email.',
                $event->providerPayoutReference,
                number_format($event->amount, 2),
            ),
            'actionUrl' => $detailUrl,
            'meta' => [
                'withdrawal_id' => $event->withdrawalId,
                'user_id' => $event->userId,
                'provider_payout_reference' => $event->providerPayoutReference,
                'event' => $event::class,
            ],
            'permission' => 'finance.manage',
            'dedupeKey' => 'wallet.withdrawal_awaiting_provider_auth.'.$event->withdrawalId,
        ];
    }

    private function withdrawalCompletedPayload(WalletWithdrawalCompleted $event): array
    {
        return [
            'type' => 'wallet.withdrawal_completed',
            'title' => 'Withdrawal completed',
            'body' => 'User #'.$event->userId.' withdrawal completed ('.$event->currency.' '.number_format($event->amount, 2).').',
            'actionUrl' => Route::has('admin.withdrawals') ? route('admin.withdrawals') : null,
            'meta' => [
                'user_id' => $event->userId,
                'withdrawal_id' => $event->withdrawalId,
                'transaction_id' => $event->transactionId,
                'event' => $event::class,
            ],
            'permission' => 'finance.manage',
            'dedupeKey' => 'wallet.withdrawal_completed.'.$event->withdrawalId,
        ];
    }

    private function withdrawalFailedPayload(WithdrawalPayoutFailed $event): array
    {
        $type = match ($event->outcome) {
            'expired' => 'wallet.withdrawal_expired',
            'reversed' => 'wallet.withdrawal_reversed',
            default => 'wallet.withdrawal_failed',
        };

        $withdrawal = Withdrawal::query()->find($event->withdrawalId);

        return [
            'type' => $type,
            'title' => ucfirst(str_replace('_', ' ', $type)),
            'body' => sprintf(
                'Withdrawal for user #%d %s (%s %s%s).',
                $event->userId,
                str_replace('wallet.withdrawal_', '', $type),
                $event->currency,
                number_format($event->amount, 2),
                $withdrawal?->reference ? ' ref '.$withdrawal->reference : ''
            ),
            'actionUrl' => Route::has('admin.withdrawals') ? route('admin.withdrawals') : null,
            'meta' => ['withdrawal_id' => $event->withdrawalId, 'user_id' => $event->userId, 'event' => $event::class],
            'permission' => 'finance.manage',
            'dedupeKey' => $type.'.'.$event->withdrawalId,
        ];
    }

    private function orderManualBankTransferSubmittedPayload(OrderManualBankTransferSubmitted $event): array
    {
        $order = Order::query()->find($event->orderId);

        return [
            'type' => 'order.manual_bank_transfer_proof',
            'title' => 'Payment proof submitted',
            'body' => sprintf(
                'User #%d submitted bank transfer proof for order %s (%s %s). Review and confirm payment.',
                $event->userId,
                $event->reference,
                $event->currency,
                number_format($event->amount, 2),
            ),
            'actionUrl' => Route::has('admin.orders.show') && $order
                ? route('admin.orders.show', $order)
                : (Route::has('admin.orders') ? route('admin.orders', ['filter' => 'awaiting_bank']) : null),
            'meta' => [
                'order_id' => $event->orderId,
                'user_id' => $event->userId,
                'event' => $event::class,
            ],
            'permission' => 'finance.manage',
            'dedupeKey' => 'order.manual_bank_transfer_proof.'.$event->orderId,
        ];
    }

    private function orderManualBankTransferPaymentFailedPayload(OrderManualBankTransferPaymentFailed $event): array
    {
        $order = Order::query()->find($event->orderId);

        return [
            'type' => 'order.manual_bank_transfer_failed',
            'title' => 'Manual bank transfer failed',
            'body' => sprintf(
                'Order %s for user #%d (%s %s) was cancelled: %s',
                $event->reference,
                $event->userId,
                $event->currency,
                number_format($event->amount, 2),
                $event->reason,
            ),
            'actionUrl' => Route::has('admin.orders.show') && $order
                ? route('admin.orders.show', $order)
                : (Route::has('admin.orders') ? route('admin.orders', ['filter' => 'failed_bank']) : null),
            'meta' => [
                'order_id' => $event->orderId,
                'user_id' => $event->userId,
                'reason' => $event->reason,
                'event' => $event::class,
            ],
            'permission' => 'finance.manage',
            'dedupeKey' => 'order.manual_bank_transfer_failed.'.$event->orderId,
        ];
    }

    private function orderCompletedPayload(OrderCompleted $event): ?array
    {
        $order = Order::query()->with(['items', 'user'])->find($event->orderId);
        if (! $order) {
            return null;
        }

        $type = $this->orderTypes->resolve($order);
        $context = $this->emailRenderer->orderContext($order);

        return [
            'type' => $type,
            'title' => 'New order: '.$order->reference,
            'body' => sprintf(
                '%s purchased for ₦%s.',
                $order->user?->name ?? 'Customer',
                number_format((float) ($order->total_amount ?? $order->amount), 2)
            ),
            'actionUrl' => Route::has('admin.orders') ? route('admin.orders') : null,
            'meta' => [
                'order_id' => $order->id,
                'event' => $event::class,
                'email_context' => $context,
            ],
            'permission' => 'catalog.manage',
            'dedupeKey' => 'order.completed.'.$order->id,
            'emailSubject' => 'Order '.$order->reference,
        ];
    }
}
