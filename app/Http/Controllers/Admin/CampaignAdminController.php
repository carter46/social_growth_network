<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignAdminController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $query = Campaign::query()
            ->with(['creator', 'product'])
            ->orderByDesc('created_at');

        if ($status !== '' && in_array($status, Campaign::STATUSES, true)) {
            $query->where('status', $status);
        }

        return view('dashboard.admin.campaigns.index', [
            'campaigns' => $query->paginate(25)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(Campaign $campaign): View
    {
        $campaign->load(['creator', 'product', 'participations.agent']);

        return view('dashboard.admin.campaigns.show', [
            'campaign' => $campaign,
        ]);
    }

    public function updateStatus(Request $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', Campaign::STATUSES)],
        ]);

        $campaign->update(['status' => $validated['status']]);

        return back()->with('status', __('Campaign status updated.'));
    }
}
