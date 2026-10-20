<?php

namespace App\Services\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Models\User;
use App\Services\Referrals\ReferralAttribution;
use App\Support\PhoneNumber;
use App\Support\UniqueUserRules;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

/**
 * Account creation shared by creator registration and agent onboarding.
 */
class AccountRegistration
{
    public function __construct(
        private readonly ReferralAttribution $referrals,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash', UniqueUserRules::username()],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', UniqueUserRules::email()],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ];
    }

    /**
     * Extra fields for agent signup so Connect can choose on-site vs hosted by country.
     *
     * @return array<string, mixed>
     */
    public function agentProfileRules(): array
    {
        $countries = array_keys((array) config('payout_methods.countries', []));

        return [
            'phone' => PhoneNumber::e164Rules(true),
            'country' => ['required', 'string', 'size:2', Rule::in($countries !== [] ? $countries : ['US'])],
            'address_line1' => ['required', 'string', 'max:160'],
            'address_city' => ['required', 'string', 'max:80'],
            'address_state' => ['required', 'string', 'max:80'],
            'address_postal_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payoutProfileRules(): array
    {
        return $this->agentProfileRules();
    }

    /**
     * @return array<int, mixed>
     */
    public function requiredReferralCodeRules(): array
    {
        return [
            'required',
            'string',
            'max:32',
            function (string $attribute, mixed $value, \Closure $fail) {
                if (! $this->referrals->isValidInvite((string) $value)) {
                    $fail(__('This invite code is not valid.'));
                }
            },
        ];
    }

    public function register(Request $request, string $role, ?string $referralCode = null): RedirectResponse
    {
        $attrs = [
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
            'password_set_at' => now(),
            'terms_accepted_at' => now(),
        ];

        if ($role === 'agent') {
            $attrs['phone'] = (string) $request->input('phone');
            $attrs['country'] = strtoupper((string) $request->input('country'));
            $attrs['address_line1'] = (string) $request->input('address_line1');
            $attrs['address_city'] = (string) $request->input('address_city');
            $attrs['address_state'] = (string) $request->input('address_state');
            $attrs['address_postal_code'] = $request->filled('address_postal_code')
                ? (string) $request->input('address_postal_code')
                : null;
            $attrs['profile_completed_at'] = now();
        }

        $user = User::create($attrs);

        $user->assignRole($role);

        if ($role === 'agent') {
            $this->referrals->attach($user, $referralCode);
            $user->ensureReferralCode();
        }

        event(new Registered($user));
        UserRegistered::dispatch($user->id);

        Auth::login($user);

        // Laravel's Registered listener already emails a code; a second one would make the first email's code fail.
        if (! OtpVerificationController::hasRecentCode($user)) {
            OtpVerificationController::createAndSendOtp($user);
        }

        return redirect()->route('verification.notice');
    }
}
