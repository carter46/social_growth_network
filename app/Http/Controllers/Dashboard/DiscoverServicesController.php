<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\EngagementMetric;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\PlatformProduct;
use App\Modules\Catalog\Services\CatalogBrowseService;
use App\Modules\Catalog\Services\CatalogContentResolver;
use App\Modules\Catalog\Services\PlatformCheckoutService;
use App\Services\Analytics\UserActivityRecorder;
use App\Services\Campaigns\CampaignBriefException;
use App\Services\Catalog\PackageLinkValidator;
use App\Services\Catalog\PackageQuoteException;
use App\Services\Catalog\PackageQuoteService;
use App\Services\Catalog\PurchaseDraftService;
use App\Services\Engagement\TargetUrlValidator;
use App\Services\Engagement\YoutubeLookupService;
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

        $products = PlatformProduct::query()
            ->visibleToPublic()
            ->with(['serviceCategory', 'productType.serviceCategory', 'activeVariants', 'heroMedia'])
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

        $wallet = $user->wallet ?? null;

        return view('dashboard.user.discover.services', compact(
            'products',
            'q',
            'wallet',
        ));
    }

    public function browse(Request $request, string $segment): View|RedirectResponse
    {
        if (PlatformProductSlugRedirect::isRetiredPlatformSlug($segment)) {
            return redirect()->route('dashboard.services')->with('error', 'That service is no longer offered.');
        }

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
        if (PlatformProductSlugRedirect::isRetiredPlatformSlug($slug)) {
            return redirect()->route('dashboard.services')->with('error', 'That service is no longer offered.');
        }

        $canonical = PlatformProductSlugRedirect::resolve($slug);
        if ($canonical !== null) {
            return redirect()->route('dashboard.services.product', $canonical, 301);
        }

        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $slug)
            ->with(['serviceCategory', 'productType.serviceCategory', 'activeVariants', 'images', 'heroMedia.variants'])
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
        ]);
    }

    /**
     * Client-side embed template for campaign target_url preview (no remote oEmbed APIs).
     */
    public function urlPreview(Request $request, \App\Services\Engagement\CheckoutUrlPreviewResolver $previews): JsonResponse
    {
        $data = $request->validate([
            'url' => ['nullable', 'string', 'max:2048'],
            'product_slug' => ['required', 'string', 'max:120'],
        ]);

        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $data['product_slug'])
            ->first();

        $isCampaignStyle = $product
            && (
                (bool) ($product->is_campaign ?? false)
                || $product->taskProfile()->legacyMetric() !== null
            );

        if (! $isCampaignStyle) {
            return response()->json([
                'mode' => 'open_url',
                'platform' => 'unknown',
                'open_url' => (string) ($data['url'] ?? ''),
                'note' => 'Preview is only available for campaign destinations.',
            ]);
        }

        $payload = $previews->resolve($data['url'] ?? null, $product->slug);

        return response()->json($payload);
    }

    public function checkout(Request $request, string $slug): View|RedirectResponse
    {
        if (PlatformProductSlugRedirect::isRetiredPlatformSlug($slug)) {
            return redirect()->route('dashboard.services')->with('error', 'That service is no longer offered.');
        }

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

        $defaultVariant = $product->pricingVariant();
        $variants = collect($defaultVariant ? [$defaultVariant] : []);
        $metric = $product->taskProfile()->legacyMetric();

        if (! $product->isPurchasable()) {
            return redirect()
                ->route('dashboard.services.product', $product->slug)
                ->with('error', 'This product is not available to buy yet. Please try again later.');
        }

        $showPlanSummary = true;
        $selectedQty = is_array($request->query('qty')) ? $request->query('qty') : [];
        $briefInput = [
            'target_url' => (string) $request->query('target_url', ''),
            'channel_url' => (string) $request->query('channel_url', ''),
            'comment_instructions' => (string) $request->query('comment_instructions', ''),
        ];
        $backInput = $briefInput + ['units' => (string) $request->query('units', ''), 'qty' => $selectedQty];

        try {
            $quote = app(PackageQuoteService::class)->quote($product, (string) $request->query('units', ''), $selectedQty, $defaultVariant);
        } catch (PackageQuoteException $e) {
            return redirect()
                ->route('dashboard.services.product', $product->slug)
                ->withInput($backInput)
                ->withErrors([$e->field => $e->getMessage()])
                ->with('error', $e->getMessage());
        }
        $selectedUnits = $quote['main_qty'];

        $targetUrl = null;
        $brief = [];
        if ($product->requiresTargetUrl()) {
            try {
                $brief = app(PackageLinkValidator::class)->validate($product, $briefInput);
                $targetUrl = TargetUrlValidator::normalize($briefInput['target_url']);
            } catch (CampaignBriefException $e) {
                return redirect()
                    ->route('dashboard.services.product', $product->slug)
                    ->withInput($backInput)
                    ->withErrors([$e->field => $e->getMessage()]);
            }
        }

        $this->activity->record($request->user()->id, 'viewed', $product, 'service.checkout');

        return view('dashboard.user.discover.services-checkout', [
            'product' => $product,
            'variants' => $variants,
            'defaultVariantId' => $defaultVariant?->id,
            'selectedUnits' => $selectedUnits,
            'engagementMetric' => $metric,
            'targetUrl' => $targetUrl,
            'linkPreview' => $targetUrl ? $this->linkPreview($product, $targetUrl, $brief['channel_url'] ?? null) : [],
            'taskProfile' => $product->taskProfile(),
            'campaignBrief' => $brief,
            'packageQuote' => $quote,
            'packageLayout' => app(PackageQuoteService::class)->layout($product),
            'basePrice' => (float) $product->displayPrice(),
            'showPlanSummary' => $showPlanSummary,
            'idempotencyKey' => (string) Str::uuid(),
            'wallet' => $request->user()->wallet,
            'gatewayEnabled' => $this->checkoutService->gatewayEnabled(),
            'gateways' => $this->checkoutService->availableGateways(),
            'defaultGateway' => $this->checkoutService->defaultGatewayKey(),
            'manualBankTransferEnabled' => $this->checkoutService->manualBankTransferEnabledForCheckout(),
        ]);
    }

    public function purchase(Request $request, string $slug): RedirectResponse
    {
        if (PlatformProductSlugRedirect::isRetiredPlatformSlug($slug)) {
            return redirect()->route('dashboard.services')->with('error', 'That service is no longer offered.');
        }

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
            return back()->withInput()->with('error', 'No payment method is available right now. Create a wallet or contact support for help.');
        }

        $rules = [
            'variant_id' => ['nullable', 'integer', 'exists:platform_product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'qty' => ['nullable', 'array'],
            'qty.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'idempotency_key' => ['required', 'string', 'uuid', 'max:64'],
            'payment_method' => ['nullable', 'in:'.implode(',', $allowedMethods)],
            'gateway' => ['nullable', 'string', 'max:40'],
            'target_url' => [
                Rule::requiredIf(fn () => $product->taskProfile()->legacyMetric() !== null),
                'nullable',
                'string',
                'url',
                'max:2048',
            ],
            'channel_url' => [
                Rule::requiredIf(fn () => $product->taskProfile()->needsChannelUrl()),
                'nullable',
                'string',
                'url',
                'max:500',
            ],
            'comment_instructions' => [
                Rule::requiredIf(fn () => $product->taskProfile()->needsCommentInstructions()),
                'nullable',
                'string',
                'max:'.\App\Services\Campaigns\CampaignBriefService::COMMENT_INSTRUCTIONS_MAX,
            ],
        ];

        $data = $request->validate($rules);

        $variant = $product->pricingVariant();
        if (! $product->isPurchasable()) {
            return back()->withInput()->with('error', 'This product is not available to buy yet. Please try again later.');
        }

        $data['task_quantities'] = array_map('intval', (array) ($data['qty'] ?? []));
        unset($data['qty']);
        try {
            app(PackageQuoteService::class)->quote($product, (string) $data['quantity'], $data['task_quantities'], $variant);
        } catch (PackageQuoteException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($product->requiresTargetUrl()) {
            try {
                $data['campaign_brief'] = app(PackageLinkValidator::class)->validate($product, $data);
            } catch (CampaignBriefException $e) {
                return back()->withInput()->withErrors([$e->field => $e->getMessage()])->with('error', $e->getMessage());
            }
        }

        $data['variant_id'] = $variant->id;

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
            app(PurchaseDraftService::class)->clear($product->slug);
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
                return back()->withInput()->with('error', 'We couldn\'t start your payment. Please try again.');
            }

            return redirect()->away($order->checkout_url);
        }

        if ($order->payment_method === Order::PAYMENT_MANUAL_BANK_TRANSFER && $order->status === 'pending') {
            return redirect()
                ->route('dashboard.orders.manual-payment', $order)
                ->with('status', 'Order '.$order->reference.' created. Complete your bank transfer using the instructions below.');
        }

        if ($product->isCampaignProduct()) {
            return $this->campaignRedirect($order)
                ->with('success', 'Order '.$order->reference.' placed. Your campaign is live.');
        }

        return redirect()
            ->route('dashboard.service-orders')
            ->with('success', 'Order '.$order->reference.' placed successfully.');
    }

    public function paymentCallback(Request $request, string $slug): RedirectResponse
    {
        $slug = PlatformProductSlugRedirect::canonical($slug);
        $backUrl = PlatformProductSlugRedirect::isRetiredPlatformSlug($slug)
            ? route('dashboard.service-orders')
            : route('dashboard.services.checkout', $slug);

        $paymentReference = $request->string('reference')->toString()
            ?: $request->string('tx_ref')->toString();

        if ($paymentReference === '') {
            return redirect()
                ->to($backUrl)
                ->with('error', 'Payment reference missing. If you paid, wait a moment and check My Orders or My Campaigns.');
        }

        $order = \App\Models\Order::query()
            ->where('provider_payment_reference', $paymentReference)
            ->where('user_id', $request->user()->id)
            ->where('source', 'platform')
            ->first();

        if (! $order) {
            return redirect()
                ->to($backUrl)
                ->with('error', 'Order not found for this payment.');
        }

        try {
            $order = $this->checkoutService->confirmGatewayOrder($order);
        } catch (\Throwable $e) {
            Log::warning('Platform gateway callback verify failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        $order->refresh();

        if ($order->status !== 'paid') {
            return redirect()
                ->to($backUrl)
                ->with('error', 'Payment is still pending. If you completed payment, refresh shortly or check My Orders or My Campaigns.');
        }

        if ($this->orderCampaign($order) !== null) {
            return $this->campaignRedirect($order)
                ->with('success', 'Payment confirmed. Order '.$order->reference.'. Your campaign is live.');
        }

        return redirect()
            ->route('dashboard.service-orders')
            ->with('success', 'Payment confirmed. Order '.$order->reference.'.');
    }

    /**
     * Video and channel cards for checkout. The link check before checkout already looked
     * these up, so they normally come from the lookup cache.
     *
     * @return array{video?: ?array<string, mixed>, target_channel?: ?array<string, mixed>, channel?: ?array<string, mixed>}
     */
    private function linkPreview(PlatformProduct $product, string $targetUrl, mixed $channelUrl): array
    {
        $profile = $product->taskProfile();
        $tasks = $profile->tasks();
        if ($tasks === []) {
            return [];
        }

        try {
            $lookup = app(YoutubeLookupService::class);
            if ($profile->targetIsChannel()) {
                return ['target_channel' => $lookup->channel($targetUrl, $tasks, 0)['channel'] ?? null];
            }

            return [
                'video' => $lookup->video($targetUrl, $tasks)['video'] ?? null,
                'channel' => filled($channelUrl) ? ($lookup->channel((string) $channelUrl, $tasks, 0)['channel'] ?? null) : null,
            ];
        } catch (\Throwable $e) {
            Log::info('Checkout link preview unavailable', ['product' => $product->slug, 'exception' => $e::class]);

            return [];
        }
    }

    private function orderCampaign(Order $order): ?Campaign
    {
        return Campaign::query()
            ->where('creator_id', $order->user_id)
            ->whereIn('order_item_id', $order->items()->select('id'))
            ->orderBy('id')
            ->first();
    }

    private function campaignRedirect(Order $order): RedirectResponse
    {
        $campaign = $this->orderCampaign($order);

        return $campaign
            ? redirect()->route('dashboard.campaigns.show', $campaign)
            : redirect()->route('dashboard.campaigns');
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
