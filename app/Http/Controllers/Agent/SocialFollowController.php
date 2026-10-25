<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentSocialFollowProof;
use App\Services\Campaigns\AgentSocialFollowService;
use App\Services\Communications\Social\SocialLinkRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialFollowController extends Controller
{
    public function __construct(
        private AgentSocialFollowService $follows,
        private SocialLinkRepository $socials,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if ($this->socials->enabled()->isEmpty()) {
            return redirect()
                ->route('agent.marketplace')
                ->with('status', __('No follow task is required right now.'));
        }

        $submission = $this->follows->ensureSubmission($request->user());

        return view('dashboard.agent.social-follow.show', [
            'submission' => $submission,
            'cleared' => $this->follows->isCleared($request->user()),
        ]);
    }

    public function saveProof(Request $request, AgentSocialFollowProof $proof): RedirectResponse
    {
        $proof->loadMissing('submission');
        abort_unless((int) ($proof->submission?->user_id) === (int) $request->user()->id, 404);

        $validated = $request->validate([
            'proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        try {
            $this->follows->saveProof($request->user(), $proof, $validated['proof']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Screenshot saved. Continue with the remaining accounts.'));
    }

    public function submit(Request $request): RedirectResponse
    {
        try {
            $this->follows->submit($request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('agent.social-follow')
            ->with('status', __('Submitted for review. You will get an email when it is approved.'));
    }
}
