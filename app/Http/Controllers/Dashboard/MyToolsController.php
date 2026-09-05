<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\PlatformProductType;
use App\Enums\UserToolStatus;
use App\Http\Controllers\Controller;
use App\Models\DomainConnection;
use App\Models\DomainRegistration;
use App\Models\UserTool;
use App\Modules\Admin\Services\AuditLogService;
use App\Services\SiteIntegrations\DemoLaunchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class MyToolsController extends Controller
{
    public function __construct(
        private DemoLaunchService $launch,
        private AuditLogService $audit,
    ) {}

    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $status = $request->string('status')->toString();
        $expiringSoon = $request->boolean('expiring_soon');
        $q = $request->string('q')->toString();

        $tools = UserTool::query()
            ->ownedBy($userId)
            ->with(['product', 'variant', 'integration'])
            ->whereHas('product', fn ($query) => $query->where('product_type', PlatformProductType::SocialService))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.$q.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('display_name', 'like', $term)
                        ->orWhere('site_url', 'like', $term)
                        ->orWhereHas('product', fn ($p) => $p->where('title', 'like', $term));
                });
            })
            ->when($expiringSoon, function ($query) {
                $query->where('status', UserToolStatus::Active)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', now())
                    ->where('expires_at', '<=', now()->addDays(7));
            })
            ->orderByDesc('purchased_at')
            ->paginate(15)
            ->withQueryString();

        return $this->toolsView($request, 'websites', [
            'tools' => $tools,
            'domains' => null,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'expiring_soon' => $expiringSoon,
            ],
        ]);
    }

    public function domains(Request $request): View
    {
        $userId = (int) $request->user()->id;

        $registrations = DomainRegistration::query()
            ->forUser($userId)
            ->with('order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DomainRegistration $row) => (object) [
                'kind' => 'registration',
                'fqdn' => $row->fqdn,
                'status' => $row->status,
                'nameservers' => $row->nameserverList(),
                'manage_url' => route('dashboard.my-domains.show', $row),
                'created_at' => $row->created_at,
            ]);

        $connections = DomainConnection::query()
            ->forUser($userId)
            ->whereHas('order', fn ($q) => $q->where('status', 'paid'))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DomainConnection $row) => (object) [
                'kind' => 'connection',
                'fqdn' => $row->fqdn,
                'status' => $row->verification_status,
                'nameservers' => $row->displayNameserverList(),
                'manage_url' => route('dashboard.my-domains.connections.show', $row),
                'created_at' => $row->created_at,
            ]);

        $domains = $registrations
            ->concat($connections)
            ->sortByDesc(fn ($row) => $row->created_at?->timestamp ?? 0)
            ->values();

        return $this->toolsView($request, 'domains', [
            'tools' => null,
            'domains' => $domains,
            'filters' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function toolsView(Request $request, string $activeTab, array $data): View
    {
        if ($request->headers->get('X-Dashboard-Tab') === '1') {
            return view('dashboard.user.my-tools._panel-'.$activeTab, $data);
        }

        return view('dashboard.user.my-tools.index', array_merge($data, [
            'activeTab' => $activeTab,
        ]));
    }

    public function show(Request $request, UserTool $tool): View
    {
        abort_unless($tool->user_id === $request->user()->id, 404);
        $tool->load(['product.heroMedia', 'variant', 'integration', 'order', 'orderItem']);

        return view('dashboard.user.my-tools.show', [
            'tool' => $tool,
        ]);
    }

    public function launchAdmin(Request $request, UserTool $tool): RedirectResponse
    {
        abort_unless($tool->user_id === $request->user()->id, 404);
        $tool->load('integration');

        try {
            $result = $this->launch->launchOwnedAdmin(
                $request->user(),
                $tool,
                $request->ip(),
                $request->userAgent()
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($result['redirect_url']);
    }

    public function copyPassword(Request $request, UserTool $tool): JsonResponse
    {
        abort_unless($tool->user_id === $request->user()->id, 404);

        if (! $tool->canRevealAdminPassword()) {
            return response()->json(['message' => 'Password is not available for this tool.'], 422)
                ->header('Cache-Control', 'no-store');
        }

        $this->audit->log($request->user()->id, 'user_tool.password_copied', $tool, null, [
            'tool_id' => $tool->id,
        ], $request->ip(), [
            'actor_type' => 'user',
            'actor_id' => $request->user()->id,
            'module' => 'site_integrations',
        ]);

        return response()->json([
            'password' => $tool->admin_password,
        ])->header('Cache-Control', 'no-store');
    }

    public function copyLivechatPassword(Request $request, UserTool $tool): JsonResponse
    {
        abort_unless($tool->user_id === $request->user()->id, 404);

        if (! $tool->canRevealLivechatPassword()) {
            return response()->json(['message' => 'Livechat password is not available for this tool.'], 422)
                ->header('Cache-Control', 'no-store');
        }

        $this->audit->log($request->user()->id, 'user_tool.livechat_password_copied', $tool, null, [
            'tool_id' => $tool->id,
        ], $request->ip(), [
            'actor_type' => 'user',
            'actor_id' => $request->user()->id,
            'module' => 'site_integrations',
        ]);

        return response()->json([
            'password' => $tool->livechat_password,
        ])->header('Cache-Control', 'no-store');
    }
}
