<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurgedUserRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PurgedUserRecordController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $records = PurgedUserRecord::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like, $q) {
                    $inner->where('email', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                    if (ctype_digit($q)) {
                        $inner->orWhere('original_user_id', (int) $q);
                    }
                });
            })
            ->orderByDesc('purged_at')
            ->paginate(20)
            ->withQueryString();

        // Generation-aware: same email/username may have a new live registration — show separately.
        $liveMatches = $q !== '' ? $this->liveMatchesForQuery($q) : collect();

        return view('dashboard.admin.purged-accounts.index', [
            'records' => $records,
            'liveMatches' => $liveMatches,
            'q' => $q,
        ]);
    }

    public function show(PurgedUserRecord $record): View
    {
        $liveWithSameEmail = null;
        if (filled($record->email)) {
            $liveWithSameEmail = User::query()
                ->whereNull('anonymized_at')
                ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $record->email)])
                ->whereHas('roles', fn ($r) => $r->whereIn('name', ['user', 'agent']))
                ->first();
        }

        return view('dashboard.admin.purged-accounts.show', [
            'record' => $record,
            'liveWithSameEmail' => $liveWithSameEmail,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function liveMatchesForQuery(string $q): Collection
    {
        $like = '%'.$q.'%';

        return User::query()
            ->whereNull('anonymized_at')
            ->whereHas('roles', fn ($r) => $r->whereIn('name', ['user', 'agent']))
            ->where(function ($query) use ($like, $q) {
                $query->where('email', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('phone', 'like', $like);
                if (ctype_digit($q)) {
                    $query->orWhere('id', (int) $q);
                }
            })
            ->with('roles')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }
}
