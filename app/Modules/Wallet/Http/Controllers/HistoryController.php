<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Transaction::where('user_id', auth()->id())->orderByDesc('created_at');

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        return view('dashboard.user.history', [
            'transactions' => $query->paginate(20)->withQueryString(),
            'layout' => \App\Support\MemberShell::layout(),
            'prefix' => \App\Support\MemberShell::prefix(),
        ]);
    }
}
