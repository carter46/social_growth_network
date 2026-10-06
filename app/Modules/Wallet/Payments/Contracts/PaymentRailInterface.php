<?php

namespace App\Modules\Wallet\Payments\Contracts;

interface PaymentRailInterface
{
    public function isConfigured(): bool;

    /** Short machine key stored on fundings/orders, e.g. "monnify". */
    public function providerKey(): string;

    /** Human name shown to users and admins, e.g. "Monnify". */
    public function displayName(): string;

    /**
     * @param  array{amount: float|string, paymentReference: string, customerName: string, customerEmail: string, redirectUrl: string, paymentDescription?: string}  $payload
     * @return array{checkoutUrl: string, transactionReference: string, paymentReference: string, amount: float|string}
     */
    public function initializeCheckout(array $payload): array;

    public function verifyTransaction(string $paymentReference): array;

    /**
     * @return array{accountName: string, accountNumber: string, bankCode: string}
     */
    public function resolveAccount(string $accountNumber, string $bankCode): array;

    /**
     * @return list<array{name: string, code: string}>
     */
    public function listBanks(): array;

    public function getMerchantWalletBalance(): float;

    /**
     * @param  array{amount: float|string, reference: string, bankCode: string, accountNumber: string, accountName: string, narration?: string, currency?: string, async?: bool}  $payload
     */
    public function initiateTransfer(array $payload): array;

    public function getTransferStatus(string $reference): array;
}
