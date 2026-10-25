<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentSocialFollowSubmission;
use App\Services\Campaigns\AgentSocialFollowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialFollowAdminController extends Controller
{
    public function __construct(
        private AgentSocialFollowService $follows,
    ) {}

    public function index(): View
    {
        return view('dashboard.admin.social-follows.index', [
            'queue' => $this->follows->adminQueue(),
        ]);
    }

    public function show(AgentSocialFollowSubmission $submission): View
    {
        $submission->load(['user', 'proofs', 'reviewer']);

        return view('dashboard.admin.social-follows.show', [
            'submission' => $submission,
        ]);
    }

    public function approve(Request $request, AgentSocialFollowSubmission $submission): RedirectResponse
    {
        try {
            $this->follows->approve($submission, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.social-follows')
            ->with('status', __('Follow task approved. The agent can start marketplace tasks.'));
    }

    public function reject(Request $request, AgentSocialFollowSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->follows->reject($submission, $request->user(), $validated['reason']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.social-follows')
            ->with('status', __('Follow task rejected. The agent was notified.'));
    }
}
