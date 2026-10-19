<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdministratorController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    public function index(): View
    {
        $administrators = User::role('admin')
            ->with('roles', 'permissions')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('dashboard.admin.administrators.index', [
            'administrators' => $administrators,
        ]);
    }

    public function create(): View
    {
        return view('dashboard.admin.administrators.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', \App\Support\UniqueUserRules::username()],
            'email' => ['required', 'string', 'email', 'max:255', \App\Support\UniqueUserRules::email()],
            'password' => ['required', 'confirmed', Password::defaults()],
            'grant_admins_manage' => ['sometimes', 'boolean'],
        ]);

        $admin = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        $admin->assignRole('admin');

        if ($request->boolean('grant_admins_manage')) {
            $admin->givePermissionTo('admins.manage');
        }

        $this->audit->log(auth()->id(), 'administrator.created', $admin, null, [
            'user_id' => $admin->id,
            'email' => $admin->email,
            'admins_manage' => $request->boolean('grant_admins_manage'),
        ], $request->ip());

        return redirect()
            ->route('admin.administrators')
            ->with('status', __('Administrator created.'));
    }

    public function edit(User $administrator): View
    {
        abort_unless($administrator->hasRole('admin'), 404);

        return view('dashboard.admin.administrators.edit', [
            'administrator' => $administrator,
        ]);
    }

    public function update(Request $request, User $administrator): RedirectResponse
    {
        abort_unless($administrator->hasRole('admin'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($administrator->id),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($administrator->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'grant_admins_manage' => ['sometimes', 'boolean'],
        ]);

        $old = $administrator->only(['name', 'username', 'email']);
        $wantsAdminsManage = $request->boolean('grant_admins_manage');

        if (! $wantsAdminsManage && $this->isLastAdminsManageHolder($administrator)) {
            return back()->withInput()->with('error', __('Cannot remove admins.manage from the last administrator who has it.'));
        }

        $administrator->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $administrator->password = $data['password'];
        }

        $administrator->save();

        if ($wantsAdminsManage) {
            $administrator->givePermissionTo('admins.manage');
        } else {
            $administrator->revokePermissionTo('admins.manage');
        }

        $this->audit->log(auth()->id(), 'administrator.updated', $administrator, $old, [
            'name' => $administrator->name,
            'username' => $administrator->username,
            'email' => $administrator->email,
            'admins_manage' => $administrator->can('admins.manage'),
            'password_changed' => ! empty($data['password']),
        ], $request->ip());

        return redirect()
            ->route('admin.administrators')
            ->with('status', __('Administrator updated.'));
    }

    public function suspend(Request $request, User $administrator): RedirectResponse
    {
        abort_unless($administrator->hasRole('admin'), 404);

        if ($administrator->id === auth()->id()) {
            return back()->with('error', __('You cannot suspend your own account.'));
        }

        if ($administrator->can('admins.manage') && $this->isLastAdminsManageHolder($administrator)) {
            return back()->with('error', __('Cannot suspend the last administrator with admins.manage.'));
        }

        $wasSuspended = (bool) $administrator->is_suspended;
        $administrator->suspend(auth()->id());

        $this->audit->log(auth()->id(), 'administrator.suspended', $administrator, null, [
            'user_id' => $administrator->id,
        ], $request->ip());

        if (! $wasSuspended) {
            app(\App\Services\Notifications\AccountStatusNotifier::class)->suspended($administrator);
        }

        return back()->with('status', __('Administrator suspended.'));
    }

    public function restore(Request $request, User $administrator): RedirectResponse
    {
        abort_unless($administrator->hasRole('admin'), 404);

        $wasSuspended = (bool) $administrator->is_suspended;
        $administrator->restoreAccess();

        $this->audit->log(auth()->id(), 'administrator.restored', $administrator, null, [
            'user_id' => $administrator->id,
        ], $request->ip());

        if ($wasSuspended) {
            app(\App\Services\Notifications\AccountStatusNotifier::class)->restored($administrator);
        }

        return back()->with('status', __('Administrator restored.'));
    }

    private function isLastAdminsManageHolder(User $administrator): bool
    {
        if (! $administrator->can('admins.manage')) {
            return false;
        }

        $holders = User::permission('admins.manage')
            ->role('admin')
            ->where('is_suspended', false)
            ->pluck('id');

        return $holders->count() <= 1 && $holders->contains($administrator->id);
    }
}
