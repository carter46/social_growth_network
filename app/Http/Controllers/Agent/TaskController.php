<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\CampaignParticipation;
use App\Services\Campaigns\CampaignParticipationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(
        private CampaignParticipationService $participations,
    ) {}

    public function active(): View
    {
        return $this->list(['started', 'submitted', 'under_review'], 'Active tasks');
    }

    public function incomplete(): View
    {
        return $this->list(['rejected'], 'Incomplete tasks');
    }

    public function completed(): View
    {
        return $this->list(['approved', 'paid'], 'Completed tasks');
    }

    public function show(CampaignParticipation $participation): View
    {
        $this->authorizeAgent($participation);

        return view('dashboard.agent.tasks.show', [
            'participation' => $participation->load('campaign.product'),
        ]);
    }

    public function submit(Request $request, CampaignParticipation $participation): RedirectResponse
    {
        $this->authorizeAgent($participation);

        $validated = $request->validate([
            'proof_url' => ['nullable', 'url', 'max:2048'],
            'proof_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $this->participations->submit($participation, $validated);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('agent.tasks.show', $participation)
            ->with('status', __('Submission sent for verification.'));
    }

    /**
     * @param  list<string>  $statuses
     */
    private function list(array $statuses, string $title): View
    {
        $participations = CampaignParticipation::query()
            ->where('agent_id', auth()->id())
            ->whereIn('status', $statuses)
            ->with('campaign')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('dashboard.agent.tasks.index', [
            'participations' => $participations,
            'title' => $title,
        ]);
    }

    private function authorizeAgent(CampaignParticipation $participation): void
    {
        abort_unless((int) $participation->agent_id === (int) auth()->id(), 403);
    }
}
