<?php

namespace App\Modules\Wallet\Payments\Flutterwave;

use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Flutterwave v3. Responses use { status: "success"|"error", message, data }.
 * Test and live share the base URL; the secret key decides the mode.
 */
class FlutterwaveClient
{
    public const BASE_URL = 'https://api.flutterwave.com/v3';

    public function __construct(
        private string $secretKey,
        private int $timeout = 20,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>  the full envelope
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>  the full envelope
     */
    public function post(string $path, array $body = []): array
    {
        return $this->send('POST', $path, $body);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $payload): array
    {
        $request = Http::baseUrl(self::BASE_URL)
            ->withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->connectTimeout(min(10, $this->timeout));

        try {
            $response = $method === 'GET' ? $request->get(ltrim($path, '/'), $payload) : $request->post(ltrim($path, '/'), $payload);
        } catch (ConnectionException $e) {
            throw new GatewayException('Flutterwave could not be reached: '.$e->getMessage(), null, null, true, [], $e);
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if ($response->successful() && ($body['status'] ?? 'success') === 'success') {
            return $body;
        }

        $status = $response->status();
        $message = (string) ($body['message'] ?? ('HTTP '.$status));

        throw new GatewayException(
            $message,
            FlutterwaveDriver::codeForMessage($message, $status),
            $status,
            $status >= 500 || $status === 429 || $status === 408,
            $body,
        );
    }
}
