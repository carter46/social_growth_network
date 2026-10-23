<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AccountRegistration;
use App\Services\Referrals\ReferralAttribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Invite-only agent signup. Every new agent needs a valid code from an active agent.
 */
class AgentOnboardingController extends Controller
{
    public function __construct(
        private readonly AccountRegistration $registration,
        private readonly ReferralAttribution $referrals,
    ) {}

    public function create(Request $request): View
    {
        return view('auth.agent-onboarding', [
            'inviteCode' => $this->referrals->remember($request),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            $this->registration->rules() + $this->registration->agentProfileRules() + [
                'referral_code' => $this->registration->requiredReferralCodeRules(),
            ],
            $this->registration->messages(),
        );

        return $this->registration->register($request, 'agent', (string) $request->input('referral_code'));
    }
}
