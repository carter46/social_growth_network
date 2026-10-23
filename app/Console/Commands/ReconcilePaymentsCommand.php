<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PaymentWebhook;
use App\Models\WalletFunding;
use App\Models\Withdrawal;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Catalog\Services\PlatformCheckoutService;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Modules\Wallet\Payments\PaymentEventProcessor;
use App\Modules\Wallet\Services\DepositCheckoutService;
use App\Modules\Wallet\Services\WithdrawalPayoutService;
use App\Services\Notifications\AdminPaymentAlertNotifier;
use Illuminate\Console\Command;
use Throwable;

/**
 * Safety net for missed webhooks: asks each gateway about stuck deposits, gateway orders
 * and live payout attempts, retries failed webhooks and reclaims, and expires fee
 * confirmations nobody answered. Records are checked oldest-checked first, so a backlog of
 * stale rows cannot starve newer ones. Anything unresolved after 3 days is raised for review.
 */
class ReconcilePaymentsCommand extends Command
{
    private const REVIEW_AFTER_DAYS = 3;

    protected $signature = 'payments:reconcile {--minutes=15 : Age threshold for stuck records} {--limit=50}';

    protected $description = 'Check stuck deposits, gateway orders and payouts with their payment gateway';

    public function handle(
        GatewayRegistry $registry,
        DepositCheckoutService $deposits,
        PlatformCheckoutService $checkout,
        WithdrawalPayoutService $payouts,
        PaymentEventProcessor $webhooks,
        AdminPaymentAlertNotifier $alerts,
    ): int {
        $cutoff = now()->subMinutes((int) $this->option('minutes'));
        $reviewCutoff = now()->subDays(self::REVIEW_AFTER_DAYS);
        $limit = (int) $this->option('limit');
        $keys = $registry->keys();

        $this->webhooks($webhooks, $limit);

        $this->oldestFirst(WalletFunding::query()
            ->whereIn('provider', $keys)
            ->whereNotNull('provider_payment_reference')
            ->where(fn ($q) => $q
                ->whereIn('status', ['pending', 'processing'])
                // A deposit marked failed is checked again for a day, in case the provider later reports it paid.
                ->orWhere(fn ($q) => $q->where('status', 'failed')->where('created_at', '>', now()->subDay())
                    ->where(fn ($q) => $q->whereNull('internal_status')->orWhere('internal_status', '!=', DepositCheckoutService::PAID_AFTER_FAILURE))))
            ->where(fn ($q) => $q->whereNull('internal_status')
                ->orWhere('internal_status', '!=', DepositCheckoutService::HELD_OVER_LIMIT))
            ->where('created_at', '<', $cutoff), $limit)
            ->each(function (WalletFunding $funding) use ($deposits, $alerts, $reviewCutoff) {
                $this->stamp($funding);
                if ($funding->created_at->lt($reviewCutoff)) {
                    $alerts->needsReview('deposit', (string) $funding->reference, ['funding_id' => $funding->id]);

                    return;
                }
                try {
                    $deposits->completeFromReturn((string) $funding->provider_payment_reference);
                    $this->line('Deposit '.$funding->reference.' checked');
                } catch (Throwable $e) {
                    $this->error('Deposit '.$funding->id.': '.$e->getMessage());
                }
            });

        $this->oldestFirst(Order::query()
            ->where('payment_method', 'gateway')
            ->whereIn('payment_provider', $keys)
            ->whereIn('status', ['pending', 'processing'])
            ->whereNull('payment_confirmed_at')
            ->where('created_at', '<', $cutoff), $limit)
            ->each(function (Order $order) use ($checkout, $alerts, $reviewCutoff) {
                $this->stamp($order);
                if ($order->created_at->lt($reviewCutoff)) {
                    $alerts->needsReview('order', (string) $order->reference, ['order_id' => $order->id]);

                    return;
                }
                try {
                    $checkout->confirmGatewayOrder($order);
                    $this->line('Order '.$order->id.' checked');
                } catch (Throwable $e) {
                    $this->error('Order '.$order->id.': '.$e->getMessage());
                }
            });

        $this->oldestFirst(WithdrawalPayoutAttempt::query()
            ->whereNotNull('live_flag')
            ->where('updated_at', '<', $cutoff), $limit)
            ->each(function (WithdrawalPayoutAttempt $attempt) use ($payouts, $alerts, $reviewCutoff) {
                $this->stamp($attempt);
                try {
                    $status = $payouts->reconcileAttempt($attempt);
                    $this->line('Payout '.$attempt->reference.': '.$status);
                } catch (Throwable $e) {
                    $this->error('Payout '.$attempt->reference.': '.$e->getMessage());
                }
                // Never closed or refunded automatically: an unknown payout may still be paid.
                if ($attempt->created_at->lt($reviewCutoff) && $attempt->fresh()?->isLive()) {
                    $alerts->needsReview('payout', (string) $attempt->reference, ['withdrawal_id' => $attempt->withdrawal_id]);
                }
            });

        $this->oldestFirst(WithdrawalPayoutAttempt::query()
            ->where('reclaim_pending', true)
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subHour())), $limit)
            ->each(function (WithdrawalPayoutAttempt $attempt) use ($payouts) {
                $this->line('Reclaim '.$attempt->reference.': '.($payouts->reclaim($attempt) ? 'done' : 'pending'));
            });

        Withdrawal::query()
            ->where('internal_status', Withdrawal::INTERNAL_AWAITING_AGENT_CONFIRMATION)
            ->whereNotNull('confirmation_expires_at')
            ->where('confirmation_expires_at', '<', now())
            ->limit($limit)
            ->get()
            ->each(function (Withdrawal $withdrawal) use ($payouts) {
                try {
                    $payouts->expireAgentConfirmation($withdrawal);
                    $this->line('Withdrawal '.$withdrawal->reference.' fee confirmation expired, refunded');
                } catch (Throwable $e) {
                    $this->error('Withdrawal '.$withdrawal->id.': '.$e->getMessage());
                }
            });

        return self::SUCCESS;
    }

    private function webhooks(PaymentEventProcessor $processor, int $limit): void
    {
        // Never processed (queued job never ran) — common when the queue worker is down.
        PaymentWebhook::query()
            ->where('status', 'received')
            ->where('signature_valid', true)
            ->where('received_at', '<', now()->subMinute())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (PaymentWebhook $webhook) use ($processor) {
                try {
                    $processor->process($webhook);
                    $this->line('Webhook #'.$webhook->id.' (received): '.$webhook->fresh()?->status);
                } catch (Throwable $e) {
                    $this->error('Webhook #'.$webhook->id.': '.$e->getMessage());
                }
            });

        PaymentWebhook::query()
            ->where('status', 'failed')
            ->where('attempts', '<', PaymentEventProcessor::MAX_ATTEMPTS)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at')
            ->limit($limit)
            ->get()
            ->each(function (PaymentWebhook $webhook) use ($processor) {
                try {
                    $processor->retry($webhook);
                    $this->line('Webhook #'.$webhook->id.': '.$webhook->fresh()?->status);
                } catch (Throwable $e) {
                    $this->error('Webhook #'.$webhook->id.': '.$e->getMessage());
                }
            });
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<T>  $query
     * @return \Illuminate\Database\Eloquent\Collection<int, T>
     */
    private function oldestFirst($query, int $limit)
    {
        return $query
            ->orderByRaw('last_checked_at IS NOT NULL')
            ->orderBy('last_checked_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    private function stamp(\Illuminate\Database\Eloquent\Model $model): void
    {
        $model->newQuery()->whereKey($model->getKey())->update(['last_checked_at' => now()]);
    }
}
