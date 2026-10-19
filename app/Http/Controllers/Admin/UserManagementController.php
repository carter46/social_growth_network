<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use App\Modules\Wallet\Services\WalletProvisioningService;
use App\Services\Campaigns\CampaignAccountHoldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(
        private AuditLogService $audit,
        private WalletProvisioningService $walletProvisioning,
        private \App\Services\Notifications\AccountStatusNotifier $accountStatus,
        private CampaignAccountHoldService $campaignHolds,
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

    public function index(Request $request): View|\Illuminate\Http\JsonResponse
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

        $search = trim($request->string('q')->toString());

        $base = User::query()
            ->role($roleName ? [$roleName] : ['user', 'agent'])
            ->notAnonymized()
            ->when($search !== '', function ($q) use ($search) {
                // Partial matches so results narrow while the admin is still typing.
                $like = '%'.addcslashes($search, '\\%_').'%';
                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('username', 'like', $like));
            });

        if ($request->headers->get('X-Users-Suggest') === '1') {
            if ($search === '') {
                return response()->json(['data' => []]);
            }

            $suggestions = (clone $base)
                ->with('roles')
                ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [addcslashes($search, '\\%_').'%'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => array_values(array_filter([
                        $u->hasRole('user') ? 'Creator' : null,
                        $u->hasRole('agent') ? 'Agent' : null,
                    ])),
                    'suspended' => (bool) $u->is_suspended,
                    'url' => route('admin.users.show', $u),
                ]);

            return response()->json(['data' => $suggestions]);
        }

        $activeCount = (clone $base)->where('is_suspended', false)->count();
        $suspendedCount = (clone $base)->where('is_suspended', true)->count();

        $users = (clone $base)
            ->when($status === 'suspended', fn ($q) => $q->where('is_suspended', true))
            ->when($status === 'active', fn ($q) => $q->where('is_suspended', false))
            ->with('roles')
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

        if ($request->headers->get('X-Users-Live') === '1') {
            return view('dashboard.admin.users._results', $data);
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
            'name_change_note' => ['nullable', 'string', 'max:500'],
        ]);

        $nameChangeNote = trim((string) ($data['name_change_note'] ?? ''));
        unset($data['name_change_note']);
        if (trim($data['name']) !== trim((string) $user->name) && $user->nameLockedByPayoutAccount() && $nameChangeNote === '') {
            return back()->withInput()->withErrors([
                'name_change_note' => __('This member has payout accounts in their current name. Add a note explaining the name change.'),
            ]);
        }

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
        ]) + ($nameChangeNote !== '' ? ['name_change_note' => $nameChangeNote] : []), $request->ip());

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

        $wasSuspended = (bool) $user->is_suspended;
        $user->suspend(auth()->id());

        $held = 0;
        if (! $wasSuspended) {
            $held = $this->campaignHolds->holdForCreator(
                $user,
                CampaignAccountHoldService::REASON_SUSPENDED,
                auth()->id(),
                $request->ip(),
            );
            $this->accountStatus->suspended($user);
        }

        $this->audit->log(auth()->id(), 'user.suspended', $user, null, [
            'user_id' => $user->id,
            'is_suspended' => true,
            'campaigns_held' => $held,
        ], $request->ip());

        $status = __('User suspended.');
        if ($held > 0) {
            $status .= ' '.__(':count campaign(s) were removed from the marketplace.', ['count' => $held]);
        }

        return back()->with('status', $status);
    }

    public function restore(User $user, Request $request): RedirectResponse
    {
        $this->ensureMember($user);

        if ($user->anonymized_at !== null) {
            return back()->with('error', __('Anonymized accounts cannot be restored.'));
        }

        $wasSuspended = (bool) $user->is_suspended;
        $user->restoreAccess();

        $released = 0;
        if ($wasSuspended) {
            $released = $this->campaignHolds->releaseForCreator($user, auth()->id(), $request->ip());
            $this->accountStatus->restored($user);
        }

        $this->audit->log(auth()->id(), 'user.restored', $user, null, [
            'user_id' => $user->id,
            'is_suspended' => false,
            'campaigns_released' => $released,
        ], $request->ip());

        $status = __('User restored.');
        if ($released > 0) {
            $status .= ' '.__(':count campaign(s) were put back on the marketplace.', ['count' => $released]);
        }

        return back()->with('status', $status);
    }

    /** Move the account to Trash (soft delete). Data stays recoverable. */
    public function destroy(User $user, Request $request): RedirectResponse
    {
        $this->ensureMember($user);

        if ($user->hasRole('admin')) {
            abort(403, 'Administrators cannot be moved to trash.');
        }

        if ($user->anonymized_at !== null) {
            return back()->with('error', __('This account has already been permanently deleted.'));
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', __('You cannot move your own account to trash.'));
        }

        $oldEmail = $user->email;
        $actorId = auth()->id();

        if (! $user->is_suspended) {
            $user->suspend($actorId);
        }

        $held = $this->campaignHolds->holdForCreator(
            $user,
            CampaignAccountHoldService::REASON_TRASHED,
            $actorId,
            $request->ip(),
        );

        $user->delete();

        $this->audit->log($actorId, 'user.trashed', $user, [
            'email' => $oldEmail,
        ], [
            'user_id' => $user->id,
            'campaigns_held' => $held,
        ], $request->ip());

        return redirect()
            ->route('admin.trash')
            ->with('status', __('Account moved to trash. You can restore it later from System → Trash.'));
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
            $held = $this->campaignHolds->holdForCreator(
                $user,
                CampaignAccountHoldService::REASON_SUSPENDED,
                $adminId,
                $request->ip(),
            );
            $this->audit->log($adminId, 'user.suspended', $user, null, [
                'user_id' => $user->id,
                'is_suspended' => true,
                'bulk' => true,
                'campaigns_held' => $held,
            ], $request->ip());
            $this->accountStatus->suspended($user);
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
            $released = $this->campaignHolds->releaseForCreator($user, $adminId, $request->ip());
            $this->audit->log($adminId, 'user.restored', $user, null, [
                'user_id' => $user->id,
                'is_suspended' => false,
                'bulk' => true,
                'campaigns_released' => $released,
            ], $request->ip());
            $this->accountStatus->restored($user);
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
            if ($user->hasRole('admin') || $user->anonymized_at !== null || $user->id === $adminId) {
                $skipped++;

                continue;
            }

            $oldEmail = $user->email;

            try {
                if (! $user->is_suspended) {
                    $user->suspend($adminId);
                }
                $held = $this->campaignHolds->holdForCreator(
                    $user,
                    CampaignAccountHoldService::REASON_TRASHED,
                    $adminId,
                    $request->ip(),
                );
                $user->delete();
                $ok = true;
            } catch (\Throwable $e) {
                report($e);
                $skipped++;

                continue;
            }

            if (! $ok) {
                $skipped++;

                continue;
            }

            $this->audit->log($adminId, 'user.trashed', $user, [
                'email' => $oldEmail,
            ], [
                'user_id' => $user->id,
                'campaigns_held' => $held,
                'bulk' => true,
            ], $request->ip());
            $deleted++;
        }

        $status = __('Moved :count account(s) to trash.', ['count' => $deleted]);
        if ($skipped > 0) {
            $status .= ' '.__(':skipped skipped.', ['skipped' => $skipped]);
        }

        return redirect()
            ->route('admin.trash')
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
}
