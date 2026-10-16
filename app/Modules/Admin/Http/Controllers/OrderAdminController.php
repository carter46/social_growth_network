<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Modules\Admin\Services\FinancialAuditLog;
use App\Modules\Catalog\Services\PlatformCheckoutService;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderAdminController extends Controller
{
    public function __construct(
        private PlatformCheckoutService $checkout,
        private FinancialAuditLog $financialAudit,
        private WalletService $walletService,
    ) {}

    public function index(Request $request): View
    {
        $query = Order::query()
            ->with(['user', 'items'])
            ->where('source', 'platform')
            ->orderByDesc('created_at');

        if ($request->string('filter')->toString() === 'awaiting_bank') {
            $query->where('payment_method', Order::PAYMENT_MANUAL_BANK_TRANSFER)
                ->where('status', 'pending');
        } elseif ($request->string('filter')->toString() === 'failed_bank') {
            $query->where('payment_method', Order::PAYMENT_MANUAL_BANK_TRANSFER)
                ->where('status', 'cancelled');
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('dashboard.admin.orders.index', [
            'orders' => $orders,
            'filter' => $request->string('filter')->toString(),
        ]);
    }

    public function show(Order $order): View
    {
        $this->assertPlatformOrder($order);
        $order->load(['user', 'items.variant', 'paymentConfirmer']);

        return view('dashboard.admin.orders.show', [
            'order' => $order,
            'bankDetails' => SystemSetting::manualBankTransferDetails(),
        ]);
    }

    public function confirmManualPayment(Order $order, Request $request): RedirectResponse
    {
        $this->assertPlatformOrder($order);

        if ($this->checkout->orderHasRetiredProducts($order)) {
            return back()->with('error', __('This order is for a service that is no longer offered. Cancel it instead of confirming payment.'));
        }

        $platformBefore = $this->walletService->getPlatformWallet()->replicate();

        try {
            $this->checkout->fulfillPaidCatalogOrder(
                $order,
                [Order::PAYMENT_MANUAL_BANK_TRANSFER],
                (int) auth()->id(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $order->refresh();
        $platformAfter = $this->walletService->getPlatformWallet()->fresh();

        $this->financialAudit->logMoneyAction(
            auth()->id(),
            'order.manual_payment.confirmed',
            $order,
            $platformBefore,
            $platformAfter,
            $request->ip(),
            $request->userAgent(),
            $request->header('X-Request-Id'),
            ['order_id' => $order->id, 'reference' => $order->reference],
        );

        return back()->with('status', __('Payment confirmed and order fulfilled.'));
    }

    public function rejectManualPayment(Order $order, Request $request): RedirectResponse
    {
        $this->assertPlatformOrder($order);

        try {
            $this->checkout->cancelManualBankTransferOrder($order, $request->input('notes'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.orders')
            ->with('status', __('Order cancelled.'));
    }

    public function downloadPaymentProof(Order $order): StreamedResponse|RedirectResponse
    {
        $this->assertPlatformOrder($order);

        if ($order->payment_method !== Order::PAYMENT_MANUAL_BANK_TRANSFER) {
            return back()->with('error', __('This order has no manual bank transfer proof.'));
        }

        $meta = $order->payment_metadata ?? [];
        $path = $meta['proof_path'] ?? null;
        $disk = $meta['proof_disk'] ?? config('media.documents.disk', 'local');

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            return back()->with('error', __('Payment proof not found.'));
        }

        return Storage::disk($disk)->download($path, 'order-proof-'.$order->reference);
    }

    private function assertPlatformOrder(Order $order): void
    {
        if ($order->source !== 'platform') {
            abort(404);
        }
    }
}
