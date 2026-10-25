<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentSocialFollowProof;
use App\Models\AgentSocialFollowSubmission;
use App\Services\Campaigns\AgentSocialFollowService;
use App\Services\Communications\Social\SocialLinkRepository;
use Illuminate\Http\JsonResponse;
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
        $submission = $this->follows->expireIfNeeded($submission);

        $secondsRemaining = $submission?->secondsRemaining();
        $totalSeconds = $submission?->totalSeconds();

        return view('dashboard.agent.social-follow.show', [
            'submission' => $submission,
            'cleared' => $this->follows->isCleared($request->user()),
            'secondsRemaining' => $secondsRemaining,
            'totalSeconds' => $totalSeconds,
            'completionMinutes' => AgentSocialFollowSubmission::completionMinutesForCount(
                (int) ($submission?->proofs?->count() ?? $this->socials->enabled()->count())
            ),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        try {
            $this->follows->start($request->user());
        } catch (\Throwable $e) {
            return redirect()
                ->route('agent.social-follow')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('agent.social-follow');
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $submission = $this->follows->submissionFor($request->user());
        $submission = $this->follows->expireIfNeeded($submission);

        if (! $submission) {
            return response()->json(['expired' => true, 'status' => 'missing']);
        }

        $expired = $submission->isExpired();

        return response()->json([
            'expired' => $expired,
            'status' => $submission->status,
            'seconds_remaining' => $expired ? 0 : $submission->secondsRemaining(),
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

        return back()->with('status', __('Screenshot saved.'));
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
            ->with('status', __('Submitted for review.'));
    }
}
