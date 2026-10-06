<?php

namespace App\Modules\Wallet\Payments\Monnify;

use App\Modules\Wallet\Payments\Contracts\PaymentRailInterface;
use App\Modules\Wallet\Payments\Contracts\SupportsTransferAuthorization;
use RuntimeException;

class MonnifyPaymentRail implements PaymentRailInterface, SupportsTransferAuthorization
{
    public function __construct(private MonnifyClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function providerKey(): string
    {
        return 'monnify';
    }

    public function displayName(): string
    {
        return 'Monnify';
    }

    public function initializeCheckout(array $payload): array
    {
        $body = [
            'amount' => (float) $payload['amount'],
            'customerName' => $payload['customerName'],
            'customerEmail' => $payload['customerEmail'],
            'paymentReference' => $payload['paymentReference'],
            'paymentDescription' => $payload['paymentDescription'] ?? 'Wallet funding',
            'currencyCode' => $payload['currencyCode'] ?? 'NGN',
            'contractCode' => $this->client->contractCode(),
            'redirectUrl' => $payload['redirectUrl'],
        ];

        $json = $this->client->post('/api/v1/merchant/transactions/init-transaction', $body);
        $responseBody = $json['responseBody'] ?? [];

        $checkoutUrl = (string) ($responseBody['checkoutUrl'] ?? '');
        $txnRef = (string) ($responseBody['transactionReference'] ?? '');
        $amount = $responseBody['amount'] ?? $payload['amount'];

        if ($checkoutUrl === '' || $txnRef === '') {
            throw new RuntimeException('Monnify initialize transaction missing checkoutUrl/transactionReference.');
        }

        if (bccomp((string) $amount, (string) $payload['amount'], 2) !== 0) {
            throw new RuntimeException('Monnify returned a different amount than requested.');
        }

        return [
            'checkoutUrl' => $checkoutUrl,
            'transactionReference' => $txnRef,
            'paymentReference' => (string) ($responseBody['paymentReference'] ?? $payload['paymentReference']),
            'amount' => $amount,
        ];
    }

    public function verifyTransaction(string $paymentReference): array
    {
        $json = $this->client->get('/api/v2/merchant/transactions/query', [
            'paymentReference' => $paymentReference,
        ]);

        return $json['responseBody'] ?? [];
    }

    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        $json = $this->client->get('/api/v2/disbursements/account/validate', [
            'accountNumber' => $accountNumber,
            'bankCode' => $bankCode,
        ]);

        $body = $json['responseBody'] ?? [];
        $name = (string) ($body['accountName'] ?? '');
        if ($name === '') {
            throw new RuntimeException('Account could not be resolved.');
        }

        return [
            'accountName' => $name,
            'accountNumber' => (string) ($body['accountNumber'] ?? $accountNumber),
            'bankCode' => (string) ($body['bankCode'] ?? $bankCode),
        ];
    }

    public function listBanks(): array
    {
        $json = $this->client->get('/api/v1/banks');
        $banks = $json['responseBody'] ?? [];

        return collect($banks)
            ->map(fn ($b) => [
                'name' => (string) ($b['name'] ?? ''),
                'code' => (string) ($b['code'] ?? ''),
            ])
            ->filter(fn ($b) => $b['name'] !== '' && $b['code'] !== '')
            ->values()
            ->all();
    }

    public function getMerchantWalletBalance(): float
    {
        $account = $this->client->walletAccountNumber();
        if ($account === '') {
            throw new RuntimeException('Monnify wallet account number is not configured.');
        }

        $json = $this->client->get('/api/v2/disbursements/wallet-balance', [
            'accountNumber' => $account,
        ]);

        return (float) ($json['responseBody']['availableBalance'] ?? 0);
    }

    public function initiateTransfer(array $payload): array
    {
        $body = [
            'amount' => (float) $payload['amount'],
            'reference' => $payload['reference'],
            'narration' => $payload['narration'] ?? 'Withdrawal payout',
            'destinationBankCode' => $payload['bankCode'],
            'destinationAccountNumber' => $payload['accountNumber'],
            'currency' => $payload['currency'] ?? 'NGN',
            'sourceAccountNumber' => $this->client->walletAccountNumber(),
            'destinationAccountName' => $payload['accountName'],
            'async' => (bool) ($payload['async'] ?? true),
        ];

        $json = $this->client->post('/api/v2/disbursements/single', $body);

        return $json['responseBody'] ?? $json;
    }

    public function getTransferStatus(string $reference): array
    {
        $json = $this->client->get('/api/v2/disbursements/single/summary', [
            'reference' => $reference,
        ]);

        return $json['responseBody'] ?? [];
    }

    public function requiresTransferAuthorization(array $initiateResult): bool
    {
        return MonnifyDisbursementMapper::requiresAuthorization($initiateResult);
    }

    public function authorizeTransfer(string $reference, string $authorizationCode): array
    {
        $json = $this->client->post('/api/v2/disbursements/single/validate-otp', [
            'reference' => $reference,
            'authorizationCode' => $authorizationCode,
        ]);

        return $json['responseBody'] ?? [];
    }

    /**
     * @param  array{accountReference: string, accountName: string, customerEmail: string, bvn?: string, nin?: string, getAllAvailableBanks?: bool}  $payload
     */
    public function createReservedAccount(array $payload): array
    {
        $body = [
            'accountReference' => $payload['accountReference'],
            'accountName' => $payload['accountName'],
            'currencyCode' => 'NGN',
            'contractCode' => $this->client->contractCode(),
            'customerEmail' => $payload['customerEmail'],
            'customerName' => $payload['accountName'],
            'getAllAvailableBanks' => $payload['getAllAvailableBanks'] ?? true,
        ];

        if (! empty($payload['bvn'])) {
            $body['bvn'] = $payload['bvn'];
        }
        if (! empty($payload['nin'])) {
            $body['nin'] = $payload['nin'];
        }

        $json = $this->client->post('/api/v2/bank-transfer/reserved-accounts', $body);

        return $json['responseBody'] ?? [];
    }
}
