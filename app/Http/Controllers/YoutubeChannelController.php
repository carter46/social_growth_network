<?php

namespace App\Http\Controllers;

use App\Models\AgentYoutubeIdentity;
use App\Models\User;
use App\Services\Campaigns\YoutubeIdentityService;
use App\Services\Engagement\YoutubeChannelApi;
use App\Services\Engagement\YoutubeOAuthClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * YouTube channel connection for Agents (required for YouTube tasks) and Creators (optional).
 * Both roles share the same identity records and rules. Agents cannot disconnect while a task is open.
 */
class YoutubeChannelController extends Controller
{
    public const CREATE_CHANNEL_URL = 'https://www.youtube.com/create_channel';

    public function __construct(
        private YoutubeIdentityService $identities,
        private YoutubeOAuthClient $oauth,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $prefix = $this->prefix($user);
        $missing = $request->session()->get('youtube_channel_missing');

        $view = $request->headers->get('X-Dashboard-Tab') === '1' ? 'youtube-channel.panel' : 'youtube-channel.show';

        return view($view, [
            'layout' => $prefix === 'agent' ? 'layouts.dashboard-agent' : 'layouts.dashboard-user',
            'prefix' => $prefix,
            'isAgent' => $prefix === 'agent',
            'identity' => $this->identities->current($user),
            'changeRequest' => $this->identities->pendingChangeRequest($user),
            'oauthEnabled' => YoutubeIdentityService::oauthEnabled(),
            'returnTo' => $this->safeReturn($user, $request->query('return')),
            'channelMissing' => in_array($missing, ['oauth', 'link'], true) ? $missing : false,
            'justConnected' => (bool) $request->session()->get('youtube_channel_connected', false),
            'openTasks' => $prefix === 'agent' ? $this->identities->openTaskCount($user->id) : 0,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['channel_url' => ['required', 'string', 'max:500']]);

        try {
            return response()->json(['channel' => $this->identities->preview($data['channel_url'])]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'missing_channel_help' => $e->getCode() === YoutubeIdentityService::ERROR_NOT_FOUND,
            ], 422);
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
            return $this->fail($request, $e->getMessage(), $e->getCode() === YoutubeIdentityService::ERROR_NOT_FOUND);
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
            ? ($request->user()->isAgent()
                ? __('Your YouTube channel is verified. You can start YouTube tasks now.')
                : __('Your YouTube channel is connected.'))
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
        abort_unless($request->user()->isAgent(), 404);
        $data = $request->validate(['channel_url' => ['required', 'string', 'max:500']]);

        try {
            $this->identities->requestChange($request->user(), $data['channel_url']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Change request sent. An admin will review it. Your current channel stays linked until then.'));
    }

    public function unlink(Request $request): RedirectResponse
    {
        try {
            $this->identities->unlink($request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route($this->prefix($request->user()).'.youtube-channel.show')
            ->with('status', __('Your YouTube channel is disconnected. You can connect the right channel now.'));
    }

    /** Recent public uploads of the creator's own verified channel, for the checkout picker. */
    public function videos(Request $request, YoutubeChannelApi $api): JsonResponse
    {
        $identity = $this->identities->verified($request->user());
        if (! $identity || ! $identity->channel_id) {
            return response()->json(['videos' => [], 'connected' => false]);
        }

        $videos = $api->recentUploads($identity->channel_id, 10);

        return response()->json([
            'connected' => true,
            'available' => $videos !== null,
            'videos' => $videos ?? [],
        ]);
    }

    public function oauthRedirect(Request $request): RedirectResponse
    {
        abort_unless(YoutubeIdentityService::oauthEnabled(), 404);

        $state = Str::random(40);
        $redirectUri = route('youtube.oauth.callback');
        $request->session()->put('youtube_oauth_state', $state);
        $request->session()->put('youtube_oauth_redirect_uri', $redirectUri);
        $request->session()->put('youtube_oauth_return', $this->safeReturn($request->user(), $request->query('return')));

        return redirect()->away($this->oauth->authorizeUrl($redirectUri, $state));
    }

    public function oauthCallback(Request $request): RedirectResponse
    {
        abort_unless(YoutubeIdentityService::oauthEnabled(), 404);

        $user = $request->user();
        $expected = (string) $request->session()->pull('youtube_oauth_state', '');
        $redirectUri = (string) ($request->session()->pull('youtube_oauth_redirect_uri') ?: url()->current());
        $returnTo = $request->session()->pull('youtube_oauth_return');
        $back = redirect()->route($this->prefix($user).'.youtube-channel.show', $returnTo ? ['return' => $returnTo] : []);

        if ($request->filled('error')) {
            if ($request->query('error') === 'access_denied') {
                return $back->with('error', __('Google sign-in was cancelled. To connect your channel, try again and allow access to view your YouTube account.'));
            }

            Log::warning('youtube_oauth.authorize_failed', ['reason' => Str::limit((string) $request->query('error'), 60, '')]);

            return $back->with('error', __('Google sign-in could not be completed. Please try again, or link your channel with its link instead.'));
        }

        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state')) || ! $request->filled('code')) {
            return $back->with('error', __('Google sign-in was cancelled or expired. Please try again.'));
        }

        $token = $this->oauth->exchangeCode((string) $request->query('code'), $redirectUri);
        if ($token['outcome'] !== YoutubeOAuthClient::FOUND) {
            return $back->with('error', $this->oauthMessage($token['outcome']));
        }

        $lookup = $this->oauth->ownChannel($token['access_token']);
        if ($lookup['outcome'] === YoutubeOAuthClient::NO_CHANNEL) {
            return $back->with('youtube_channel_missing', 'oauth');
        }
        if ($lookup['outcome'] !== YoutubeOAuthClient::FOUND) {
            return $back->with('error', $this->oauthMessage($lookup['outcome']));
        }

        try {
            $this->identities->linkFromOAuth($user, $lookup['channel'], (string) json_encode([
                'refresh_token' => $token['refresh_token'] ?? null,
                'access_token' => $token['access_token'],
                'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600))->toIso8601String(),
            ]));
        } catch (\InvalidArgumentException $e) {
            return $back->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $back->with('error', __('We could not connect your YouTube channel right now. Please try again.'));
        }

        return $back->with('youtube_channel_connected', true);
    }

    public function oauthDisconnect(Request $request): RedirectResponse
    {
        $this->identities->disconnectOAuth($request->user());

        return back()->with('status', $request->user()->isAgent()
            ? __('Google access removed. Your channel is still connected, and your tasks are now checked using public YouTube data.')
            : __('Google access removed. Your channel is still connected. To remove the channel too, choose Disconnect channel.'));
    }

    private function oauthMessage(string $outcome): string
    {
        return match ($outcome) {
            YoutubeOAuthClient::INSUFFICIENT_SCOPE => __('Google did not give permission to view your YouTube account. Please try again and allow that permission.'),
            YoutubeOAuthClient::EXPIRED => __('Google sign-in expired. Please try again.'),
            default => __('We couldn\'t reach YouTube right now. Please try again in a few minutes, or link your channel with its link instead.'),
        };
    }

    private function done(Request $request, AgentYoutubeIdentity $identity, string $message, ?string $return): RedirectResponse|JsonResponse
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

        $return = $this->safeReturn($request->user(), $return);
        $redirect = redirect()->route($this->prefix($request->user()).'.youtube-channel.show', $return ? ['return' => $return] : []);

        return $identity->isVerified()
            ? $redirect->with('youtube_channel_connected', true)
            : $redirect->with('status', $message);
    }

    private function fail(Request $request, string $message, bool $missingChannelHelp = false): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'missing_channel_help' => $missingChannelHelp], 422);
        }

        $redirect = back()->withInput()->with('error', $message);

        return $missingChannelHelp ? $redirect->with('youtube_channel_missing', 'link') : $redirect;
    }

    private function prefix(User $user): string
    {
        return $user->isAgent() ? 'agent' : 'dashboard';
    }

    /** Only same-site paths inside the user's own dashboard are allowed as a return target. */
    private function safeReturn(User $user, mixed $return): ?string
    {
        $return = is_string($return) ? trim($return) : '';
        $base = '/'.$this->prefix($user);
        if ($return === '' || str_starts_with($return, '//') || ($return !== $base && ! str_starts_with($return, $base.'/'))) {
            return null;
        }

        return $return;
    }
}
