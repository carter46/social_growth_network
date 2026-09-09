<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Enums\UserToolStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PlatformProduct;
use App\Models\PlatformProductVariant;
use App\Models\ProductType;
use App\Models\ServiceCategory;
use App\Models\SiteIntegrationCheckLog;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use App\Modules\Catalog\Services\PlatformCheckoutService;
use App\Modules\Wallet\Services\WalletProvisioningService;
use App\Models\DomainConnection;
use App\Services\Domains\DomainConnectionService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationEmailRenderer;
use App\Services\Notifications\NotificationMessage;
use App\Services\SiteIntegrations\SubscriptionSyncService;
use App\Services\SiteIntegrations\UserToolLifecycleNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(
        private AuditLogService $audit,
        private WalletProvisioningService $walletProvisioning,
        private PlatformCheckoutService $checkout,
        private SubscriptionSyncService $subscriptionSync,
        private NotificationDispatcher $notificationDispatcher,
        private NotificationEmailRenderer $notificationEmailRenderer,
        private UserToolLifecycleNotifier $toolLifecycleNotifier,
    ) {}

    public function create(): View
    {
        return view('dashboard.admin.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'country' => isset($data['country']) ? strtoupper($data['country']) : null,
            'bio' => $data['bio'] ?? null,
            'terms_accepted_at' => now(),
        ]);

        if ($request->boolean('email_verified')) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->assignRole('user');

        if (isset($data['kyc_level'])) {
            $user->forceFill(['kyc_level' => (int) $data['kyc_level']])->save();
        }

        if ($request->boolean('is_suspended')) {
            $user->suspend(auth()->id());
        }

        $walletCreated = false;
        if ($request->boolean('provision_wallet')) {
            try {
                $this->walletProvisioning->createWallet($user->fresh());
                $walletCreated = true;
            } catch (\Throwable $e) {
                $this->audit->log(auth()->id(), 'user.created', $user, null, [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'wallet_error' => $e->getMessage(),
                ], $request->ip());

                return redirect()
                    ->route('admin.users.show', $user)
                    ->with('status', __('User created, but wallet was not provisioned: :error', ['error' => $e->getMessage()]));
            }
        }

        $this->audit->log(auth()->id(), 'user.created', $user, null, [
            'user_id' => $user->id,
            'email' => $user->email,
            'email_verified' => $request->boolean('email_verified'),
            'wallet_created' => $walletCreated,
            'kyc_level' => $user->fresh()->kyc_level,
        ], $request->ip());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', __('User created.'));
    }

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: 'active';
        if (! in_array($status, ['active', 'suspended'], true)) {
            $status = 'active';
        }

        $roleFilter = $request->string('role')->toString() ?: 'all';
        if (! in_array($roleFilter, ['all', 'creator', 'agent'], true)) {
            $roleFilter = 'all';
        }

        $roleName = match ($roleFilter) {
            'creator' => 'user',
            'agent' => 'agent',
            default => null,
        };

        $base = User::query()
            ->role($roleName ? [$roleName] : ['user', 'agent'])
            ->notAnonymized();

        $activeCount = (clone $base)->where('is_suspended', false)->count();
        $suspendedCount = (clone $base)->where('is_suspended', true)->count();

        $search = trim($request->string('q')->toString());

        $users = User::query()
            ->role($roleName ? [$roleName] : ['user', 'agent'])
            ->notAnonymized()
            ->when($status === 'suspended', fn ($q) => $q->where('is_suspended', true))
            ->when($status === 'active', fn ($q) => $q->where('is_suspended', false))
            ->when($search !== '', fn ($q) => \App\Support\Search::apply($q, $search))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $data = [
            'users' => $users,
            'status' => $status,
            'activeCount' => $activeCount,
            'suspendedCount' => $suspendedCount,
            'search' => $search,
            'roleFilter' => $roleFilter,
        ];

        if ($this->wantsTabPartial($request)) {
            return view('dashboard.admin.users._table', $data);
        }

        return view('dashboard.admin.users', $data);
    }

    public function show(User $user, Request $request): View
    {
        $this->ensureMember($user);

        $user->load('wallet');

        return $this->userTabView($request, $user, 'overview', [
            'wallet' => $user->wallet,
            'recentTransactions' => $user->transactions()->orderByDesc('created_at')->limit(5)->get(),
            'orderCount' => $user->orders()->count(),
            'ticketCount' => $user->supportTickets()->count(),
        ]);
    }

    public function wallet(User $user, Request $request): View
    {
        $this->ensureMember($user);
        $user->load('wallet');

        return $this->userTabView($request, $user, 'wallet', [
            'wallet' => $user->wallet,
        ]);
    }

    public function transactions(User $user, Request $request): View
    {
        $this->ensureMember($user);

        return $this->userTabView($request, $user, 'transactions', [
            'transactions' => $user->transactions()->orderByDesc('created_at')->paginate(20),
        ]);
    }

    public function orders(User $user, Request $request): View
    {
        $this->ensureMember($user);

        return $this->userTabView($request, $user, 'orders', [
            'orders' => $user->orders()->with('listing')->orderByDesc('created_at')->paginate(20),
        ]);
    }

    public function tools(User $user, Request $request): View
    {
        $this->ensureMember($user);

        return $this->userTabView($request, $user, 'tools', [
            'tools' => \App\Models\UserTool::query()
                ->where('user_id', $user->id)
                ->with(['product', 'variant', 'integration'])
                ->orderByDesc('purchased_at')
                ->paginate(20),
        ]);
    }

    public function manageTool(User $user, \App\Models\UserTool $tool): View
    {
        $this->ensureMember($user);
        abort_unless($tool->user_id === $user->id, 404);

        $tool->load(['product', 'variant', 'integration', 'orderItem', 'domainConnection']);

        $logs = $tool->integration
            ? SiteIntegrationCheckLog::query()
                ->where('owner_type', 'owned')
                ->where('owner_id', $tool->integration->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
            : collect();

        return view('dashboard.admin.users.tools.show', [
            'user' => $user,
            'tool' => $tool,
            'logs' => $logs,
            'freshCredentials' => session('fresh_tool_credentials'),
            'suggestedSiteUrl' => $tool->suggestedSiteUrl(),
        ]);
    }

    public function setupTool(Request $request, User $user, \App\Models\UserTool $tool): RedirectResponse
    {
        $this->ensureMember($user);
        abort_unless($tool->user_id === $user->id, 404);

        $data = $request->validate([
            'site_url' => ['required', 'url', 'max:500'],
            'admin_login_url' => ['required', 'url', 'max:500'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:6', 'max:255'],
            'livechat_name' => ['nullable', 'string', 'max:255'],
            'livechat_url' => ['nullable', 'string', 'max:2000'],
            'livechat_email' => ['nullable', 'email', 'max:255'],
            'livechat_password' => ['nullable', 'string', 'min:4', 'max:255'],
        ]);

        try {
            $result = app(\App\Services\SiteIntegrations\UserToolProvisioningService::class)
                ->setup($tool, $data, $request->user(), $request->ip());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.users.tools.show', [$user, $tool])
            ->with('status', 'Tool configured.')
            ->with('fresh_tool_credentials', $result['credentials']);
    }

    public function reconfigureTool(Request $request, User $user, \App\Models\UserTool $tool): RedirectResponse
    {
        $this->ensureMember($user);
        abort_unless($tool->user_id === $user->id, 404);

        $data = $request->validate([
            'site_url' => ['required', 'url', 'max:500'],
            'admin_login_url' => ['required', 'url', 'max:500'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['nullable', 'string', 'min:6', 'max:255'],
        ]);

        try {
            app(\App\Services\SiteIntegrations\UserToolProvisioningService::class)
                ->reconfigure($tool, $data, $request->user(), $request->ip());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.users.tools.show', [$user, $tool])
            ->with('status', 'Tool reconfigured. Subscription expiry was not changed.');
    }

    public function updateToolLivechat(Request $request, User $user, \App\Models\UserTool $tool): RedirectResponse
    {
        $this->ensureMember($user);
        abort_unless($tool->user_id === $user->id, 404);

        if (! \Illuminate\Support\Facades\Schema::hasColumn('user_tools', 'livechat_url')) {
            return redirect()
                ->route('admin.users.tools.show', [$user, $tool])
                ->with('error', 'Livechat columns are missing. Run php artisan migrate on the server, then try again.');
        }

        $data = $request->validate([
            'livechat_name' => ['nullable', 'string', 'max:500'],
            'livechat_url' => ['nullable', 'string', 'max:2000'],
            'livechat_email' => ['nullable', 'email', 'max:255'],
            'livechat_password' => ['nullable', 'string', 'min:4', 'max:255'],
        ]);

        $rawUrl = trim((string) ($data['livechat_url'] ?? ''));
        if ($rawUrl !== '') {
            $normalizedUrl = preg_match('#^https?://#i', $rawUrl) ? $rawUrl : 'https://'.$rawUrl;
            if (! filter_var($normalizedUrl, FILTER_VALIDATE_URL)) {
                return redirect()
                    ->route('admin.users.tools.show', [$user, $tool])
                    ->withInput()
                    ->with('error', 'Enter a valid livechat URL.');
            }
            $data['livechat_url'] = $normalizedUrl;
        } else {
            $data['livechat_url'] = null;
        }

        try {
            app(\App\Services\SiteIntegrations\UserToolProvisioningService::class)
                ->updateLivechat($tool, $data, $request->user(), $request->ip());
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.users.tools.show', [$user, $tool])
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.users.tools.show', [$user, $tool])
                ->with('error', 'Could not save livechat logins: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.users.tools.show', [$user, $tool])
            ->with('status', 'Livechat logins saved.');
    }

    public function rotateToolCredentials(Request $request, User $user, \App\Models\UserTool $tool): RedirectResponse
    {
        $this->ensureMember($user);
        abort_unless($tool->user_id === $user->id, 404);

        try {
            $result = app(\App\Services\SiteIntegrations\UserToolProvisioningService::class)
                ->rotateCredentials($tool, $request->user(), $request->ip());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.users.tools.show', [$user, $tool])
            ->with('status', 'Integration credentials rotated. Update the merchant site env.')
            ->with('fresh_tool_credentials', $result['credentials']);
    }

    public function checkTool(Request $request, User $user, \App\Models\UserTool $tool): RedirectResponse
    {
        $this->ensureMember($user);
        abort_unless($tool->user_id === $user->id, 404);

        $result = app(\App\Services\SiteIntegrations\ConnectionCheckService::class)->checkOwned($tool->load('integration'));

        $tool->load('integration');
        $status = $tool->integration?->connection_status;

        $redirect = redirect()->route('admin.users.tools.show', [$user, $tool]);

        if ($result['ok']) {
            return $redirect->with('status', $result['message']);
        }

        if ($status === 'pending_merchant') {
            return $redirect->with('warning', $result['message']);
        }

        return $redirect->with('error', $result['message']);
    }

    public function approveDomainConnection(Request $request, User $user, DomainConnection $connection): RedirectResponse
    {
        $this->ensureMember($user);
        abort_unless($connection->user_id === $user->id, 404);

        if ($connection->verification_status === DomainConnection::STATUS_VERIFIED) {
            return back()->with('status', 'Domain is already verified.');
        }

        $previousStatus = $connection->verification_status;

        app(DomainConnectionService::class)->approveManually($connection);

        $this->audit->log(
            (int) auth()->id(),
            'admin.domain_connection.manual_approve',
            $connection,
            ['verification_status' => $previousStatus],
            ['verification_status' => DomainConnection::STATUS_VERIFIED],
            $request->ip(),
        );

        return back()->with('status', __('Domain :fqdn manually approved.', ['fqdn' => $connection->fqdn]));
    }

    public function manualPurchaseCatalog(Request $request): JsonResponse
    {
        $categoryId = $request->integer('service_category_id') ?: null;
        $serviceId = $request->integer('product_type_id') ?: null;

        $categories = ServiceCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $servicesQuery = ProductType::query()
            ->where('is_active', true)
            ->orderBy('sort_order');

        if ($categoryId) {
            $servicesQuery->where('service_category_id', $categoryId);
        }

        $services = $servicesQuery->get(['id', 'name', 'service_category_id']);

        $productsQuery = PlatformProduct::query()
            ->visibleToPublic()
            ->with(['activeVariants' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('title');

        if ($serviceId) {
            $productsQuery->where('product_type_id', $serviceId);
        } elseif ($categoryId) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('platform_products', 'service_category_id')) {
                $productsQuery->where('service_category_id', $categoryId);
            } else {
                $productsQuery->whereHas('productType', fn ($q) => $q->where('service_category_id', $categoryId));
            }
        }

        $products = $productsQuery->get()->map(fn (PlatformProduct $product) => [
            'id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'product_type' => $product->product_type?->value,
            'product_type_id' => $product->product_type_id,
            'service_category_id' => $product->service_category_id,
            'base_price' => (float) $product->base_price,
            'variants' => $product->activeVariants->map(fn (PlatformProductVariant $variant) => [
                'id' => $variant->id,
                'label' => $variant->displayLabel(),
                'price' => (float) $variant->price,
                'duration_months' => $variant->duration_months,
            ])->values(),
        ])->values();

        return response()->json([
            'categories' => $categories,
            'services' => $services,
            'products' => $products,
        ]);
    }

    public function manualPurchase(Request $request, User $user): RedirectResponse
    {
        $this->ensureMember($user);

        $validated = $request->validate([
            'product_slug' => ['required', 'string', 'max:255'],
            'variant_id' => ['required', 'integer', 'exists:platform_product_variants,id'],
            'mark_paid' => ['nullable', 'boolean'],
            'purchased_at' => ['nullable', 'date', 'before_or_equal:today'],
            'domain_fqdn' => ['nullable', 'string', 'max:255'],
        ]);

        $product = PlatformProduct::query()
            ->visibleToPublic()
            ->where('slug', $validated['product_slug'])
            ->firstOrFail();

        PlatformProductVariant::query()
            ->whereKey($validated['variant_id'])
            ->where('platform_product_id', $product->id)
            ->where('is_active', true)
            ->firstOrFail();

        $data = [
            'variant_id' => (int) $validated['variant_id'],
            'quantity' => 1,
            'idempotency_key' => (string) Str::uuid(),
            'payment_method' => Order::PAYMENT_MANUAL_BANK_TRANSFER,
            'admin_skip_domain_validation' => true,
            'purchased_at' => $validated['purchased_at'] ?? null,
            'domain_mode' => 'none',
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
            return redirect()
                ->route('admin.users.show', $user)
                ->with('error', $e->getMessage());
        }

        if (! $request->boolean('mark_paid')) {
            $this->notifyManualPurchaseCreated($user, $order);
        }

        $message = $request->boolean('mark_paid')
            ? __('Order created and marked paid. Reference: :ref', ['ref' => $order->reference])
            : __('Pending order created. Reference: :ref', ['ref' => $order->reference]);

        return redirect()
            ->route('admin.users.tools', $user)
            ->with('status', $message);
    }

    public function adjustToolExpiry(Request $request, User $user, \App\Models\UserTool $tool): RedirectResponse
    {
        $this->ensureMember($user);
        abort_unless($tool->user_id === $user->id, 404);

        if ($tool->status === UserToolStatus::PendingSetup) {
            return back()->with('error', 'Complete initial setup before adjusting expiry.');
        }

        $validated = $request->validate([
            'expires_at' => ['required', 'date'],
        ]);

        $expiresAt = \Carbon\Carbon::parse($validated['expires_at'])->endOfDay();
        $previous = $tool->expires_at?->toIso8601String();
        $wasExpired = $tool->status === UserToolStatus::Expired;
        $notifyExtend = $wasExpired && $expiresAt->isFuture() && $tool->endedByNaturalExpiry();

        $tool->expires_at = $expiresAt;
        if ($expiresAt->isFuture() && $tool->status === UserToolStatus::Expired) {
            $tool->status = UserToolStatus::Active;
            $tool->clearSubscriptionEndReason();
            $tool->clearShutdownResumeExpiry();
        } elseif ($expiresAt->isPast() && $tool->status === UserToolStatus::Active) {
            $tool->status = UserToolStatus::Expired;
            $tool->markSubscriptionEnded(\App\Models\UserTool::END_REASON_ADMIN_ADJUSTED);
            $tool->clearShutdownResumeExpiry();
        }
        $tool->save();

        if ($tool->integration && $tool->site_url) {
            $this->subscriptionSync->push($tool->fresh(['integration']));
        }

        // Only email when extending a naturally expired subscription (not after admin shutdown/enable).
        if ($notifyExtend) {
            $this->toolLifecycleNotifier->notifyExtendedAfterNaturalExpiry($tool->fresh(['product', 'user']));
        }

        $this->audit->log(auth()->id(), 'user_tool.expiry_adjusted', $tool, null, [
            'previous_expires_at' => $previous,
            'expires_at' => $tool->expires_at?->toIso8601String(),
            'user_notified' => $notifyExtend,
        ], $request->ip(), ['module' => 'site_integrations']);

        return redirect()
            ->route('admin.users.tools.show', [$user, $tool])
            ->with('status', 'Subscription expiry updated.');
    }

    public function shutdownTool(Request $request, User $user, \App\Models\UserTool $tool): RedirectResponse
    {
        $this->ensureMember($user);
        abort_unless($tool->user_id === $user->id, 404);

        if ($tool->status === UserToolStatus::PendingSetup) {
            return back()->with('error', 'Complete initial setup before shutting down the site.');
        }

        if (! $tool->isSubscriptionLive()) {
            return back()->with('error', 'This site is already shut down. Use Enable to reopen it.');
        }

        $previous = [
            'status' => $tool->status instanceof UserToolStatus ? $tool->status->value : (string) $tool->status,
            'expires_at' => $tool->expires_at?->toIso8601String(),
        ];

        // Remember the paid window so Enable can restore it without asking for a new date.
        $tool->shutdown_resume_expires_at = $tool->expires_at;
        $tool->status = UserToolStatus::Expired;
        $tool->expires_at = now();
        $tool->markSubscriptionEnded(\App\Models\UserTool::END_REASON_ADMIN_SHUTDOWN);
        $tool->save();

        $pushed = false;
        $syncWarning = null;
        if ($tool->integration && $tool->site_url) {
            $pushed = true;
            $result = $this->subscriptionSync->push($tool->fresh(['integration']));
            if (! ($result['ok'] ?? false)) {
                $syncWarning = $result['message'] ?? 'Merchant site did not acknowledge shutdown. Hub is still marked expired.';
            }
        }

        // Intentionally no user email — admin shutdown must stay silent.
        $this->audit->log(auth()->id(), 'user_tool.site_shutdown', $tool, $previous, [
            'status' => $tool->status->value,
            'expires_at' => $tool->expires_at?->toIso8601String(),
            'shutdown_resume_expires_at' => $tool->shutdown_resume_expires_at?->toIso8601String(),
            'merchant_notified' => $pushed && $syncWarning === null,
            'user_notified' => false,
        ], $request->ip(), ['module' => 'site_integrations']);

        $redirect = redirect()->route('admin.users.tools.show', [$user, $tool]);

        if ($syncWarning) {
            return $redirect
                ->with('status', 'Site shut down on Hub.')
                ->with('warning', $syncWarning);
        }

        if (! $pushed) {
            return $redirect->with('status', 'Site shut down on Hub. No merchant sync was sent (missing site URL or integration credentials).');
        }

        return $redirect->with('status', 'Site shut down. The external website has been notified to deactivate.');
    }

    public function enableTool(Request $request, User $user, \App\Models\UserTool $tool): RedirectResponse
    {
        $this->ensureMember($user);
        abort_unless($tool->user_id === $user->id, 404);

        if ($tool->status === UserToolStatus::PendingSetup) {
            return back()->with('error', 'Complete initial setup before enabling the site.');
        }

        if ($tool->isSubscriptionLive()) {
            return back()->with('error', 'This site is already live.');
        }

        $canResume = $tool->canResumeShutdownWithStoredExpiry();

        if ($canResume) {
            $expiresAt = $tool->shutdown_resume_expires_at->copy()->endOfDay();
        } else {
            $validated = $request->validate([
                'enable_expires_at' => ['required', 'date', 'after:today'],
            ]);
            $expiresAt = \Carbon\Carbon::parse($validated['enable_expires_at'])->endOfDay();
        }

        $previous = [
            'status' => $tool->status instanceof UserToolStatus ? $tool->status->value : (string) $tool->status,
            'expires_at' => $tool->expires_at?->toIso8601String(),
            'shutdown_resume_expires_at' => $tool->shutdown_resume_expires_at?->toIso8601String(),
        ];

        $tool->status = UserToolStatus::Active;
        $tool->expires_at = $expiresAt;
        $tool->clearSubscriptionEndReason();
        $tool->clearShutdownResumeExpiry();
        $tool->save();

        $pushed = false;
        $syncWarning = null;
        if ($tool->integration && $tool->site_url) {
            $pushed = true;
            $result = $this->subscriptionSync->push($tool->fresh(['integration']));
            if (! ($result['ok'] ?? false)) {
                $syncWarning = $result['message'] ?? 'Merchant site did not acknowledge enable. Hub is still marked active.';
            }
        }

        // Intentionally no user email — admin Enable must stay silent.
        $this->audit->log(auth()->id(), 'user_tool.site_enabled', $tool, $previous, [
            'status' => $tool->status->value,
            'expires_at' => $tool->expires_at?->toIso8601String(),
            'resumed_stored_expiry' => $canResume,
            'merchant_notified' => $pushed && $syncWarning === null,
            'user_notified' => false,
        ], $request->ip(), ['module' => 'site_integrations']);

        $redirect = redirect()->route('admin.users.tools.show', [$user, $tool]);

        $statusMessage = $canResume
            ? __('Site enabled. Previous expiry :date was restored.', ['date' => $expiresAt->format('j M Y')])
            : __('Site enabled. The external website has been notified.');

        if ($syncWarning) {
            return $redirect
                ->with('status', $canResume ? __('Site enabled on Hub; previous expiry restored.') : __('Site enabled on Hub.'))
                ->with('warning', $syncWarning);
        }

        if (! $pushed) {
            return $redirect->with(
                'status',
                $canResume
                    ? __('Site enabled on Hub; previous expiry restored. No merchant sync was sent (missing site URL or integration credentials).')
                    : __('Site enabled on Hub. No merchant sync was sent (missing site URL or integration credentials).')
            );
        }

        return $redirect->with('status', $statusMessage);
    }

    public function tickets(User $user, Request $request): View
    {
        $this->ensureMember($user);

        return $this->userTabView($request, $user, 'tickets', [
            'tickets' => $user->supportTickets()->orderByDesc('created_at')->paginate(20),
        ]);
    }

    public function activity(User $user, Request $request): View
    {
        $this->ensureMember($user);

        $activity = AuditLog::query()
            ->where(function ($q) use ($user) {
                $q->where('model_type', User::class)->where('model_id', $user->id);
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->userTabView($request, $user, 'activity', [
            'activity' => $activity,
        ]);
    }

    public function security(User $user, Request $request): View
    {
        $this->ensureMember($user);

        return $this->userTabView($request, $user, 'security');
    }

    public function edit(User $user): View
    {
        $this->ensureMember($user);

        return view('dashboard.admin.users.edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureMember($user);

        if ($user->anonymized_at !== null) {
            return back()->with('error', __('This account has been permanently deleted and cannot be edited.'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'kyc_level' => ['nullable', 'integer', 'min:0', 'max:4'],
        ]);

        if (isset($data['country'])) {
            $data['country'] = strtoupper($data['country']);
        }

        $old = $user->only(['name', 'username', 'email', 'phone', 'country', 'bio', 'kyc_level']);

        $user->fill(collect($data)->except('kyc_level')->all());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if (array_key_exists('kyc_level', $data) && $data['kyc_level'] !== null) {
            $user->forceFill(['kyc_level' => (int) $data['kyc_level']])->save();
        }

        $this->audit->log(auth()->id(), 'user.updated', $user, $old, $user->only([
            'name', 'username', 'email', 'phone', 'country', 'bio', 'kyc_level',
        ]), $request->ip());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', __('User profile updated.'));
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $this->ensureMember($user);

        if ($user->anonymized_at !== null) {
            return back()->with('error', __('Cannot reset password for a deleted account.'));
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        $this->audit->log(auth()->id(), 'user.password_reset_link_sent', $user, null, [
            'user_id' => $user->id,
            'status' => $status,
        ], $request->ip());

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', __($status));
        }

        return back()->with('status', __('Password reset link sent to :email.', ['email' => $user->email]));
    }

    public function verifyEmail(Request $request, User $user): RedirectResponse
    {
        $this->ensureMember($user);

        if ($user->anonymized_at !== null) {
            return back()->with('error', __('Cannot verify a deleted account.'));
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->audit->log(auth()->id(), 'user.email_verified', $user, null, [
            'user_id' => $user->id,
        ], $request->ip());

        return back()->with('status', __('Email marked as verified.'));
    }

    public function unverifyEmail(Request $request, User $user): RedirectResponse
    {
        $this->ensureMember($user);

        if ($user->anonymized_at !== null) {
            return back()->with('error', __('Cannot modify a deleted account.'));
        }

        $user->forceFill(['email_verified_at' => null])->save();

        $this->audit->log(auth()->id(), 'user.email_unverified', $user, null, [
            'user_id' => $user->id,
        ], $request->ip());

        return back()->with('status', __('Email marked as unverified.'));
    }

    public function provisionWallet(Request $request, User $user): RedirectResponse
    {
        $this->ensureMember($user);

        if ($user->anonymized_at !== null) {
            return back()->with('error', __('Cannot provision a wallet for a deleted account.'));
        }

        if ($user->wallet) {
            return back()->with('error', __('User already has a wallet.'));
        }

        try {
            $this->walletProvisioning->createWallet($user);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log(auth()->id(), 'user.wallet_provisioned', $user, null, [
            'user_id' => $user->id,
            'wallet_id' => $user->fresh()->wallet?->id,
        ], $request->ip());

        return back()->with('status', __('Wallet provisioned.'));
    }

    public function suspend(User $user, Request $request): RedirectResponse
    {
        $this->ensureMember($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', __('You cannot suspend your own account.'));
        }

        if ($user->anonymized_at !== null) {
            return back()->with('error', __('This account has been permanently deleted.'));
        }

        $user->suspend(auth()->id());

        $this->audit->log(auth()->id(), 'user.suspended', $user, null, [
            'user_id' => $user->id,
            'is_suspended' => true,
        ], $request->ip());

        return back()->with('status', __('User suspended.'));
    }

    public function restore(User $user, Request $request): RedirectResponse
    {
        $this->ensureMember($user);

        if ($user->anonymized_at !== null) {
            return back()->with('error', __('Anonymized accounts cannot be restored.'));
        }

        $user->restoreAccess();

        $this->audit->log(auth()->id(), 'user.restored', $user, null, [
            'user_id' => $user->id,
            'is_suspended' => false,
        ], $request->ip());

        return back()->with('status', __('User restored.'));
    }

    public function destroy(User $user, Request $request): RedirectResponse
    {
        $this->ensureMember($user);

        if ($user->hasRole('admin')) {
            abort(403, 'Administrators cannot be permanently deleted.');
        }

        if ($user->anonymized_at !== null) {
            return back()->with('error', __('This account is already permanently deleted.'));
        }

        if (! $user->is_suspended) {
            return back()->with('error', __('Suspend the user before permanently deleting.'));
        }

        $oldEmail = $user->email;

        try {
            $ok = $user->anonymize(auth()->id());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Could not delete user: :message', ['message' => $e->getMessage()]));
        }

        if (! $ok) {
            return back()->with('error', __('This account cannot be permanently deleted.'));
        }

        $this->audit->log(auth()->id(), 'user.anonymized', $user, [
            'email' => $oldEmail,
        ], [
            'user_id' => $user->id,
            'anonymized_at' => optional($user->fresh())->anonymized_at?->toIso8601String(),
        ], $request->ip());

        return redirect()
            ->route('admin.users', ['status' => 'suspended'])
            ->with('status', __('User permanently deleted. They are hidden now and will be fully removed within 24 hours.'));
    }

    public function bulkSuspend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
        ]);

        $suspended = 0;
        $skipped = 0;
        $adminId = (int) auth()->id();

        foreach ($this->memberUsersByIds($data['ids']) as $user) {
            if ($user->id === $adminId || $user->is_suspended || $user->anonymized_at !== null) {
                $skipped++;

                continue;
            }

            $user->suspend($adminId);
            $this->audit->log($adminId, 'user.suspended', $user, null, [
                'user_id' => $user->id,
                'is_suspended' => true,
                'bulk' => true,
            ], $request->ip());
            $suspended++;
        }

        $status = __('Suspended :count user(s).', ['count' => $suspended]);
        if ($skipped > 0) {
            $status .= ' '.__(':skipped skipped.', ['skipped' => $skipped]);
        }

        return redirect()
            ->route('admin.users', ['status' => 'active'])
            ->with('status', $status);
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
        ]);

        $restored = 0;
        $skipped = 0;
        $adminId = (int) auth()->id();

        foreach ($this->memberUsersByIds($data['ids']) as $user) {
            if (! $user->is_suspended || $user->anonymized_at !== null) {
                $skipped++;

                continue;
            }

            $user->restoreAccess();
            $this->audit->log($adminId, 'user.restored', $user, null, [
                'user_id' => $user->id,
                'is_suspended' => false,
                'bulk' => true,
            ], $request->ip());
            $restored++;
        }

        $status = __('Restored :count user(s).', ['count' => $restored]);
        if ($skipped > 0) {
            $status .= ' '.__(':skipped skipped.', ['skipped' => $skipped]);
        }

        return redirect()
            ->route('admin.users', ['status' => 'suspended'])
            ->with('status', $status);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
        ]);

        $deleted = 0;
        $skipped = 0;
        $adminId = (int) auth()->id();

        foreach ($this->memberUsersByIds($data['ids']) as $user) {
            if ($user->hasRole('admin') || $user->anonymized_at !== null || ! $user->is_suspended) {
                $skipped++;

                continue;
            }

            $oldEmail = $user->email;

            try {
                $ok = $user->anonymize($adminId);
            } catch (\Throwable $e) {
                report($e);
                $skipped++;

                continue;
            }

            if (! $ok) {
                $skipped++;

                continue;
            }

            $this->audit->log($adminId, 'user.anonymized', $user, [
                'email' => $oldEmail,
            ], [
                'user_id' => $user->id,
                'anonymized_at' => optional($user->fresh())->anonymized_at?->toIso8601String(),
                'bulk' => true,
            ], $request->ip());
            $deleted++;
        }

        $status = __('Permanently deleted :count user(s).', ['count' => $deleted]);
        if ($skipped > 0) {
            $status .= ' '.__(':skipped skipped.', ['skipped' => $skipped]);
        }

        return redirect()
            ->route('admin.users', ['status' => 'suspended'])
            ->with('status', $status);
    }

    /**
     * Legacy role assignment endpoint — role changes belong on Administrators.
     */
    public function assignRole(User $user, Request $request): RedirectResponse
    {
        abort(403, 'Role assignment is managed from Administrators.');
    }

    private function ensureMember(User $user): void
    {
        abort_if($user->isAnonymized(), 404);
        abort_unless(
            ($user->hasRole('user') || $user->hasRole('agent')) && ! $user->hasRole('admin'),
            404
        );
    }

    /**
     * @param  list<int|string>  $ids
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function memberUsersByIds(array $ids): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereIn('id', $ids)
            ->role(['user', 'agent'])
            ->notAnonymized()
            ->get()
            ->filter(fn (User $user) => ! $user->hasRole('admin'))
            ->values();
    }

    private function wantsTabPartial(Request $request): bool
    {
        return $request->headers->get('X-Dashboard-Tab') === '1';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function userTabView(Request $request, User $user, string $activeTab, array $data = []): View
    {
        $payload = array_merge($data, [
            'user' => $user,
            'activeTab' => $activeTab,
        ]);

        if ($this->wantsTabPartial($request)) {
            return view('dashboard.admin.users.show-panel', $payload);
        }

        return view('dashboard.admin.users.show', $payload);
    }

    private function notifyManualPurchaseCreated(User $user, Order $order): void
    {
        $order->loadMissing(['items', 'user']);
        $context = $this->notificationEmailRenderer->orderContext($order);

        $this->notificationDispatcher->notifyUser(
            $user,
            new NotificationMessage(
                type: 'order.created',
                title: __('Purchase recorded'),
                body: __('A purchase was created for you. Order :ref for ₦:amount is awaiting payment confirmation.', [
                    'ref' => $order->reference,
                    'amount' => number_format((float) ($order->total_amount ?? $order->amount), 2),
                ]),
                actionUrl: Route::has('dashboard.orders') ? route('dashboard.orders') : null,
                meta: [
                    'order_id' => $order->id,
                    'email_context' => $context,
                ],
                emailSubject: __('Order :ref recorded', ['ref' => $order->reference]),
                dedupeKey: 'order.created.'.$order->id,
            ),
            ['database', 'mail']
        );
    }
}
