<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampaignParticipation;
use App\Services\Campaigns\CampaignParticipationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationAdminController extends Controller
{
    public function __construct(
        private CampaignParticipationService $participations,
    ) {}

    public function index(): View
    {
        $queue = CampaignParticipation::query()
            ->whereIn('status', [
                CampaignParticipation::STATUS_SUBMITTED,
                CampaignParticipation::STATUS_UNDER_REVIEW,
                CampaignParticipation::STATUS_VERIFYING,
            ])
            ->with(['campaign', 'agent'])
            ->orderBy('submitted_at')
            ->paginate(25);

        return view('dashboard.admin.verifications.index', [
            'queue' => $queue,
        ]);
    }

    public function show(CampaignParticipation $participation): View
    {
        return view('dashboard.admin.verifications.show', [
            'participation' => $participation->load(['campaign.product', 'agent']),
        ]);
    }

    public function approve(CampaignParticipation $participation): RedirectResponse
    {
        try {
            $this->participations->approve($participation, auth()->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.verifications')
            ->with('status', __('Submission approved and agent rewarded.'));
    }

    public function reject(Request $request, CampaignParticipation $participation): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->participations->reject($participation, auth()->user(), $validated['rejection_reason']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.verifications')
            ->with('status', __('Submission rejected.'));
    }
}
