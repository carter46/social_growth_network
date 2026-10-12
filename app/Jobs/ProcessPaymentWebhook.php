<?php

namespace App\Jobs;

use App\Models\PaymentWebhook;
use App\Modules\Wallet\Payments\PaymentEventProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $webhookId) {}

    public function handle(PaymentEventProcessor $processor): void
    {
        $webhook = PaymentWebhook::find($this->webhookId);
        if (! $webhook) {
            return;
        }

        $processor->process($webhook);
    }
}
