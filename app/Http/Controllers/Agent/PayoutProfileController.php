<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\Auth\AccountRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Complete phone / country / address so Connect can choose on-site vs hosted by country.
 */
class PayoutProfileController extends Controller
{
    public function __construct(
        private AccountRegistration $registration,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->registration->payoutProfileRules());

        $user = $request->user();
        $user->forceFill([
            'phone' => (string) $validated['phone'],
            'country' => strtoupper((string) $validated['country']),
            'address_line1' => (string) $validated['address_line1'],
            'address_city' => (string) $validated['address_city'],
            'address_state' => (string) $validated['address_state'],
            'address_postal_code' => filled($validated['address_postal_code'] ?? null)
                ? (string) $validated['address_postal_code']
                : null,
        ])->save();

        $user->syncPayoutProfileCompletion();

        return back()->with('status', __('Profile updated. You can connect your bank account now.'));
    }
}
