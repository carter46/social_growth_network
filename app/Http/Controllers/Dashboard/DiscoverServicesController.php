<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\EngagementMetric;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PlatformProduct;
use App\Modules\Catalog\Services\CatalogBrowseService;
use App\Modules\Catalog\Services\CatalogContentResolver;
use App\Modules\Catalog\Services\PlatformCheckoutService;
use App\Services\Analytics\UserActivityRecorder;
use App\Support\PlatformProductSlugRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class DiscoverServicesController extends Controller
{
    public function __construct(
        private CatalogBrowseService $browse,
        private CatalogContentResolver $content,
        private UserActivityRecorder $activity,
        private PlatformCheckoutService $checkoutService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->activity->record($user->id, 'viewed', null, 'services.hub');

        $q = $request->string('q')->toString();
        $groups = $this->dashboardGroupCards();

        $searchResults = null;
        if ($q !== '') {
            $searchResults = PlatformProduct::query()
                ->visibleToPublic()
                ->with(['serviceCategory', 'productType.serviceCategory', 'activeVariants', 'heroMedia'])
                ->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                })
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->paginate(12)
                ->withQueryString();
        }

        $wallet = $user->wallet ?? null;

        return view('dashboard.user.discover.services', compact(
            'groups',
            'searchResults',
            'q',
            'wallet',
        ));
    }

    public function browse(Request $request, string $segment): View|RedirectResponse
    {
        $user = $request->user();

        if ($this->browse->isGroup($segment)) {
            $category = $this->browse->usesDbHierarchy()
                ? $this->browse->findServiceCategory($segment)
                : null;

            if ($category?->isMarketplaceLink()) {
                return redirect()->route('dashboard.services');
            }

            $resolved = $this->content->forGroup($segment);
            $typeKeys = $resolved['types'] ?? config('catalog.groups.'.$segment.'.types', []);
            if ($category) {
                $typeKeys = $category->services()->active()->orderBy('sort_order')->pluck('slug')->all();
            }

            $typeFilter = $request->string('type')->toString();
            if ($typeFilter !== '' && in_array($typeFilter, $typeKeys, true)) {
                return $this->browseType($request, $typeFilter, $segment, $resolved);
            }

            // Prefer Category → Product listing when service_category_id is set.
            if ($category && \Illuminate\Support\Facades\Schema::hasColumn('platform_products', 'service_category_id')) {
                return $this->browseCategoryProducts($request, $category, $segment, $resolved, $typeKeys);
            }

            return $this->browseProducts($request, $typeKeys, $segment, $resolved, $typeFilter !== '' ? $typeFilter : null);
        }

        if ($this->browse->isType($segment)) {
            $groupSlug = $this->browse->groupForType($segment);

            return $this->browseType($request, $segment, $groupSlug, $this->content->forType($segment));
        }

        abort(404);
    }

    public function product(Request $request, string $slug): View|RedirectResponse
    {
        $canonical = PlatformProductSlugRedirect::resolve($slug);
        if ($canonical !== null) {
            return redirect()->route('dashboard.services.product', $canonical, 301);
        }

        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $slug)
            ->with(['serviceCategory', 'productType.serviceCategory', 'activeVariants', 'images', 'heroMedia.variants', 'siteIntegration'])
            ->firstOrFail();

        $typeSlug = $product->typeSlug();

        $this->activity->record($request->user()->id, 'viewed', $product, 'service.viewed');

        $groupSlug = $product->categorySlug()
            ?? $product->productType?->serviceCategory?->slug
            ?? $this->browse->groupForType((string) $typeSlug);

        return view('dashboard.user.discover.services-product', [
            'product' => $product,
            'groupSlug' => $groupSlug,
            'groupLabel' => $groupSlug ? ($this->content->forGroup($groupSlug)['label'] ?? $groupSlug) : null,
            'wallet' => $request->user()->wallet,
            'isDomainProduct' => false,
            'domainTlds' => [],
            'domainTldsAdvanced' => [],
        ]);
    }

    public function domainTlds(): JsonResponse
    {
        abort(410, 'Domain registration is no longer available.');
    }

    public function domainQuote(Request $request): JsonResponse
    {
        abort(422, 'Domain quotes are no longer available.');
    }

    public function domainConnectScan(Request $request): JsonResponse
    {
        abort(422, 'Domain connect is no longer available.');
    }

    public function checkout(Request $request, string $slug): View|RedirectResponse
    {
        $canonical = PlatformProductSlugRedirect::resolve($slug);
        if ($canonical !== null) {
            return redirect()->route('dashboard.services.checkout', array_merge(
                ['slug' => $canonical],
                $request->query()
            ), 301);
        }

        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $slug)
            ->with('activeVariants')
            ->firstOrFail();

        $variants = $product->activeVariants->sortBy('price')->values();
        $requestedVariantId = $request->integer('variant') ?: null;
        $defaultVariant = $requestedVariantId
            ? ($variants->firstWhere('id', $requestedVariantId) ?? $variants->first())
            : $variants->first();

        if ($requestedVariantId && (int) $defaultVariant?->id !== $requestedVariantId) {
            return redirect()
                ->route('dashboard.services.product', $product->slug)
                ->with('error', 'Selected plan is unavailable.');
        }

        $showPlanSummary = $requestedVariantId !== null;

        $this->activity->record($request->user()->id, 'viewed', $product, 'service.checkout');

        $renewTool = null;
        if ($request->filled('renew')) {
            $renewTool = \App\Models\UserTool::query()
                ->where('public_id', $request->string('renew')->toString())
                ->where('user_id', $request->user()->id)
                ->where('platform_product_id', $product->id)
                ->first();
        }

        return view('dashboard.user.discover.services-checkout', [
            'product' => $product,
            'variants' => $variants,
            'defaultVariantId' => $defaultVariant?->id,
            'basePrice' => (float) $product->displayPrice(),
            'showPlanSummary' => $showPlanSummary,
            'isWebsitePackage' => false,
            'isDomainProduct' => false,
            'requireDomainChoice' => false,
            'domainTlds' => [],
            'domainTldsAdvanced' => [],
            'quoteToken' => null,
            'quotedFqdn' => null,
            'quotedPrice' => null,
            'idempotencyKey' => (string) Str::uuid(),
            'wallet' => $request->user()->wallet,
            'renewTool' => $renewTool,
            'gatewayEnabled' => $this->checkoutService->gatewayEnabled(),
            'manualBankTransferEnabled' => $this->checkoutService->manualBankTransferEnabledForCheckout(),
        ]);
    }

    public function purchase(Request $request, string $slug): RedirectResponse
    {
        // Canonicalize in place so POST body is preserved.
        $slug = PlatformProductSlugRedirect::canonical($slug);

        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $slug)
            ->firstOrFail();

        $gatewayEnabled = $this->checkoutService->gatewayEnabled();
        $manualBankEnabled = $this->checkoutService->manualBankTransferEnabledForCheckout();
        $hasWallet = (bool) $request->user()->wallet;

        $allowedMethods = [];
        if ($hasWallet) {
            $allowedMethods[] = 'wallet';
        }
        if ($gatewayEnabled) {
            $allowedMethods[] = 'gateway';
        }
        if ($manualBankEnabled) {
            $allowedMethods[] = Order::PAYMENT_MANUAL_BANK_TRANSFER;
        }

        if ($allowedMethods === []) {
            return back()->withInput()->with('error', 'No payment method is available. Create a wallet, enable card/transfer checkout, or ask support about bank transfer for orders.');
        }

        $rules = [
            'variant_id' => ['nullable', 'integer', 'exists:platform_product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'idempotency_key' => ['required', 'string', 'uuid', 'max:64'],
            'renew_user_tool_id' => ['nullable', 'integer', 'exists:user_tools,id'],
            'payment_method' => ['nullable', 'in:'.implode(',', $allowedMethods)],
            'target_url' => [
                Rule::requiredIf(fn () => (bool) $product->is_campaign && EngagementMetric::fromProductSlug($product->slug)),
                'nullable',
                'string',
                'url',
                'max:2048',
            ],
        ];

        $data = $request->validate($rules);

        $data['payment_method'] = $data['payment_method']
            ?? ($hasWallet ? 'wallet' : ($gatewayEnabled ? 'gateway' : ($manualBankEnabled ? Order::PAYMENT_MANUAL_BANK_TRANSFER : null)));

        if (! $data['payment_method'] || ! in_array($data['payment_method'], $allowedMethods, true)) {
            return back()->withInput()->with('error', 'Choose a valid payment method.');
        }

        if (($data['payment_method'] ?? '') === 'gateway') {
            $data['redirect_url'] = route('dashboard.services.payment-callback', ['slug' => $product->slug]);
        }

        try {
            $order = $this->checkoutService->purchase($request->user(), $product, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Dashboard platform checkout failed', [
                'slug' => $slug,
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Checkout failed. Please try again or contact support.');
        }

        if ($order->payment_method === 'gateway' && in_array($order->status, ['pending', 'processing'], true)) {
            if (! filled($order->checkout_url)) {
                return back()->withInput()->with('error', 'Unable to start payment gateway checkout.');
            }

            return redirect()->away($order->checkout_url);
        }

        if ($order->payment_method === Order::PAYMENT_MANUAL_BANK_TRANSFER && $order->status === 'pending') {
            return redirect()
                ->route('dashboard.orders.manual-payment', $order)
                ->with('status', 'Order '.$order->reference.' created. Complete your bank transfer using the instructions below.');
        }

        if (! empty($data['renew_user_tool_id'])) {
            $tool = \App\Models\UserTool::query()->find($data['renew_user_tool_id']);

            return redirect()
                ->route('dashboard.my-tools.show', $tool)
                ->with('success', 'Subscription renewed. Order '.$order->reference.'.');
        }

        if ($product->is_campaign) {
            return redirect()
                ->route('dashboard.campaigns')
                ->with('success', 'Order '.$order->reference.' placed. Your campaign is live for agents.');
        }

        $tool = \App\Models\UserTool::query()
            ->where('order_id', $order->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($tool) {
            return redirect()
                ->route('dashboard.service-orders')
                ->with('success', 'Order '.$order->reference.' placed successfully.');
        }

        return redirect()
            ->route('dashboard.service-orders')
            ->with('success', 'Order '.$order->reference.' placed successfully.');
    }

    public function paymentCallback(Request $request, string $slug): RedirectResponse
    {
        $slug = PlatformProductSlugRedirect::canonical($slug);

        $paymentReference = $request->string('paymentReference')->toString()
            ?: $request->string('payment_reference')->toString();

        if ($paymentReference === '') {
            return redirect()
                ->route('dashboard.services.checkout', $slug)
                ->with('error', 'Payment reference missing. If you paid, wait a moment and check My Orders or My Campaigns.');
        }

        $order = \App\Models\Order::query()
            ->where('provider_payment_reference', $paymentReference)
            ->where('user_id', $request->user()->id)
            ->where('source', 'platform')
            ->first();

        if (! $order) {
            return redirect()
                ->route('dashboard.services.checkout', $slug)
                ->with('error', 'Order not found for this payment.');
        }

        try {
            $rail = app(\App\Modules\Wallet\Payments\Contracts\PaymentRailInterface::class);
            $verified = $rail->verifyTransaction($paymentReference);
            $status = strtoupper((string) ($verified['paymentStatus'] ?? ''));
            $amountPaid = (string) ($verified['amountPaid'] ?? '0');

            if (in_array($status, ['PAID', 'SUCCESS', 'COMPLETED'], true)
                && bccomp($amountPaid, (string) $order->total_amount, 2) === 0) {
                $order = $this->checkoutService->fulfillPaidGatewayOrder($order);
            }
        } catch (\Throwable $e) {
            Log::warning('Platform gateway callback verify failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        $order->refresh();

        if ($order->status !== 'paid') {
            return redirect()
                ->route('dashboard.services.checkout', $slug)
                ->with('error', 'Payment is still pending. If you completed payment, refresh shortly or check Service orders.');
        }

        $tool = \App\Models\UserTool::query()
            ->where('order_id', $order->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($tool) {
            return redirect()
                ->route('dashboard.my-tools.show', $tool)
                ->with('success', 'Payment confirmed. Order '.$order->reference.'.');
        }

        return redirect()
            ->route('dashboard.service-orders')
            ->with('success', 'Payment confirmed. Order '.$order->reference.'.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function dashboardGroupCards()
    {
        return $this->browse->groupCards($this->content)->map(function (array $group) {
            $slug = $group['slug'] ?? null;
            if (! $slug) {
                return $group;
            }

            $group['href'] = route('dashboard.services.browse', $slug);

            return $group;
        });
    }

    /**
     * @param  list<string>  $typeKeys
     * @param  array<string, mixed>  $resolved
     */
    private function browseType(Request $request, string $type, ?string $groupSlug, array $resolved): View
    {
        return $this->browseProducts($request, [$type], $groupSlug ?? $type, $resolved, null);
    }

    /**
     * @param  list<string>  $typeKeys
     * @param  array<string, mixed>  $resolved
     */
    private function browseCategoryProducts(
        Request $request,
        \App\Models\ServiceCategory $category,
        string $segment,
        array $resolved,
        array $typeKeys,
    ): View {
        $q = $request->string('q')->toString();

        $products = PlatformProduct::query()
            ->visibleToPublic()
            ->ofCategory($category)
            ->with(['serviceCategory', 'productType.serviceCategory', 'activeVariants', 'heroMedia.variants'])
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        $this->activity->record($request->user()->id, 'viewed', null, 'services.browse.'.$segment);

        return view('dashboard.user.discover.services-browse', [
            'segment' => $segment,
            'title' => $resolved['label'] ?? $segment,
            'subtitle' => $resolved['short_description'] ?? null,
            'typeCards' => null,
            'products' => $products,
            'filters' => ['q' => $q, 'type' => null],
            'typeKeys' => $typeKeys,
            'wallet' => $request->user()->wallet,
        ]);
    }

    /**
     * @param  list<string>  $typeKeys
     * @param  array<string, mixed>  $resolved
     */
    private function browseProducts(
        Request $request,
        array $typeKeys,
        string $segment,
        array $resolved,
        ?string $typeFilter,
    ): View {
        $q = $request->string('q')->toString();
        $activeTypes = $typeFilter ? [$typeFilter] : $typeKeys;

        $products = PlatformProduct::query()
            ->visibleToPublic()
            ->ofTypeMany($activeTypes)
            ->with(['serviceCategory', 'productType.serviceCategory', 'activeVariants', 'heroMedia.variants'])
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        $this->activity->record($request->user()->id, 'viewed', null, 'services.browse.'.$segment);

        return view('dashboard.user.discover.services-browse', [
            'segment' => $segment,
            'title' => $resolved['label'] ?? $segment,
            'subtitle' => $resolved['short_description'] ?? null,
            'typeCards' => null,
            'products' => $products,
            'filters' => ['q' => $q, 'type' => $typeFilter],
            'typeKeys' => $typeKeys,
            'wallet' => $request->user()->wallet,
        ]);
    }
}
