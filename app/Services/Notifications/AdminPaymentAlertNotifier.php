<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Route;

class AdminPaymentAlertNotifier
{
    public function __construct(private NotificationDispatcher $dispatcher) {}

    public function gatewayUnmatched(string $paymentReference, array $meta = [], string $provider = 'gateway'): void
    {
        $this->notify(
            type: 'payment.gateway_unmatched',
            title: 'Unmatched '.$this->label($provider).' payment',
            body: 'No funding or platform order matched payment reference '.$paymentReference.'.',
            dedupeKey: 'payment.gateway_unmatched.'.$paymentReference,
            meta: $meta + ['provider' => $provider],
        );
    }

    public function amountMismatch(string $context, string $expected, string $paid, array $meta = [], string $provider = 'gateway'): void
    {
        $this->notify(
            type: 'payment.amount_mismatch',
            title: $this->label($provider).' amount mismatch',
            body: $context.' expected '.$expected.' but received '.$paid.'.',
            dedupeKey: 'payment.amount_mismatch.'.md5($context.$expected.$paid),
            meta: $meta + ['provider' => $provider],
        );
    }

    public function depositHeld(string $reference, string $amount, string $max, array $meta = []): void
    {
        $this->notify(
            type: 'payment.deposit_held',
            title: 'Deposit held: above maximum',
            body: 'Payment '.$reference.' of '.money($amount).' is above the '.money($max).' maximum deposit and was not credited. Verify and credit it, or reject it and refund from the gateway dashboard.',
            dedupeKey: 'payment.deposit_held.'.$reference,
            meta: $meta,
        );
    }

    public function disbursementFailed(string $reference, string $status, array $meta = [], string $provider = 'gateway'): void
    {
        $this->notify(
            type: 'payment.disbursement_failed',
            title: $this->label($provider).' payout '.$status,
            body: 'Payout '.$reference.' reported status '.$status.'.',
            dedupeKey: 'payment.disbursement_failed.'.$reference.'.'.$status,
            meta: $meta + ['provider' => $provider],
        );
    }

    public function disbursementReversed(string $reference, array $meta = [], string $provider = 'gateway'): void
    {
        $this->notify(
            type: 'payment.disbursement_reversed',
            title: $this->label($provider).' payout reversed',
            body: 'Payout '.$reference.' was reversed after payout. Manual review required.',
            dedupeKey: 'payment.disbursement_reversed.'.$reference,
            meta: $meta + ['provider' => $provider],
        );
    }

    /** A payout attempt closed without payment, but the provider now reports it paid. */
    public function closedAttemptPaid(string $reference, array $meta = [], string $provider = 'gateway'): void
    {
        $this->notify(
            type: 'payment.closed_attempt_paid',
            title: $this->label($provider).' reports a closed payout as paid',
            body: 'Payout '.$reference.' was closed without payment here, but '.$this->label($provider).' now reports it paid. Do not send the withdrawal again until this is checked.',
            dedupeKey: 'payment.closed_attempt_paid.'.$reference,
            meta: $meta + ['provider' => $provider],
            actionRoute: 'admin.reconciliation',
        );
    }

    public function webhookGaveUp(int $webhookId, string $provider, string $event, string $error): void
    {
        $this->notify(
            type: 'payment.webhook_failed',
            title: $this->label($provider).' webhook could not be processed',
            body: 'Webhook #'.$webhookId.' ('.$event.') failed after repeated retries: '.mb_substr($error, 0, 300),
            dedupeKey: 'payment.webhook_failed.'.$webhookId,
            meta: ['webhook_id' => $webhookId, 'provider' => $provider],
            actionRoute: 'admin.reconciliation',
        );
    }

    /** The provider says a deposit we marked failed was paid. Nothing is credited automatically. */
    public function paidAfterFailure(string $reference, string $amount, array $meta = [], string $provider = 'gateway'): void
    {
        $this->notify(
            type: 'payment.paid_after_failure',
            title: $this->label($provider).' paid a deposit marked failed',
            body: 'Deposit '.$reference.' of '.money($amount).' was marked failed, but '.$this->label($provider).' now reports it paid. Review it and use "Verify and credit" on the reconciliation page.',
            dedupeKey: 'payment.paid_after_failure.'.$reference,
            meta: $meta + ['provider' => $provider],
            actionRoute: 'admin.reconciliation',
        );
    }

    /** A payment arrived on a reference the order replaced when the buyer switched gateway. */
    public function paymentOnReplacedReference(string $reference, int $orderId, array $meta = [], string $provider = 'gateway'): void
    {
        $this->notify(
            type: 'payment.replaced_reference_paid',
            title: $this->label($provider).' payment on a replaced checkout',
            body: 'Order #'.$orderId.' received a '.$this->label($provider).' payment on reference '.$reference.', which was replaced when the buyer switched gateway. Check whether the buyer paid twice and refund one payment from the gateway dashboard.',
            dedupeKey: 'payment.replaced_reference_paid.'.$reference,
            meta: $meta + ['provider' => $provider, 'order_id' => $orderId],
            actionRoute: 'admin.reconciliation',
        );
    }

    /** @param  list<string>  $lines */
    public function strandedBalances(array $lines, string $provider = 'bachs'): void
    {
        $this->notify(
            type: 'payment.stranded_balance',
            title: $this->label($provider).' recipient accounts hold platform funds',
            body: count($lines).' recipient account(s) have a balance and no payout in progress: '.implode('; ', array_slice($lines, 0, 5)).'. Review with payments:bachs-stranded and confirm the sweep.',
            dedupeKey: 'payment.stranded_balance.'.now()->format('Y-m-d'),
            meta: ['provider' => $provider, 'count' => count($lines)],
            actionRoute: 'admin.reconciliation',
        );
    }

    public function needsReview(string $kind, string $reference, array $meta = []): void
    {
        $this->notify(
            type: 'payment.needs_review',
            title: 'Payment record needs review',
            body: ucfirst($kind).' '.$reference.' is still unresolved after 3 days of automatic checks. Review it on the reconciliation page.',
            dedupeKey: 'payment.needs_review.'.$kind.'.'.$reference,
            meta: $meta + ['kind' => $kind],
            actionRoute: 'admin.reconciliation',
        );
    }

    /**
     * Payout coverage changed (a route stopped being reported, or the sync failed).
     */
    public function coverageAlert(string $title, string $body, string $dedupeKey, array $meta = []): void
    {
        $this->notify(
            type: 'payment.coverage_changed',
            title: $title,
            body: $body,
            dedupeKey: $dedupeKey,
            meta: $meta,
            actionRoute: 'admin.payout-coverage',
        );
    }

    private function label(string $provider): string
    {
        return match ($provider) {
            'bachs' => 'Bachs',
            'flutterwave' => 'Flutterwave',
            'monnify' => 'Monnify',
            default => 'Gateway',
        };
    }

    private function notify(string $type, string $title, string $body, string $dedupeKey, array $meta, string $actionRoute = 'admin.fundings'): void
    {
        $this->dispatcher->notifyAdmins(
            new NotificationMessage(
                type: $type,
                title: $title,
                body: $body,
                actionUrl: Route::has($actionRoute) ? route($actionRoute) : null,
                meta: array_merge($meta, ['event' => 'payment.webhook']),
                permission: 'finance.manage',
                dedupeKey: $dedupeKey,
                emailSubject: $title,
            ),
            AdminNotificationChannels::FINANCE
        );
    }
}
