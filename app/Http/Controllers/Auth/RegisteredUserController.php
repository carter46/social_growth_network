<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Referrals\ReferralAttribution;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(
        private ReferralAttribution $referrals,
    ) {}

    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        return view('auth.register', [
            'referralCode' => $this->rememberReferral($request),
        ]);
    }

    /**
     * Display agent registration (pre-selects Agent on step 1).
     */
    public function createAgent(Request $request): View
    {
        return view('auth.register', [
            'registerAsAgent' => true,
            'referralCode' => $this->rememberReferral($request),
        ]);
    }

    private function rememberReferral(Request $request): ?string
    {
        return $this->referrals->remember($request);
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
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'account_type' => ['required', 'in:creator,agent'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username', 'alpha_dash'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
            'referral_code' => [
                'nullable',
                'string',
                'max:32',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $this->referrals->findReferrer((string) $value)) {
                        $fail(__('This referral code is not valid.'));
                    }
                },
            ],
        ]);

        $role = $request->string('account_type')->toString() === 'agent' ? 'agent' : 'user';

        return $this->registerWithRole($request, $role);
    }

    /**
     * Handle an incoming agent registration request (legacy endpoint).
     *
     * @throws ValidationException
     */
    public function storeAgent(Request $request): RedirectResponse
    {
        $request->merge(['account_type' => 'agent']);

        return $this->store($request);
    }

    /**
     * @throws ValidationException
     */
    private function registerWithRole(Request $request, string $role): RedirectResponse
    {
        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
            'password_set_at' => now(),
            'terms_accepted_at' => now(),
        ]);

        $user->assignRole($role);

        $this->referrals->attach($user, $request->has('referral_code')
            ? (string) $request->input('referral_code')
            : $this->referrals->rememberedCode($request));

        if ($role === 'agent') {
            $user->ensureReferralCode();
        }

        event(new Registered($user));
        UserRegistered::dispatch($user->id);

        Auth::login($user);

        OtpVerificationController::createAndSendOtp($user);

        return redirect()->route('verification.notice');
    }
}
