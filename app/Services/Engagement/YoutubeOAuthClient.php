<?php

namespace App\Services\Engagement;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google sign-in for YouTube channel linking. Never logs tokens; every failure is
 * classified so "no channel" is only reported when YouTube actually says so.
 */
class YoutubeOAuthClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/youtube.readonly';

    public const FOUND = 'found';

    public const NO_CHANNEL = 'no_channel';

    public const INSUFFICIENT_SCOPE = 'insufficient_scope';

    public const TEMPORARY = 'temporary';

    public const EXPIRED = 'expired';

    public function authorizeUrl(string $redirectUri, string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'select_account consent',
            'state' => $state,
        ]);
    }

    /**
     * @return array{outcome: string, access_token?: string, refresh_token?: ?string, expires_in?: int}
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        try {
            $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $this->clientId(),
                'client_secret' => trim((string) SystemSetting::get('youtube_oauth_client_secret', '')),
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);
        } catch (\Throwable $e) {
            $this->log('token', null, null, $e::class);

            return ['outcome' => self::TEMPORARY];
        }

        $accessToken = (string) $response->json('access_token', '');
        if (! $response->successful() || $accessToken === '') {
            $error = (string) $response->json('error', '');
            $this->log('token', $response->status(), $error);

            return ['outcome' => $response->serverError() ? self::TEMPORARY : self::EXPIRED];
        }

        $granted = (string) $response->json('scope', self::SCOPE);
        if (! in_array(self::SCOPE, preg_split('/\s+/', $granted) ?: [], true)) {
            $this->log('token', $response->status(), 'scope_not_granted');

            return ['outcome' => self::INSUFFICIENT_SCOPE];
        }

        return [
            'outcome' => self::FOUND,
            'access_token' => $accessToken,
            'refresh_token' => $response->json('refresh_token'),
            'expires_in' => (int) $response->json('expires_in', 3600),
        ];
    }

    /**
     * The signed-in user's own channel.
     *
     * @return array{outcome: string, channel?: array{id: string, title: string, handle: ?string, thumbnail: ?string}}
     */
    public function ownChannel(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->timeout(10)
                ->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet', 'mine' => 'true']);
        } catch (\Throwable $e) {
            $this->log('channels', null, null, $e::class);

            return ['outcome' => self::TEMPORARY];
        }

        if ($response->successful()) {
            $item = $response->json('items.0');
            if (! is_array($item) || empty($item['id'])) {
                return ['outcome' => self::NO_CHANNEL];
            }

            return ['outcome' => self::FOUND, 'channel' => [
                'id' => (string) $item['id'],
                'title' => (string) ($item['snippet']['title'] ?? ''),
                'handle' => $item['snippet']['customUrl'] ?? null,
                'thumbnail' => $item['snippet']['thumbnails']['default']['url'] ?? null,
            ]];
        }

        $reason = (string) $response->json('error.errors.0.reason', '');
        $this->log('channels', $response->status(), $reason);

        // A 403 that is not a scope problem (API disabled, quota, project config) is ours to fix,
        // so the user is not told to grant a permission they already granted.
        return ['outcome' => match (true) {
            $reason === 'youtubeSignupRequired' => self::NO_CHANNEL,
            $reason === 'insufficientPermissions' => self::INSUFFICIENT_SCOPE,
            $response->status() === 401 => self::EXPIRED,
            default => self::TEMPORARY,
        }];
    }

    /** Best effort: a failed revoke is only logged. */
    public function revoke(?string $storedToken): void
    {
        $data = json_decode((string) $storedToken, true);
        $token = is_array($data) ? ($data['refresh_token'] ?? $data['access_token'] ?? null) : null;
        if (! is_string($token) || $token === '') {
            return;
        }

        try {
            $response = Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
            if (! $response->successful()) {
                $this->log('revoke', $response->status(), (string) $response->json('error', ''));
            }
        } catch (\Throwable $e) {
            $this->log('revoke', null, null, $e::class);
        }
    }

    private function clientId(): string
    {
        return trim((string) SystemSetting::get('youtube_oauth_client_id', ''));
    }

    private function log(string $step, ?int $status, ?string $reason, ?string $exception = null): void
    {
        Log::warning('youtube_oauth.'.$step.'_failed', array_filter([
            'status' => $status,
            'reason' => $reason,
            'exception' => $exception,
        ], fn ($v) => $v !== null && $v !== ''));
    }
}
