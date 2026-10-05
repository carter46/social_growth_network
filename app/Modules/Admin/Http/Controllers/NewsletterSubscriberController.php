<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterSubscriberController extends Controller
{
    public function index(Request $request): View
    {
        $subscribers = $this->filtered($request)->paginate(30)->withQueryString();

        return view('dashboard.admin.newsletter', [
            'subscribers' => $subscribers,
            'activeCount' => NewsletterSubscriber::active()->count(),
            'filters' => [
                'q' => $request->string('q')->toString(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Email', 'Status', 'Source', 'Subscribed at', 'Unsubscribed at']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->email,
                        $row->isActive() ? 'Subscribed' : 'Unsubscribed',
                        $row->source,
                        $row->subscribed_at?->toDateTimeString(),
                        $row->unsubscribed_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request): Builder
    {
        $query = NewsletterSubscriber::query()->orderByDesc('subscribed_at')->orderByDesc('id');

        if ($search = trim($request->string('q')->toString())) {
            $query->where('email', 'like', '%'.$search.'%');
        }

        return $query;
    }
}
