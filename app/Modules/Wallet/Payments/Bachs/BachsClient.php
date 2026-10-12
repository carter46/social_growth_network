<?php

namespace App\Modules\Wallet\Payments\Bachs;

use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP wrapper for api.bachs.io. Errors use a flat envelope: { error_code, detail, errors[] }.
 */
class BachsClient
{
    public const LIVE_URL = 'https://api.bachs.io';

    public const SANDBOX_URL = 'https://sandbox-api.bachs.io';

    public function __construct(
        private string $secretKey,
        private bool $live,
        private int $timeout = 20,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = [], ?string $accountId = null): array
    {
        return $this->send('GET', $path, $query, $accountId, null);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body = [], ?string $accountId = null, ?string $idempotencyKey = null): array
    {
        return $this->send('POST', $path, $body, $accountId, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $payload, ?string $accountId, ?string $idempotencyKey): array
    {
        $request = $this->request($accountId, $idempotencyKey);

        try {
            /** @var Response $response */
            $response = $method === 'GET'
                ? $request->get($path, $payload)
                : $request->post($path, $payload);
        } catch (ConnectionException $e) {
            throw new GatewayException('Bachs could not be reached: '.$e->getMessage(), null, null, true, [], $e);
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if ($response->successful()) {
            return $body;
        }

        $status = $response->status();
        $code = isset($body['error_code']) ? (string) $body['error_code'] : null;
        $detail = (string) ($body['detail'] ?? $body['message'] ?? ('HTTP '.$status));

        throw new GatewayException(
            $detail,
            $code,
            $status,
            $status >= 500 || $status === 429 || $status === 408 || $code === 'IDEMPOTENCY_IN_PROGRESS',
            $body,
        );
    }

    private function request(?string $accountId, ?string $idempotencyKey): PendingRequest
    {
        $headers = ['Accept' => 'application/json'];
        if ($accountId) {
            $headers['X-Account-Id'] = $accountId;
        }
        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return Http::baseUrl($this->live ? self::LIVE_URL : self::SANDBOX_URL)
            ->withToken($this->secretKey)
            ->withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->connectTimeout(min(10, $this->timeout));
    }
}
