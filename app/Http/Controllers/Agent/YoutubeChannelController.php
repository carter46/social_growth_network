<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Campaigns\YoutubeIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class YoutubeChannelController extends Controller
{
    public function __construct(
        private YoutubeIdentityService $identities,
    ) {}

    public function show(Request $request): View
    {
        $agent = $request->user();

        return view('dashboard.agent.youtube-channel', [
            'identity' => $this->identities->current($agent),
            'changeRequest' => $this->identities->pendingChangeRequest($agent),
            'oauthEnabled' => YoutubeIdentityService::oauthEnabled(),
            'returnTo' => $this->safeReturn($request->query('return')),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['channel_url' => ['required', 'string', 'max:500']]);

        try {
            return response()->json(['channel' => $this->identities->preview($data['channel_url'])]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'channel_url' => ['required', 'string', 'max:500'],
            'return' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $identity = $this->identities->begin($request->user(), $data['channel_url']);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->done($request, $identity, __('Almost there. Add your code to your channel description, then choose "I\'ve added the code".'), $data['return'] ?? null);
    }

    public function check(Request $request): RedirectResponse|JsonResponse
    {
        $identity = $this->identities->current($request->user());
        if (! $identity || ! $identity->isPending()) {
            return $this->fail($request, __('There is no pending verification to check.'));
        }

        try {
            $identity = $this->identities->check($identity);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($request, $e->getMessage());
        }

        $message = $identity->isVerified()
            ? __('Your YouTube channel is verified. You can start YouTube tasks now.')
            : __('We haven\'t found the code yet. We\'ll keep checking every 30 minutes for up to 24 hours. Make sure the code is saved in your channel description and the channel is public.');

        return $this->done($request, $identity, $message, $request->input('return'));
    }

    public function retry(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $identity = $this->identities->retry($request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->done($request, $identity, __('Here is a new code. Add it to your channel description, then choose "I\'ve added the code".'), $request->input('return'));
    }

    public function requestChange(Request $request): RedirectResponse
    {
        $data = $request->validate(['channel_url' => ['required', 'string', 'max:500']]);

        try {
            $this->identities->requestChange($request->user(), $data['channel_url']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Change request sent. An admin will review it. Your current channel stays linked until then.'));
    }

    /** Path A: Google consent with the read-only YouTube scope. */
    public function oauthRedirect(Request $request): RedirectResponse
    {
        abort_unless(YoutubeIdentityService::oauthEnabled(), 404);

        $state = Str::random(40);
        $request->session()->put('youtube_oauth_state', $state);
        $request->session()->put('youtube_oauth_return', $this->safeReturn($request->query('return')));

        $query = http_build_query([
            'client_id' => trim((string) SystemSetting::get('youtube_oauth_client_id', '')),
            'redirect_uri' => route('agent.youtube-channel.oauth.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/youtube.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function oauthCallback(Request $request): RedirectResponse
    {
        abort_unless(YoutubeIdentityService::oauthEnabled(), 404);

        $expected = (string) $request->session()->pull('youtube_oauth_state', '');
        $returnTo = $request->session()->pull('youtube_oauth_return');
        $back = redirect()->route('agent.youtube-channel.show');

        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state')) || ! $request->filled('code')) {
            return $back->with('error', __('Google sign-in was cancelled or expired. Please try again.'));
        }

        try {
            $token = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'code' => (string) $request->query('code'),
                'client_id' => trim((string) SystemSetting::get('youtube_oauth_client_id', '')),
                'client_secret' => trim((string) SystemSetting::get('youtube_oauth_client_secret', '')),
                'redirect_uri' => route('agent.youtube-channel.oauth.callback'),
                'grant_type' => 'authorization_code',
            ]);
            $accessToken = (string) $token->json('access_token', '');
            if (! $token->successful() || $accessToken === '') {
                return $back->with('error', __('Google did not confirm the connection. Please try again.'));
            }

            $channels = Http::withToken($accessToken)->timeout(10)
                ->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet', 'mine' => 'true']);
            $item = $channels->json('items.0');
            if (! $channels->successful() || ! is_array($item) || empty($item['id'])) {
                return $back->with('error', __('That Google account has no YouTube channel. Create a channel first, or use another account.'));
            }

            $this->identities->linkFromOAuth($request->user(), [
                'id' => (string) $item['id'],
                'title' => (string) ($item['snippet']['title'] ?? ''),
                'handle' => $item['snippet']['customUrl'] ?? null,
                'thumbnail' => $item['snippet']['thumbnails']['default']['url'] ?? null,
            ], (string) json_encode([
                'refresh_token' => $token->json('refresh_token'),
                'access_token' => $accessToken,
                'expires_at' => now()->addSeconds((int) $token->json('expires_in', 3600))->toIso8601String(),
            ]));
        } catch (\InvalidArgumentException $e) {
            return $back->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $back->with('error', __('We could not connect your YouTube channel right now. Please try again.'));
        }

        return ($returnTo ? redirect()->to($returnTo) : $back)
            ->with('status', __('Your YouTube channel is connected and verified.'));
    }

    public function oauthDisconnect(Request $request): RedirectResponse
    {
        $this->identities->disconnectOAuth($request->user());

        return back()->with('status', __('Google access removed. Your channel stays linked; checks now use public data only.'));
    }

    private function done(Request $request, $identity, string $message, ?string $return): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'identity' => [
                    'status' => $identity->status,
                    'status_label' => $identity->statusLabel(),
                    'code' => $identity->isPending() ? $identity->verification_code : null,
                    'expires_at' => $identity->code_expires_at?->toIso8601String(),
                    'title' => $identity->title,
                    'handle' => $identity->handle,
                ],
            ]);
        }

        $return = $this->safeReturn($return);
        if ($identity->isVerified() && $return) {
            return redirect()->to($return)->with('status', $message);
        }

        return redirect()
            ->route('agent.youtube-channel.show', $return ? ['return' => $return] : [])
            ->with('status', $message);
    }

    private function fail(Request $request, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 422)
            : back()->withInput()->with('error', $message);
    }

    /** Only same-site agent paths are allowed as a return target. */
    private function safeReturn(mixed $return): ?string
    {
        $return = is_string($return) ? trim($return) : '';
        if ($return === '' || ! str_starts_with($return, '/agent/') || str_starts_with($return, '//')) {
            return null;
        }

        return $return;
    }
}
