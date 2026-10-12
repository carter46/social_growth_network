<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Payments\PaymentEventProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentEventProcessor $processor): Response
    {
        $result = $processor->receive($provider, $request);

        return response($result['status'] === 200 ? 'ok' : 'rejected', $result['status']);
    }
}
