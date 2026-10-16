<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AccountRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly AccountRegistration $registration,
    ) {}

    /**
     * Display the creator registration view.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->filled('ref') || $request->query('as') === 'agent') {
            return $this->redirectToAgentOnboarding($request);
        }

        return view('auth.register');
    }

    /**
     * Live username availability check for the registration form.
     */
    public function checkUsername(Request $request): JsonResponse
    {
        $username = trim((string) $request->query('username', ''));

        if ($username === '') {
            return response()->json(['available' => false, 'message' => 'Enter a username.']);
        }

        if (mb_strlen($username) > 255 || ! preg_match('/^[A-Za-z0-9_-]+$/', $username)) {
            return response()->json([
                'available' => false,
                'message' => 'Use letters, numbers, dashes, or underscores only.',
            ]);
        }

        if (! $this->usernameTaken($username)) {
            return response()->json(['available' => true, 'message' => 'Username is available.']);
        }

        return response()->json([
            'available' => false,
            'message' => 'This username is already taken.',
            'suggestion' => $this->suggestUsername($username),
        ]);
    }

    private function usernameTaken(string $username): bool
    {
        return User::query()->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->exists();
    }

    private function suggestUsername(string $base): ?string
    {
        $base = mb_substr($base, 0, 24);

        for ($i = 0; $i < 10; $i++) {
            $candidate = $base.'_'.random_int(10, 9999);
            if (! $this->usernameTaken($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Handle an incoming creator registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate($this->registration->rules());

        return $this->registration->register($request, 'user');
    }

    /**
     * Old agent signup and referral links now live on the agent onboarding page.
     */
    public function redirectToAgentOnboarding(Request $request): RedirectResponse
    {
        return redirect()->to(route('agent-onboarding', Arr::except($request->query(), ['as'])), 301);
    }
}
