<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentYoutubeIdentity;
use App\Services\Campaigns\YoutubeIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class YoutubeIdentityAdminController extends Controller
{
    private const FILTERS = [
        'review' => 'Needs review',
        'change' => 'Change requests',
        'pending' => 'Waiting for code',
        'failed' => 'Failed',
        'verified' => 'Verified',
        'all' => 'All',
    ];

    public function __construct(private YoutubeIdentityService $identities) {}

    public function index(Request $request): View
    {
        $filter = array_key_exists((string) $request->query('filter'), self::FILTERS)
            ? (string) $request->query('filter')
            : 'review';

        $role = in_array($request->query('role'), ['agent', 'user'], true) ? (string) $request->query('role') : null;

        $query = AgentYoutubeIdentity::query()->with(['user:id,name,email', 'user.roles:id,name'])->latest('id');
        if ($role) {
            $query->whereHas('user', fn ($u) => $u->role($role));
        }

        match ($filter) {
            'review' => $query->where('status', AgentYoutubeIdentity::STATUS_PENDING)->where('method', AgentYoutubeIdentity::METHOD_ADMIN),
            'change' => $query->where('status', AgentYoutubeIdentity::STATUS_CHANGE_REQUESTED),
            'pending' => $query->where('status', AgentYoutubeIdentity::STATUS_PENDING),
            'failed' => $query->where('status', AgentYoutubeIdentity::STATUS_FAILED),
            'verified' => $query->where('status', AgentYoutubeIdentity::STATUS_VERIFIED),
            default => null,
        };

        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->query('q')).'%';
            $query->where(function ($q) use ($term) {
                $q->where('channel_id', 'like', $term)
                    ->orWhere('handle', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term));
            });
        }

        $counts = [
            'review' => AgentYoutubeIdentity::query()->where('status', AgentYoutubeIdentity::STATUS_PENDING)->where('method', AgentYoutubeIdentity::METHOD_ADMIN)->count(),
            'change' => AgentYoutubeIdentity::query()->where('status', AgentYoutubeIdentity::STATUS_CHANGE_REQUESTED)->count(),
        ];

        return view('dashboard.admin.youtube-identities.index', [
            'identities' => $query->paginate(25)->withQueryString(),
            'filter' => $filter,
            'filters' => self::FILTERS,
            'role' => $role,
            'counts' => $counts,
        ]);
    }

    public function approve(Request $request, AgentYoutubeIdentity $identity): RedirectResponse
    {
        $data = $request->validate([
            'channel_id' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $this->identities->adminApprove($identity, $request->user(), $data['channel_id'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Channel approved.'));
    }

    public function reject(Request $request, AgentYoutubeIdentity $identity): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $wasVerified = $identity->isVerified();
        $this->identities->adminReject($identity, $request->user(), $data['reason']);

        return back()->with('status', $wasVerified
            ? ($identity->user?->isAgent()
                ? __('Channel link revoked. The agent must link a channel again before starting YouTube tasks.')
                : __('Channel link revoked.'))
            : __('Rejected.'));
    }

    public function approveChange(Request $request, AgentYoutubeIdentity $identity): RedirectResponse
    {
        try {
            $this->identities->approveChange($identity, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Change approved. The agent must now verify the new channel.'));
    }
}
