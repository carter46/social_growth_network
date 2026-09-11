<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Engagement\YoutubeDataApiProbe;
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
            'tiktokClientKey' => (string) SystemSetting::get('api_tiktok_client_key', ''),
            'tiktokClientSecret' => (string) SystemSetting::get('api_tiktok_client_secret', ''),
            'metaAppId' => (string) SystemSetting::get('api_meta_app_id', ''),
            'metaAppSecret' => (string) SystemSetting::get('api_meta_app_secret', ''),
            'metaAccessToken' => (string) SystemSetting::get('api_meta_access_token', ''),
            'xBearerToken' => (string) SystemSetting::get('api_x_bearer_token', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:youtube,tiktok,meta,x'],
            'api_youtube_data_key' => ['nullable', 'string', 'max:255'],
            'api_tiktok_client_key' => ['nullable', 'string', 'max:255'],
            'api_tiktok_client_secret' => ['nullable', 'string', 'max:255'],
            'api_meta_app_id' => ['nullable', 'string', 'max:255'],
            'api_meta_app_secret' => ['nullable', 'string', 'max:255'],
            'api_meta_access_token' => ['nullable', 'string', 'max:2000'],
            'api_x_bearer_token' => ['nullable', 'string', 'max:2000'],
        ]);

        match ($data['channel']) {
            'youtube' => SystemSetting::set('api_youtube_data_key', trim((string) ($data['api_youtube_data_key'] ?? ''))),
            'tiktok' => tap(null, function () use ($data) {
                SystemSetting::set('api_tiktok_client_key', trim((string) ($data['api_tiktok_client_key'] ?? '')));
                SystemSetting::set('api_tiktok_client_secret', trim((string) ($data['api_tiktok_client_secret'] ?? '')));
            }),
            'meta' => tap(null, function () use ($data) {
                SystemSetting::set('api_meta_app_id', trim((string) ($data['api_meta_app_id'] ?? '')));
                SystemSetting::set('api_meta_app_secret', trim((string) ($data['api_meta_app_secret'] ?? '')));
                SystemSetting::set('api_meta_access_token', trim((string) ($data['api_meta_access_token'] ?? '')));
            }),
            'x' => SystemSetting::set('api_x_bearer_token', trim((string) ($data['api_x_bearer_token'] ?? ''))),
        };

        return back()->with('status', __('API integration settings saved. Count probes use scrape fallback when APIs are unavailable.'));
    }

    public function test(Request $request, YoutubeDataApiProbe $youtube): RedirectResponse
    {
        $channel = $request->validate([
            'channel' => ['required', 'in:youtube,tiktok,meta,x'],
        ])['channel'];

        if ($channel === 'youtube') {
            $key = trim((string) SystemSetting::get('api_youtube_data_key', ''));
            if ($key === '') {
                return back()->with('error', __('YouTube API key is not configured — scrape fallback will be used.'));
            }

            $response = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'id',
                'id' => 'dQw4w9WgXcQ',
                'key' => $key,
            ]);

            if ($response->successful()) {
                return back()->with('status', __('YouTube Data API connection succeeded.'));
            }

            return back()->with('error', __('YouTube Data API test failed: :msg', [
                'msg' => $response->json('error.message') ?? $response->status(),
            ]));
        }

        return back()->with('status', __('Saved credentials for :channel. Full live tests depend on app approval; scrape fallback remains available.', [
            'channel' => strtoupper($channel),
        ]));
    }
}
