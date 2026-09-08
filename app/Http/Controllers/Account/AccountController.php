<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function profile(Request $request): View
    {
        return $this->view($request, 'profile', ['user' => $request->user()]);
    }

    public function updateProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email') && ! $user->hasRole('admin')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route($this->routeName($request, 'profile'))
            ->with('status', 'profile-updated');
    }

    public function security(Request $request): View
    {
        $user = $request->user();
        $googleLinked = $user->hasAuthProvider(\App\Models\UserAuthProvider::GOOGLE);
        $canDisconnectGoogle = app(\App\Services\Auth\Identity\SocialAuthService::class)->canDisconnectGoogle($user);

        return $this->view($request, 'security', [
            'user' => $user,
            'passwordIsSet' => $user->hasPasswordSet(),
            'googleLinked' => $googleLinked,
            'canDisconnectGoogle' => $canDisconnectGoogle,
            'googleIdentityConfig' => \App\Services\Auth\Identity\GoogleIdentityProvider::configForFrontend(),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_if($request->user()->hasRole('admin'), 403);

        if (! $request->user()->hasPasswordSet()) {
            return Redirect::route($this->routeName($request, 'security'))
                ->withErrors([
                    'userDeletion' => __('Set a password before deleting your account.'),
                ]);
        }

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        Auth::logout();

        $user->anonymize();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::route('home');
    }

    public function notifications(Request $request): View|RedirectResponse
    {
        if (self::routePrefix($request) === 'admin') {
            return Redirect::route('admin.inbox');
        }

        $user = $request->user();
        $unreadCount = (\Illuminate\Support\Facades\Schema::hasTable('user_notifications') && $user)
            ? (int) $user->notifications()->whereNull('read_at')->count()
            : 0;

        return $this->view($request, 'notifications', [
            'unreadCount' => $unreadCount,
        ]);
    }

    public function kyc(Request $request): View|RedirectResponse
    {
        abort_unless(in_array(self::routePrefix($request), ['dashboard', 'agent'], true), 404);

        $user = $request->user();
        $submission = $user->kycSubmissions()->latest()->first();
        $kycRequired = \App\Models\SystemSetting::kycRequired();

        return $this->view($request, 'kyc', [
            'submission' => $submission,
            'kycLevel' => (int) $user->kyc_level,
            'kycRequired' => $kycRequired,
        ]);
    }

    public function preferences(Request $request): View
    {
        return $this->view($request, 'preferences');
    }

    public function sessions(Request $request): View
    {
        $table = config('session.table', 'sessions');
        $sessionsAvailable = config('session.driver') === 'database'
            && Schema::hasTable($table);

        $sessions = collect();

        if ($sessionsAvailable) {
            $sessions = DB::table($table)
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->orderByDesc('last_activity')
                ->get()
                ->map(function ($session) use ($request) {
                    $session->is_current = $session->id === $request->session()->getId();
                    $session->last_active_at = now()->setTimestamp($session->last_activity);

                    return $session;
                });
        }

        return $this->view($request, 'sessions', [
            'sessions' => $sessions,
            'sessionsAvailable' => $sessionsAvailable,
        ]);
    }

    public function revokeSession(Request $request, string $session): RedirectResponse
    {
        abort_unless(
            config('session.driver') === 'database'
                && Schema::hasTable(config('session.table', 'sessions')),
            422,
            'Session listing is unavailable for the current session driver.',
        );

        abort_if(hash_equals($request->session()->getId(), $session), 422, 'The current session cannot be revoked here.');

        DB::table(config('session.table', 'sessions'))
            ->where('id', $session)
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->delete();

        return back()->with('status', 'session-revoked');
    }

    public static function routePrefix(Request $request): string
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return 'admin';
        }

        if ($user->hasRole('agent')) {
            return 'agent';
        }

        return 'dashboard';
    }

    private function routeName(Request $request, string $page): string
    {
        return self::routePrefix($request).'.account.'.$page;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function view(Request $request, string $page, array $data = []): View
    {
        $prefix = self::routePrefix($request);
        $payload = array_merge($data, [
            'layout' => match ($prefix) {
                'admin' => 'layouts.dashboard-admin',
                'agent' => 'layouts.dashboard-agent',
                default => 'layouts.dashboard-user',
            },
            'prefix' => $prefix,
        ]);

        if ($request->headers->get('X-Dashboard-Tab') === '1') {
            return view('account.partials.panel-'.$page, $payload);
        }

        return view('account.'.$page, $payload);
    }
}
