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
                ->with('status', __('No priority task is required right now.'));
        }

        $submission = $this->follows->ensureSubmission($request->user());
        $submission = $this->follows->expireIfNeeded($submission);

        return view('dashboard.agent.social-follow.show', [
            'submission' => $submission,
            'cleared' => $this->follows->isCleared($request->user()),
            'secondsRemaining' => $submission?->secondsRemaining(),
            'totalSeconds' => $submission?->totalSeconds(),
            'completionMinutes' => AgentSocialFollowSubmission::completionMinutesForCount(
                (int) ($submission?->proofs?->count() ?? $this->socials->enabled()->count())
            ),
        ]);
    }

    public function start(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $this->follows->start($request->user());
        } catch (\Throwable $e) {
            return $this->followError($request, $e->getMessage());
        }

        return $this->followRedirect($request, __('Task started. Complete each step.'));
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

    public function saveProof(Request $request, AgentSocialFollowProof $proof): RedirectResponse|JsonResponse
    {
        $proof->loadMissing('submission');
        abort_unless((int) ($proof->submission?->user_id) === (int) $request->user()->id, 404);

        $validated = $request->validate([
            'proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'proof.required' => 'Upload a screenshot for this step.',
            'proof.max' => 'Screenshots must be 2 MB or smaller.',
        ]);

        try {
            $this->follows->saveProof($request->user(), $proof, $validated['proof']);
        } catch (\Throwable $e) {
            return $this->followError($request, $e->getMessage());
        }

        $submission = $this->follows->submissionFor($request->user());
        $remaining = $submission?->proofs->first(fn (AgentSocialFollowProof $p) => ! $p->isComplete());

        return $this->followRedirect(
            $request,
            $remaining
                ? __('Step saved. Continue with the next one.')
                : __('Every step is done. Submit the task when you are ready.')
        );
    }

    public function submit(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $this->follows->submit($request->user());
        } catch (\Throwable $e) {
            return $this->followError($request, $e->getMessage());
        }

        return $this->followRedirect($request, __('Submitted for review.'));
    }

    private function followRedirect(Request $request, string $status = ''): RedirectResponse|JsonResponse
    {
        if ($status !== '') {
            session()->flash('status', $status);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('agent.social-follow'),
            ]);
        }

        return redirect()->route('agent.social-follow');
    }

    private function followError(Request $request, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 422)
            : redirect()->route('agent.social-follow')->with('error', $message);
    }
}
