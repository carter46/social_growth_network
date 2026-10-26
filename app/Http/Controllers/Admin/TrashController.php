<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurgedUserRecord;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use App\Services\Campaigns\CampaignAccountHoldService;
use App\Services\Users\AccountLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TrashController extends Controller
{
    public function __construct(
        private AuditLogService $audit,
        private CampaignAccountHoldService $campaignHolds,
        private AccountLifecycleService $lifecycle,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $purgeDays = (int) config('user-lifecycle.trash_to_purge_days', 180);

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
            'purgeDays' => $purgeDays,
        ]);
    }

    public function restore(int $userId, Request $request): RedirectResponse
    {
        try {
            $released = DB::transaction(function () use ($userId, $request) {
                $user = User::onlyTrashed()
                    ->whereKey($userId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureMember($user);

                if ($user->anonymized_at !== null
                    || PurgedUserRecord::query()->where('original_user_id', $user->id)->exists()
                ) {
                    throw new \RuntimeException(
                        __('This account was permanently removed and cannot be restored. See Historical accounts.')
                    );
                }

                $user->restore();
                $user->restoreAccess();

                return $this->campaignHolds->releaseForCreator($user, auth()->id(), $request->ip());
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $user = User::query()->find($userId);
        if ($user) {
            $this->audit->log(auth()->id(), 'user.trash_restored', $user, null, [
                'user_id' => $user->id,
                'campaigns_released' => $released,
                'via' => 'admin',
            ], $request->ip());
        }

        $status = __('Account restored from trash.');
        if ($released > 0) {
            $status .= ' '.__(':count campaign(s) were put back on the marketplace.', ['count' => $released]);
        }

        return back()->with('status', $status);
    }

    /**
     * Permanently remove from Trash: historical snapshot + anonymized tombstone (ledger-safe).
     */
    public function destroy(int $userId, Request $request): RedirectResponse
    {
        $user = User::onlyTrashed()->whereKey($userId)->firstOrFail();
        $this->ensureMember($user);

        if ($user->anonymized_at !== null
            || PurgedUserRecord::query()->where('original_user_id', $user->id)->exists()
        ) {
            return back()->with('error', __('This account has already been permanently removed.'));
        }

        try {
            $record = $this->lifecycle->purgeTrashedUser(
                $user,
                'admin_manual_purge',
                'admin.trash.destroy',
                auth()->id(),
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Could not permanently delete: :message', [
                'message' => $e->getMessage(),
            ]));
        }

        return back()->with('status', __(
            'Account permanently removed from Trash. A historical record was kept (ID :id).',
            ['id' => $record->id]
        ));
    }

    private function ensureMember(User $user): void
    {
        abort_unless(
            ($user->hasRole('user') || $user->hasRole('agent')) && ! $user->hasRole('admin'),
            404
        );
    }
}
