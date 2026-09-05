<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformProductType;
use App\Enums\SiteIntegrationStatus;
use App\Http\Controllers\Controller;
use App\Models\PlatformProduct;
use App\Models\SiteIntegration;
use App\Models\SiteIntegrationCheckLog;
use App\Services\SiteIntegrations\SiteIntegrationAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SiteIntegrationAdminController extends Controller
{
    public function __construct(
        private SiteIntegrationAdminService $service,
    ) {}

    public function index(Request $request): View
    {
        $integrations = SiteIntegration::query()
            ->with('product')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('base_url', 'like', $term)
                        ->orWhereHas('product', fn ($p) => $p->where('title', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.admin.site-integrations.index', [
            'integrations' => $integrations,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->get('status'),
            ],
        ]);
    }

    public function create(): View
    {
        $products = $this->availableProducts();

        return view('dashboard.admin.site-integrations.create', [
            'products' => $products,
            'defaultCapabilities' => SiteIntegration::defaultCapabilities(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'platform_product_id' => ['required', 'integer', 'exists:platform_products,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:500'],
            'demo_user_email' => ['nullable', 'email', 'max:255'],
            'demo_admin_email' => ['nullable', 'email', 'max:255'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string'],
        ]);

        try {
            $result = $this->service->create($data, $request->user()?->id, $request->ip());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.site-integrations.show', $result['integration'])
            ->with('status', 'Demo integration created. Copy credentials now — the secret is shown once.')
            ->with('fresh_credentials', $result['credentials']);
    }

    public function show(SiteIntegration $siteIntegration): View
    {
        $siteIntegration->load('product');
        $logs = SiteIntegrationCheckLog::query()
            ->where('owner_type', 'demo')
            ->where('owner_id', $siteIntegration->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('dashboard.admin.site-integrations.show', [
            'integration' => $siteIntegration,
            'logs' => $logs,
            'statuses' => SiteIntegrationStatus::cases(),
            'freshCredentials' => session('fresh_credentials'),
        ]);
    }

    public function update(Request $request, SiteIntegration $siteIntegration): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:500'],
            'demo_user_email' => ['nullable', 'email', 'max:255'],
            'demo_admin_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:draft,active,disabled'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string'],
        ]);

        $this->service->update($siteIntegration, $data, $request->user()?->id, $request->ip());

        return back()->with('status', 'Integration updated.');
    }

    public function rotate(Request $request, SiteIntegration $siteIntegration): RedirectResponse
    {
        $result = $this->service->rotateCredentials($siteIntegration, $request->user()?->id, $request->ip());

        return redirect()
            ->route('admin.site-integrations.show', $siteIntegration)
            ->with('status', 'Credentials rotated. Copy the new secret now.')
            ->with('fresh_credentials', $result['credentials']);
    }

    public function check(Request $request, SiteIntegration $siteIntegration): RedirectResponse|JsonResponse
    {
        $result = $this->service->checkConnection($siteIntegration);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => (bool) ($result['ok'] ?? false),
                'message' => (string) ($result['message'] ?? ($result['ok'] ? 'Connection successful.' : 'Connection failed.')),
            ]);
        }

        return back()->with(
            $result['ok'] ? 'status' : 'error',
            $result['message']
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, PlatformProduct>
     */
    private function availableProducts()
    {
        $usedIds = SiteIntegration::query()->pluck('platform_product_id');

        return PlatformProduct::query()
            ->where('product_type', PlatformProductType::SocialService)
            ->whereNotIn('id', $usedIds)
            ->orderBy('title')
            ->get(['id', 'title', 'slug']);
    }
}
