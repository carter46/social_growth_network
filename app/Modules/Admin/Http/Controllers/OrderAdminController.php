<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\SystemSetting;
use App\Models\User;
use App\Modules\Admin\Services\FinancialAuditLog;
use App\Modules\Catalog\Services\PlatformCheckoutService;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function create(): View
    {
        $products = PlatformProduct::query()
            ->visibleToPublic()
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'product_type']);

        $users = User::query()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'email']);

        return view('dashboard.admin.orders.create', [
            'products' => $products,
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'product_slug' => ['required', 'string', 'max:255'],
            'variant_id' => ['nullable', 'integer', 'exists:platform_product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'mark_paid' => ['nullable', 'boolean'],
        ]);

        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $validated['product_slug'])
            ->firstOrFail();

        $variant = null;
        if (! empty($validated['variant_id'])) {
            $variant = PlatformProductVariant::query()
                ->whereKey($validated['variant_id'])
                ->where('platform_product_id', $product->id)
                ->where('is_active', true)
                ->firstOrFail();
        } else {
            $variant = $product->activeVariants()->orderBy('sort_order')->first();
        }

        $quantity = (int) $validated['quantity'];
        if ($variant?->isPerUnit()) {
            $min = $variant->effectiveMinUnits();
            $max = $variant->effectiveMaxUnits();
            if ($quantity < $min || $quantity > $max) {
                return back()->withInput()->with('error', "Units must be between {$min} and {$max}.");
            }
        } else {
            if ($quantity !== 1) {
                return back()->withInput()->with('error', 'Fixed plans must use quantity 1.');
            }
            $quantity = 1;
        }

        $user = User::query()->findOrFail((int) $validated['user_id']);

        $data = [
            'variant_id' => $variant?->id,
            'quantity' => $quantity,
            'domain_mode' => 'none',
            'idempotency_key' => (string) Str::uuid(),
            'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
        ];

        try {
            $order = $this->checkout->createManualBankTransferOrderForUser(
                $user,
                $product,
                $data,
                $request->boolean('mark_paid'),
                (int) auth()->id(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->boolean('mark_paid')) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('status', __('Order created and marked paid. Reference: :ref', ['ref' => $order->reference]));
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', __('Pending order created. Reference: :ref', ['ref' => $order->reference]));
    }

    public function confirmManualPayment(Order $order, Request $request): RedirectResponse
    {
        $this->assertPlatformOrder($order);

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
