<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $query = Campaign::query()
            ->where('creator_id', auth()->id())
            ->with('product')
            ->orderByDesc('created_at');

        if ($status !== '' && in_array($status, Campaign::STATUSES, true)) {
            $query->where('status', $status);
        }

        return view('dashboard.user.campaigns.index', [
            'campaigns' => $query->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(Campaign $campaign): View
    {
        abort_unless((int) $campaign->creator_id === (int) auth()->id(), 403);

        $campaign->load(['product', 'participations.agent']);

        return view('dashboard.user.campaigns.show', [
            'campaign' => $campaign,
        ]);
    }
}
