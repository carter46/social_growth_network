<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class ApiIntegrationsController extends Controller
{
    public function index(): View
    {
        return view('dashboard.admin.settings.api-integrations', [
            'youtubeKey' => (string) SystemSetting::get('api_youtube_data_key', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:youtube'],
            'api_youtube_data_key' => ['nullable', 'string', 'max:255'],
        ]);

        SystemSetting::set('api_youtube_data_key', trim((string) ($data['api_youtube_data_key'] ?? '')));

        return back()->with('status', __('API integration settings saved. Count probes use scrape fallback when APIs are unavailable.'));
    }

    public function test(Request $request): RedirectResponse
    {
        $request->validate([
            'channel' => ['required', 'in:youtube'],
        ]);

        $key = trim((string) SystemSetting::get('api_youtube_data_key', ''));
        if ($key === '') {
            return back()->with('error', __('YouTube API key is not configured, so scrape fallback will be used.'));
        }

        try {
            $response = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'id',
                'id' => 'dQw4w9WgXcQ',
                'key' => $key,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', __('YouTube Data API test failed: :msg', ['msg' => $e->getMessage()]));
        }

        if ($response->successful()) {
            return back()->with('status', __('YouTube Data API connection succeeded.'));
        }

        return back()->with('error', __('YouTube Data API test failed: :msg', [
            'msg' => $response->json('error.message') ?? $response->status(),
        ]));
    }
}