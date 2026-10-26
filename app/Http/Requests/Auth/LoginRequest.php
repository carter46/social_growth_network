<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email or username',
        ];
    }

    /**
     * The login field accepts an email or a username. Usernames are alpha_dash, so an "@" means email.
     *
     * @return array<string, string>
     */
    private function credentials(): array
    {
        $identifier = trim((string) $this->input('email'));

        if (str_contains($identifier, '@')) {
            return ['email' => $identifier, 'password' => (string) $this->input('password')];
        }

        $username = User::query()
            ->whereRaw('LOWER(username) = ?', [mb_strtolower($identifier)])
            ->value('username');

        return ['username' => $username ?? $identifier, 'password' => (string) $this->input('password')];
    }

    private function findInactiveTrashedUser(): ?User
    {
        $identifier = trim((string) $this->input('email'));
        if ($identifier === '') {
            return null;
        }

        $query = User::onlyTrashed()->whereNull('anonymized_at');

        if (str_contains($identifier, '@')) {
            return $query->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])->first();
        }

        return $query->whereRaw('LOWER(username) = ?', [mb_strtolower($identifier)])->first();
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $inactive = $this->findInactiveTrashedUser();
        if ($inactive && ! $inactive->hasRole('admin')) {
            // Password must match before disclosing inactive / Activate CTA (enumeration control).
            if (! Hash::check((string) $this->input('password'), (string) $inactive->password)) {
                RateLimiter::hit($this->throttleKey());

                throw ValidationException::withMessages([
                    'email' => trans('auth.failed'),
                ]);
            }

            RateLimiter::hit($this->throttleKey());
            $this->session()->flash('account_inactive_email', $inactive->email);
            $this->session()->flash('account_inactive', true);

            throw ValidationException::withMessages([
                'email' => __('Your account is currently inactive. Activate it to sign in again.'),
            ]);
        }

        if (! Auth::attempt($this->credentials(), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (Auth::user()?->is_suspended) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => __('Your account has been suspended. If you think this is a mistake, please contact our support team.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
