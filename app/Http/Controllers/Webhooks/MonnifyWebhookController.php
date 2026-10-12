<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Monnify is retired. Late deliveries are stored for admin review only; nothing is credited
 * or paid from them. Delete this route after the grace period.
 */
class MonnifyWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent();
        $payload = json_decode($raw, true) ?: [];

        Log::notice('payments.monnify.late_webhook', [
            'event' => $payload['eventType'] ?? null,
            'ip' => $request->ip(),
        ]);

        PaymentWebhook::query()->firstOrCreate(
            ['provider' => 'monnify', 'idempotency_key' => 'retired:'.sha1($raw)],
            [
                'event' => mb_substr((string) ($payload['eventType'] ?? 'unknown'), 0, 80),
                'payload' => $payload,
                'headers' => ['ip' => $request->ip()],
                'signature_valid' => null,
                'status' => 'ignored',
                'error' => 'Monnify is retired; logged only.',
                'received_at' => now(),
                'processed_at' => now(),
            ]
        );

        return response('ok', 200);
    }
}
