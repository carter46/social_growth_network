<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view (combined auth with signup section active).
     */
    public function create(): View
    {
        return view('auth.login', ['showSignup' => true]);
    }

    /**
     * Display agent registration (combined auth with signup section active).
     */
    public function createAgent(): View
    {
        return view('auth.login', [
            'showSignup' => true,
            'registerAsAgent' => true,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->registerWithRole($request, 'user');
    }

    /**
     * Handle an incoming agent registration request.
     *
     * @throws ValidationException
     */
    public function storeAgent(Request $request): RedirectResponse
    {
        return $this->registerWithRole($request, 'agent');
    }

    /**
     * @throws ValidationException
     */
    private function registerWithRole(Request $request, string $role): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username', 'alpha_dash'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
            'password_set_at' => now(),
            'terms_accepted_at' => now(),
        ]);

        $user->assignRole($role);

        event(new Registered($user));
        UserRegistered::dispatch($user->id);

        Auth::login($user);

        OtpVerificationController::createAndSendOtp($user);

        return redirect()->route('verification.notice');
    }
}
