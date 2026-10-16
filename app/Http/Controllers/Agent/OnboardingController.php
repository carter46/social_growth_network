<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\Campaigns\YoutubeIdentityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Optional getting-started guide for new agents. It never gates access; the channel
 * is still required when the agent applies for a YouTube task.
 */
class OnboardingController extends Controller
{
    public function __construct(
        private readonly YoutubeIdentityService $identities,
    ) {}

    public function show(Request $request): View
    {
        $identity = $this->identities->current($request->user());

        return view('dashboard.agent.onboarding', [
            'identity' => $identity,
            'channelVerified' => (bool) $identity?->isVerified(),
            'returnPath' => route('agent.onboarding', [], false),
        ]);
    }
}
