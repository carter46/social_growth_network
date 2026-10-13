<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    public function start(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->can('users.manage'), 403);

        $admin = $request->user();

        if ($user->id === $admin->id) {
            return back()->with('error', __('You cannot impersonate yourself.'));
        }

        if ($user->hasRole('admin')) {
            return back()->with('error', __('Cannot impersonate an administrator.'));
        }

        if (! $user->isCreator() && ! $user->isAgent()) {
            return back()->with('error', __('Only creator and agent accounts can be impersonated.'));
        }

        if ($user->is_suspended) {
            return back()->with('error', __('Cannot impersonate a suspended user.'));
        }

        if ($user->anonymized_at !== null) {
            return back()->with('error', __('Cannot impersonate a deleted user.'));
        }

        if (session('impersonating')) {
            return back()->with('error', __('Already impersonating a user. Return to admin first.'));
        }

        session([
            'impersonator_id' => $admin->id,
            'impersonating' => true,
        ]);

        Auth::login($user, false);
        $request->session()->regenerate();

        $this->audit->log($admin->id, 'user.impersonation.started', $user, null, [
            'impersonator_id' => $admin->id,
            'target_user_id' => $user->id,
        ], $request->ip());

        return redirect()
            ->to($user->homeRoute())
            ->with('status', __('You are now impersonating :name.', ['name' => $user->name]));
    }

    public function leave(Request $request): RedirectResponse
    {
        if (! session('impersonating') || ! session('impersonator_id')) {
            return redirect()->route('dashboard');
        }

        $impersonatorId = (int) session('impersonator_id');
        $target = $request->user();

        $admin = User::query()->find($impersonatorId);

        if (! $admin || ! $admin->hasRole('admin') || $admin->is_suspended || $admin->anonymized_at !== null) {
            session()->forget(['impersonating', 'impersonator_id']);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', __('Unable to restore administrator session.'));
        }

        Auth::login($admin, false);
        session()->forget(['impersonating', 'impersonator_id']);
        $request->session()->regenerate();

        $this->audit->log($admin->id, 'user.impersonation.stopped', $target, null, [
            'impersonator_id' => $admin->id,
            'target_user_id' => $target?->id,
        ], $request->ip());

        $redirect = $target
            ? redirect()->route('admin.users.show', $target)
            : redirect()->route('admin.users');

        return $redirect->with('status', __('Returned to administrator account.'));
    }
}
