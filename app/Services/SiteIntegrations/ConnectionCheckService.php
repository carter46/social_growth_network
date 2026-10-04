<?php

namespace App\Services\SiteIntegrations;

use App\Models\SiteIntegration;
use App\Models\SiteIntegrationCheckLog;
use App\Models\User;
use App\Models\UserTool;
use App\Models\UserToolIntegration;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ConnectionCheckService
{
    private const BODY_EXCERPT_LIMIT = 400;

    public function __construct(
        private ProtocolV1Signer $signer,
        private IntegrationHttpClient $http,
        private SubscriptionSyncService $subscriptionSync,
        private NotificationDispatcher $notificationDispatcher,
    ) {}

    /**
     * @return array{ok: bool, http_status: int|null, message: string, payload: array<string, mixed>|null}
     */
    public function checkDemo(SiteIntegration $integration): array
    {
        $result = $this->ping(
            $integration->healthUrl(),
            $integration->integration_id,
            $integration->client_id,
            $integration->client_secret,
            'demo',
        );

        $integration->connection_status = $result['ok'] ? 'ok' : 'error';
        $integration->last_checked_at = now();
        $integration->last_error = $result['ok'] ? null : $result['message'];
        $integration->save();

        SiteIntegrationCheckLog::create([
            'owner_type' => 'demo',
            'owner_id' => $integration->id,
            'direction' => 'hub_to_site',
            'ok' => $result['ok'],
            'http_status' => $result['http_status'],
            'message' => $result['message'],
            'payload_summary' => $result['payload'],
        ]);

        return $result;
    }

    /**
     * @return array{ok: bool, http_status: int|null, message: string, payload: array<string, mixed>|null}
     */
    public function checkOwned(UserTool $tool): array
    {
        $integration = $tool->integration;
        if (! $integration instanceof UserToolIntegration || ! $tool->site_url) {
            return [
                'ok' => false,
                'http_status' => null,
                'message' => 'Missing site URL or provisioning integration.',
                'payload' => null,
            ];
        }

        $previousStatus = $integration->connection_status;

        $result = $this->ping(
            $tool->healthUrl(),
            $integration->integration_id,
            $integration->client_id,
            $integration->client_secret,
            'owned_tool',
        );

        $connectionStatus = $result['ok'] ? 'ok' : $this->resolveOwnedConnectionStatus($result);

        $integration->connection_status = $connectionStatus;
        $integration->last_checked_at = now();
        $integration->last_error = $result['ok'] ? null : $result['message'];
        $integration->save();

        if ($result['ok']) {
            $this->subscriptionSync->push($tool->fresh(['integration']));

            if ($previousStatus !== 'ok' && $tool->configured_at !== null) {
                $this->notifyUserToolReady($tool->fresh(['product']));
            }
        }

        SiteIntegrationCheckLog::create([
            'owner_type' => 'owned',
            'owner_id' => $integration->id,
            'direction' => 'hub_to_site',
            'ok' => $result['ok'],
            'http_status' => $result['http_status'],
            'message' => $result['message'],
            'payload_summary' => $result['payload'],
        ]);

        return $result;
    }

    private function notifyUserToolReady(UserTool $tool): void
    {
        $user = User::query()->find($tool->user_id);
        if (! $user) {
            return;
        }

        $productName = $tool->product?->title ?? $tool->resolvedDisplayName();
        $toolUrl = Route::has('dashboard.my-tools.show')
            ? route('dashboard.my-tools.show', $tool)
            : null;

        $this->notificationDispatcher->notifyUser(
            $user,
            new NotificationMessage(
                type: 'tool.setup_complete',
                title: __('Your website is ready'),
                body: __(':product has been set up and connected successfully. You can now access your website, admin login, and other details from My Tools.', [
                    'product' => $productName,
                ]),
                actionUrl: $toolUrl,
                meta: ['user_tool_id' => $tool->id],
                emailSubject: __(':product: your website is ready', ['product' => $productName]),
                dedupeKey: 'tool.setup_complete.'.$tool->id,
            ),
            ['database', 'mail']
        );
    }

    /**
     * @return array{ok: bool, http_status: int|null, message: string, payload: array<string, mixed>|null}
     */
    private function ping(string $url, string $integrationId, string $clientId, string $clientSecret, string $context): array
    {
        // Sign only — never include client_secret in diagnostics/logs.
        $payload = $this->signer->sign([
            'integration_id' => $integrationId,
            'context' => $context,
            'role' => 'health',
            'identity' => ['email' => 'health@7th-tradehub.local'],
            'request_id' => (string) Str::uuid(),
            'nonce' => Str::random(24),
            'issued_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(2)->toIso8601String(),
        ], $clientSecret);

        $startedAt = microtime(true);

        try {
            $response = $this->http->postJson(
                $url,
                [
                    'X-7TH-Client-Id' => $clientId,
                    'X-7TH-Integration-Id' => $integrationId,
                ],
                $payload,
                15
            );

            return $this->interpretHealthResponse($url, $response, $startedAt);
        } catch (InvalidArgumentException $e) {
            return [
                'ok' => false,
                'http_status' => null,
                'message' => 'Connection blocked: '.$e->getMessage().'; URL: '.$url,
                'payload' => [
                    'request_url' => $url,
                    'network_error' => $e->getMessage(),
                ],
            ];
        } catch (ConnectionException $e) {
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

            return [
                'ok' => false,
                'http_status' => null,
                'message' => $this->formatFailureParts([
                    'Network/cURL error: '.$this->safeExceptionMessage($e),
                    'URL: '.$url,
                    'Elapsed: '.$elapsedMs.'ms',
                ]),
                'payload' => [
                    'request_url' => $url,
                    'elapsed_ms' => $elapsedMs,
                    'network_error' => $this->safeExceptionMessage($e),
                ],
            ];
        } catch (Throwable $e) {
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

            return [
                'ok' => false,
                'http_status' => null,
                'message' => $this->formatFailureParts([
                    'Connection error: '.$this->safeExceptionMessage($e),
                    'URL: '.$url,
                    'Elapsed: '.$elapsedMs.'ms',
                ]),
                'payload' => [
                    'request_url' => $url,
                    'elapsed_ms' => $elapsedMs,
                    'network_error' => $this->safeExceptionMessage($e),
                ],
            ];
        }
    }

    /**
     * @return array{ok: bool, http_status: int|null, message: string, payload: array<string, mixed>|null}
     */
    private function interpretHealthResponse(string $requestUrl, Response $response, float $startedAt): array
    {
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
        $status = $response->status();
        $rawBody = (string) $response->body();
        $contentType = (string) ($response->header('Content-Type') ?: '');
        $location = (string) ($response->header('Location') ?: '');
        $redirected = $this->isRedirectStatus($status);
        $json = $response->json();
        $jsonDecodable = is_array($json);
        $okFlag = $jsonDecodable ? ($json['ok'] ?? null) : null;
        $passed = $response->successful() && $okFlag === true;

        $payload = [
            'request_url' => $requestUrl,
            'http_status' => $status,
            'content_type' => $contentType !== '' ? $contentType : null,
            'redirected' => $redirected,
            'redirect_location' => $location !== '' ? $location : null,
            'elapsed_ms' => $elapsedMs,
            'json_decodable' => $jsonDecodable,
            'ok' => $jsonDecodable ? $okFlag : null,
            'capabilities' => $jsonDecodable ? ($json['capabilities'] ?? null) : null,
            'error' => $jsonDecodable ? ($json['error'] ?? null) : null,
            'message' => $jsonDecodable ? ($json['message'] ?? null) : null,
            'body_excerpt' => $this->excerptBody($rawBody),
        ];

        if ($passed) {
            return [
                'ok' => true,
                'http_status' => $status,
                'message' => 'Connection successful. HTTP '.$status.'; '.$elapsedMs.'ms; URL: '.$requestUrl,
                'payload' => $payload,
            ];
        }

        $parts = ['HTTP '.$status];

        if ($contentType !== '') {
            $parts[] = 'Content-Type: '.$contentType;
        }

        $parts[] = 'URL: '.$requestUrl;
        $parts[] = 'Elapsed: '.$elapsedMs.'ms';

        if ($redirected) {
            $parts[] = 'Redirected: yes'.($location !== '' ? ' → '.$location : '');
        }

        if (! $jsonDecodable) {
            $parts[] = $rawBody === ''
                ? 'invalid/empty JSON response'
                : 'invalid JSON response';
        } else {
            $parts[] = 'JSON ok='.$this->stringifyMixed($okFlag);
            if (array_key_exists('error', $json) && $json['error'] !== null && $json['error'] !== '') {
                $parts[] = 'error='.$this->stringifyMixed($json['error']);
            }
            if (array_key_exists('message', $json) && $json['message'] !== null && $json['message'] !== '') {
                $parts[] = 'message='.$this->stringifyMixed($json['message']);
            }
        }

        $excerpt = $this->excerptBody($rawBody);
        if ($excerpt !== '') {
            $parts[] = 'response: '.$excerpt;
        } elseif ($rawBody === '') {
            $parts[] = 'response: (empty body)';
        }

        return [
            'ok' => false,
            'http_status' => $status,
            'message' => 'Health check failed: '.implode('; ', $parts),
            'payload' => $payload,
        ];
    }

    /**
     * @param  array{ok: bool, http_status: int|null, message: string, payload: array<string, mixed>|null}  $result
     */
    private function resolveOwnedConnectionStatus(array $result): string
    {
        $error = $result['payload']['error'] ?? null;

        if ($error === 'unknown_integration') {
            return 'pending_merchant';
        }

        return 'error';
    }

    private function isRedirectStatus(int $status): bool
    {
        return in_array($status, [301, 302, 303, 307, 308], true);
    }

    private function excerptBody(string $body): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($body)) ?? trim($body);
        if ($normalized === '') {
            return '';
        }

        // Never leak secrets if a merchant echoes them back.
        $normalized = preg_replace(
            '/(?i)(client_secret|webhook_secret|authorization|api[_-]?key)\s*[:=]\s*\S+/',
            '$1=[redacted]',
            $normalized
        ) ?? $normalized;

        if (strlen($normalized) > self::BODY_EXCERPT_LIMIT) {
            return substr($normalized, 0, self::BODY_EXCERPT_LIMIT).'…';
        }

        return $normalized;
    }

    private function stringifyMixed(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : 'complex';
    }

    /**
     * @param  list<string>  $parts
     */
    private function formatFailureParts(array $parts): string
    {
        return 'Health check failed: '.implode('; ', array_values(array_filter($parts, fn (string $p): bool => $p !== '')));
    }

    private function safeExceptionMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return $e::class;
        }

        return $this->excerptBody($message) ?: $e::class;
    }
}
