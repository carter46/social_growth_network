<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use App\Services\Campaigns\CampaignAccountHoldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrashController extends Controller
{
    public function __construct(
        private AuditLogService $audit,
        private CampaignAccountHoldService $campaignHolds,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::onlyTrashed()
            ->whereNull('anonymized_at')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['user', 'agent']))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('username', 'like', $like);
                });
            })
            ->with('roles')
            ->orderByDesc('deleted_at')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.admin.trash.index', [
            'users' => $users,
            'q' => $q,
        ]);
    }

    public function restore(int $userId, Request $request): RedirectResponse
    {
        $user = User::onlyTrashed()->whereKey($userId)->firstOrFail();
        $this->ensureMember($user);

        $user->restore();
        $user->restoreAccess();
        $released = $this->campaignHolds->releaseForCreator($user, auth()->id(), $request->ip());

        $this->audit->log(auth()->id(), 'user.trash_restored', $user, null, [
            'user_id' => $user->id,
            'campaigns_released' => $released,
        ], $request->ip());

        $status = __('Account restored from trash.');
        if ($released > 0) {
            $status .= ' '.__(':count campaign(s) were put back on the marketplace.', ['count' => $released]);
        }

        return back()->with('status', $status);
    }

    public function destroy(int $userId, Request $request): RedirectResponse
    {
        $user = User::onlyTrashed()->whereKey($userId)->firstOrFail();
        $this->ensureMember($user);

        $oldEmail = $user->email;
        $actorId = auth()->id();

        try {
            $ok = $user->anonymize($actorId);
            if (! $ok) {
                return back()->with('error', __('This account cannot be permanently deleted.'));
            }
            $user->forceDelete();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Could not permanently delete: :message', ['message' => $e->getMessage()]));
        }

        $this->audit->log($actorId, 'user.permanently_deleted', null, [
            'email' => $oldEmail,
            'user_id' => $userId,
        ], [
            'user_id' => $userId,
        ], $request->ip());

        return back()->with('status', __('Account permanently deleted.'));
    }

    private function ensureMember(User $user): void
    {
        abort_unless(
            ($user->hasRole('user') || $user->hasRole('agent')) && ! $user->hasRole('admin'),
            404
        );
    }
}
