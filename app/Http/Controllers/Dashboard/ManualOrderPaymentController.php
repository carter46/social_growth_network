<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Modules\Catalog\Services\PlatformCheckoutService;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualOrderPaymentController extends Controller
{
    public function __construct(
        private PlatformCheckoutService $checkout,
        private MediaUploadService $media,
    ) {}

    public function show(Order $order): View|RedirectResponse
    {
        if (! $this->canAccessManualPayment($order)) {
            return $this->denyManualPayment($order);
        }

        if (! $order->payment_submitted_at && $this->checkout->orderHasRetiredProducts($order)) {
            return redirect()->route('dashboard.service-orders')
                ->with('error', __('Order :ref is for a service that is no longer offered, so it cannot be paid.', ['ref' => $order->reference]));
        }

        $order = $this->checkout->initializeManualPaymentWindow($order);

        if (! $order->payment_submitted_at) {
            $result = $this->checkout->processManualPaymentExpiry($order);
            if ($result['status'] === 'cancelled') {
                return redirect()
                    ->route('dashboard')
                    ->with('manual_payment_order_cancelled', $result['message'] ?? 'Your order was cancelled.');
            }
            $order = $order->fresh();
        }

        return view('dashboard.user.orders.manual-payment', [
            'order' => $order,
            'bankDetails' => SystemSetting::manualBankTransferDetails(),
            'secondsRemaining' => $this->checkout->manualPaymentSecondsRemaining($order),
            'paymentExpired' => $this->checkout->isManualPaymentExpired($order),
            'paymentSession' => (int) ($order->payment_metadata['manual_payment_session'] ?? 1),
            'maxPaymentSessions' => PlatformCheckoutService::MANUAL_PAYMENT_MAX_SESSIONS,
        ]);
    }

    public function expireSession(Order $order): JsonResponse
    {
        if (! $this->canAccessManualPayment($order)) {
            abort(403);
        }

        $result = $this->checkout->processManualPaymentExpiry($order);

        return response()->json(array_merge($result, [
            'seconds_remaining' => $this->checkout->manualPaymentSecondsRemaining($order->fresh()),
        ]));
    }

    public function restartSession(Order $order): JsonResponse
    {
        if (! $this->canAccessManualPayment($order)) {
            abort(403);
        }

        try {
            $order = $this->checkout->restartManualPaymentWindow($order);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'status' => 'active',
            'seconds_remaining' => $this->checkout->manualPaymentSecondsRemaining($order),
            'session' => (int) ($order->payment_metadata['manual_payment_session'] ?? 1),
        ]);
    }

    public function submitProof(Order $order, Request $request): RedirectResponse|JsonResponse
    {
        if (! $this->canAccessManualPayment($order)) {
            return $this->denyManualPayment($order);
        }

        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $proofMeta = [];

        if ($request->hasFile('proof')) {
            $stored = $this->media->storeDocument($request->file('proof'), $request->user());
            $proofMeta['proof_path'] = $stored['path'];
            $proofMeta['proof_disk'] = $stored['disk'];
            $proofMeta['proof_media_asset_id'] = $stored['media_asset_id'];
        }

        try {
            $this->checkout->submitManualBankTransferProof($order, $proofMeta);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = __('Your payment is being processed. We will review your transfer and confirm your order shortly.');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return back()->with('status', $message);
    }

    private function canAccessManualPayment(Order $order): bool
    {
        return (int) $order->user_id === (int) auth()->id()
            && $order->source === 'platform'
            && $order->payment_method === Order::PAYMENT_MANUAL_BANK_TRANSFER
            && $order->status === 'pending';
    }

    private function denyManualPayment(Order $order): RedirectResponse
    {
        if ((int) $order->user_id !== (int) auth()->id()) {
            abort(403);
        }

        if ($order->payment_method !== Order::PAYMENT_MANUAL_BANK_TRANSFER) {
            return redirect()->route('dashboard.service-orders')
                ->with('error', __('This order does not use manual bank transfer.'));
        }

        if ($order->status === 'paid') {
            return redirect()->route('dashboard.service-orders')
                ->with('status', __('Order :ref is already paid.', ['ref' => $order->reference]));
        }

        if ($order->status === 'cancelled') {
            return redirect()->route('dashboard')
                ->with('manual_payment_order_cancelled', __('This order was cancelled.'));
        }

        abort(404);
    }
}
