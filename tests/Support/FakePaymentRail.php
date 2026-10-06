<?php

namespace Tests\Support;

use App\Modules\Wallet\Payments\Contracts\PaymentRailInterface;
use App\Modules\Wallet\Payments\Contracts\SupportsTransferAuthorization;

class FakePaymentRail implements PaymentRailInterface, SupportsTransferAuthorization
{
    /** @var array<string, mixed> */
    public static array $initiateResult = ['status' => 'SUCCESS'];

    /** @var array<string, mixed> */
    public static array $transferStatus = ['status' => 'SUCCESS'];

    /** @var array<string, mixed> */
    public static array $authorizeResult = ['status' => 'SUCCESS'];

    public static bool $configured = true;

    public static ?string $lastAuthorizeReference = null;

    public static ?string $lastAuthorizeCode = null;

    /** @var array<string, mixed> */
    public static array $verifyResult = ['paymentStatus' => 'PAID'];

    public function isConfigured(): bool
    {
        return self::$configured;
    }

    public function providerKey(): string
    {
        return 'monnify';
    }

    public function displayName(): string
    {
        return 'Test gateway';
    }

    public function initializeCheckout(array $payload): array
    {
        return [
            'checkoutUrl' => 'https://example.test/checkout',
            'transactionReference' => 'TXN-TEST',
            'paymentReference' => $payload['paymentReference'],
            'amount' => $payload['amount'],
        ];
    }

    public function verifyTransaction(string $paymentReference): array
    {
        return self::$verifyResult;
    }

    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        return [
            'accountName' => 'Test User',
            'accountNumber' => $accountNumber,
            'bankCode' => $bankCode,
        ];
    }

    public function listBanks(): array
    {
        return [['name' => 'GTBank', 'code' => '058']];
    }

    public function getMerchantWalletBalance(): float
    {
        return 1_000_000.0;
    }

    public function initiateTransfer(array $payload): array
    {
        return array_merge([
            'reference' => $payload['reference'],
            'amount' => $payload['amount'],
        ], self::$initiateResult);
    }

    public function getTransferStatus(string $reference): array
    {
        return array_merge(['reference' => $reference], self::$transferStatus);
    }

    public function requiresTransferAuthorization(array $initiateResult): bool
    {
        return strtoupper((string) ($initiateResult['status'] ?? '')) === 'PENDING_AUTHORIZATION';
    }

    public function authorizeTransfer(string $reference, string $authorizationCode): array
    {
        self::$lastAuthorizeReference = $reference;
        self::$lastAuthorizeCode = $authorizationCode;

        if ($authorizationCode === '000000') {
            throw new \App\Modules\Wallet\Payments\Monnify\MonnifyApiException('Invalid OTP', '99');
        }

        return array_merge(['reference' => $reference], self::$authorizeResult);
    }

    public static function reset(): void
    {
        self::$initiateResult = ['status' => 'SUCCESS'];
        self::$transferStatus = ['status' => 'SUCCESS'];
        self::$authorizeResult = ['status' => 'SUCCESS'];
        self::$configured = true;
        self::$verifyResult = ['paymentStatus' => 'PAID'];
        self::$lastAuthorizeReference = null;
        self::$lastAuthorizeCode = null;
    }
}
