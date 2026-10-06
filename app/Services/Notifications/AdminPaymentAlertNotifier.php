<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Route;

class AdminPaymentAlertNotifier
{
    public function __construct(private NotificationDispatcher $dispatcher) {}

    public function gatewayUnmatched(string $paymentReference, array $meta = []): void
    {
        $this->notify(
            type: 'payment.gateway_unmatched',
            title: 'Unmatched Monnify payment',
            body: 'No funding or platform order matched payment reference '.$paymentReference.'.',
            dedupeKey: 'payment.gateway_unmatched.'.$paymentReference,
            meta: $meta,
        );
    }

    public function amountMismatch(string $context, string $expected, string $paid, array $meta = []): void
    {
        $this->notify(
            type: 'payment.amount_mismatch',
            title: 'Monnify amount mismatch',
            body: $context.' expected '.$expected.' but received '.$paid.'.',
            dedupeKey: 'payment.amount_mismatch.'.md5($context.$expected.$paid),
            meta: $meta,
        );
    }

    public function depositHeld(string $reference, string $amount, string $max, array $meta = []): void
    {
        $this->notify(
            type: 'payment.deposit_held',
            title: 'Deposit held: above maximum',
            body: 'Reserved account payment '.$reference.' of NGN '.number_format((float) $amount, 2).' is above the NGN '.number_format((float) $max, 2).' maximum deposit and was not credited. Verify and credit it, or reject it and refund from the gateway dashboard.',
            dedupeKey: 'payment.deposit_held.'.$reference,
            meta: $meta,
        );
    }

    public function disbursementFailed(string $reference, string $status, array $meta = []): void
    {
        $this->notify(
            type: 'payment.disbursement_failed',
            title: 'Monnify disbursement '.$status,
            body: 'Disbursement '.$reference.' reported status '.$status.'.',
            dedupeKey: 'payment.disbursement_failed.'.$reference.'.'.$status,
            meta: $meta,
        );
    }

    public function disbursementReversed(string $reference, array $meta = []): void
    {
        $this->notify(
            type: 'payment.disbursement_reversed',
            title: 'Monnify disbursement reversed',
            body: 'Disbursement '.$reference.' was reversed after payout. Manual review required.',
            dedupeKey: 'payment.disbursement_reversed.'.$reference,
            meta: $meta,
        );
    }

    private function notify(string $type, string $title, string $body, string $dedupeKey, array $meta): void
    {
        $this->dispatcher->notifyAdmins(
            new NotificationMessage(
                type: $type,
                title: $title,
                body: $body,
                actionUrl: Route::has('admin.fundings') ? route('admin.fundings') : null,
                meta: array_merge($meta, ['event' => 'monnify.webhook']),
                permission: 'finance.manage',
                dedupeKey: $dedupeKey,
                emailSubject: $title,
            ),
            AdminNotificationChannels::FINANCE
        );
    }
}
