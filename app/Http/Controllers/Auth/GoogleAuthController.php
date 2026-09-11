<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\Identity\SocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly SocialAuthService $socialAuth,
    ) {}

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['required', 'string'],
            'account_type' => ['nullable', 'in:creator,agent'],
        ]);

        try {
            $result = $this->socialAuth->authenticateWithGoogle(
                $validated['credential'],
                $validated['account_type'] ?? null,
            );
        } catch (\Throwable $e) {
            return $this->failure($request, $e->getMessage());
        }

        Auth::login($result['user'], true);
        $request->session()->regenerate();

        $target = $result['user']->hasVerifiedEmail()
            ? redirect()->intended($result['user']->homeRoute())->getTargetUrl()
            : route('verification.notice');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['redirect' => $target]);
        }

        return redirect()->to($target);
    }

    public function link(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        try {
            $this->socialAuth->linkGoogle($request->user(), $validated['credential']);
        } catch (\Throwable $e) {
            return $this->failure($request, $e->getMessage());
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'redirect' => route($this->accountSecurityRouteName($request)),
                'status' => 'google-linked',
            ]);
        }

        return redirect()
            ->route($this->accountSecurityRouteName($request))
            ->with('status', 'google-linked');
    }

    public function unlink(Request $request): RedirectResponse
    {
        try {
            $this->socialAuth->unlinkGoogle($request->user());
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'google' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route($this->accountSecurityRouteName($request))
            ->with('status', 'google-unlinked');
    }

    private function failure(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 422);
        }

        throw ValidationException::withMessages([
            'google' => $message,
        ]);
    }

    private function accountSecurityRouteName(Request $request): string
    {
        if ($request->user()?->hasRole('admin')) {
            return 'admin.account.security';
        }

        return 'dashboard.account.security';
    }
}
