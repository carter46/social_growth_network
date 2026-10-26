<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Users\AccountReactivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountReactivationController extends Controller
{
    public function __construct(
        private AccountReactivationService $reactivation,
    ) {}

    public function create(Request $request): View
    {
        return view('auth.reactivate', [
            'email' => old('email', (string) $request->query('email', '')),
            'forAgents' => (bool) $request->boolean('agent'),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->reactivation->requestOtp($validated['email'], (string) $request->ip());

        return redirect()
            ->route('account.reactivate.verify', [
                'email' => $validated['email'],
                'agent' => $request->boolean('agent') ? 1 : null,
            ])
            ->with('status', __('If that account is inactive, we sent a code to the registered email.'));
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $email = old('email', (string) $request->query('email', ''));
        if ($email === '') {
            return redirect()->route('account.reactivate');
        }

        return view('auth.reactivate-verify', [
            'email' => $email,
            'forAgents' => (bool) $request->boolean('agent'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'string'],
        ]);

        $this->reactivation->verifyAndRestore(
            $validated['email'],
            $validated['otp'],
            (string) $request->ip(),
        );

        $login = $request->boolean('agent') ? 'agent-login' : 'login';

        return redirect()
            ->route($login)
            ->with('status', __('Your account is active again. Please sign in.'))
            ->withInput(['email' => $validated['email']]);
    }
}
