<?php

namespace App\Modules\Wallet\Services;

use App\Models\PaymentTimelineEvent;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WalletFunding;
use App\Modules\Wallet\Payments\Contracts\PaymentRailInterface;
use App\Modules\Wallet\Payments\Monnify\MonnifyPaymentRail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DepositCheckoutService
{
    public function __construct(
        private PaymentRailInterface $rail,
        private WalletService $wallets,
        private MonnifyPaymentRail $monnify,
    ) {}

    public function monnifyEnabled(): bool
    {
        return $this->rail->isConfigured();
    }

    public function reservedAccountsAllowed(User $user): bool
    {
        if (! SystemSetting::kycRequired()) {
            $meta = \App\Models\IntegrationProvider::forProvider(\App\Models\IntegrationProvider::MONNIFY)->meta ?? [];

            return (bool) ($meta['reserved_accounts_without_kyc'] ?? false);
        }

        $minLevel = SystemSetting::kycRequiredLevel('reserved_account', 1);

        return (int) $user->kyc_level >= $minLevel;
    }

    public function assertDepositKyc(User $user): void
    {
        if ($user->hasRole('agent')) {
            throw new InvalidArgumentException('Agents cannot deposit funds. Earnings are credited after task verification.');
        }

        if (! SystemSetting::kycRequired()) {
            return;
        }

        $minLevel = SystemSetting::kycRequiredLevel('deposit', 1);
        if (! $user->hasApprovedKyc($minLevel)) {
            throw new InvalidArgumentException(
                'Complete KYC verification (level '.$minLevel.') before depositing.'
            );
        }
    }

    public function startCheckout(User $user, float $amount, string $redirectUrl): WalletFunding
    {
        if ($user->hasRole('agent')) {
            throw new InvalidArgumentException('Agents cannot deposit funds.');
        }

        if (! $user->wallet) {
            throw new InvalidArgumentException('Create a wallet first.');
        }

        $this->assertDepositKyc($user);

        if (! $this->monnifyEnabled()) {
            throw new InvalidArgumentException('Card/transfer checkout is not available right now.');
        }

        $depositMin = (float) SystemSetting::get('deposit_min_amount', 100);
        if ($amount < $depositMin) {
            throw new InvalidArgumentException('Amount is below the minimum deposit.');
        }

        return DB::transaction(function () use ($user, $amount, $redirectUrl) {
            $open = WalletFunding::query()
                ->where('user_id', $user->id)
                ->where('method', 'monnify_checkout')
                ->whereIn('status', ['pending', 'processing'])
                ->where('amount', $amount)
                ->where(function ($q) {
                    $q->whereNull('checkout_expires_at')
                        ->orWhere('checkout_expires_at', '>', now());
                })
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($open && filled($open->checkout_url) && ! $open->isCheckoutExpired()) {
                return $open;
            }

            $paymentReference = 'DEP-'.strtoupper((string) Str::ulid());

            $funding = WalletFunding::create([
                'user_id' => $user->id,
                'wallet_id' => $user->wallet->id,
                'method' => 'monnify_checkout',
                'amount' => $amount,
                'currency' => 'NGN',
                'status' => 'pending',
                'internal_status' => 'pending',
                'provider' => 'monnify',
                'provider_payment_reference' => $paymentReference,
                'reference' => $paymentReference,
            ]);

            PaymentTimelineEvent::record($funding, 'created', 'Created');

            $init = $this->rail->initializeCheckout([
                'amount' => $amount,
                'paymentReference' => $paymentReference,
                'customerName' => $user->name,
                'customerEmail' => $user->email,
                'redirectUrl' => $redirectUrl,
                'paymentDescription' => 'Wallet funding '.$paymentReference,
            ]);

            $funding->update([
                'checkout_url' => $init['checkoutUrl'],
                'provider_transaction_reference' => $init['transactionReference'],
                'checkout_expires_at' => now()->addMinutes(40),
                'internal_status' => 'processing',
                'status' => 'processing',
                'provider_status' => 'PENDING',
            ]);

            PaymentTimelineEvent::record($funding, 'sent_to_provider', 'Sent to Monnify');

            return $funding->fresh();
        });
    }

    public function completeFromReturn(string $paymentReference): WalletFunding
    {
        $funding = WalletFunding::query()
            ->where('provider_payment_reference', $paymentReference)
            ->firstOrFail();

        if ($funding->status === 'approved' || $funding->internal_status === 'completed') {
            return $funding;
        }

        $verified = $this->rail->verifyTransaction($paymentReference);
        $status = strtoupper((string) ($verified['paymentStatus'] ?? ''));
        $amountPaid = (string) ($verified['amountPaid'] ?? '0');

        $funding->update([
            'provider_status' => $status,
            'provider_transaction_reference' => $verified['transactionReference'] ?? $funding->provider_transaction_reference,
        ]);

        if (! in_array($status, ['PAID', 'SUCCESS', 'COMPLETED'], true)) {
            return $funding;
        }

        if (bccomp($amountPaid, (string) $funding->amount, 2) !== 0) {
            throw new InvalidArgumentException('Paid amount does not match funding amount.');
        }

        $owner = User::query()->find($funding->user_id);
        if ($owner?->hasRole('agent')) {
            $this->clearReservedDepositRails($owner);
            throw new InvalidArgumentException('Agents cannot receive deposit credits.');
        }

        PaymentTimelineEvent::record($funding, 'verified', 'Payment verified with Monnify');
        $this->wallets->creditFromFunding($funding);

        return $funding->fresh();
    }

    /**
     * @return array{accountNumber: string, bankName: string, accountReference: string}
     */
    public function ensureReservedAccount(User $user): array
    {
        if ($user->hasRole('agent')) {
            throw new InvalidArgumentException('Agents cannot deposit funds.');
        }

        if (! $this->reservedAccountsAllowed($user)) {
            throw new InvalidArgumentException('Complete KYC to get a reserved deposit account.');
        }

        if (! $this->monnifyEnabled()) {
            throw new InvalidArgumentException('Reserved accounts are not available right now.');
        }

        $wallet = $user->wallet;
        if (! $wallet) {
            throw new InvalidArgumentException('Create a wallet first.');
        }

        if (filled($wallet->reserved_account_number)) {
            return [
                'accountNumber' => (string) $wallet->reserved_account_number,
                'bankName' => (string) $wallet->reserved_bank_name,
                'accountReference' => (string) $wallet->reserved_account_reference,
            ];
        }

        $accountReference = 'RA-'.$user->id.'-'.Str::lower(Str::random(8));
        $body = $this->monnify->createReservedAccount([
            'accountReference' => $accountReference,
            'accountName' => $user->name,
            'customerEmail' => $user->email,
        ]);

        $accounts = $body['accounts'] ?? [];
        $first = $accounts[0] ?? null;
        $accountNumber = (string) ($first['accountNumber'] ?? $body['accountNumber'] ?? '');
        $bankName = (string) ($first['bankName'] ?? $body['bankName'] ?? '');

        $wallet->update([
            'reserved_account_number' => $accountNumber,
            'reserved_bank_name' => $bankName,
            'reserved_account_reference' => $accountReference,
        ]);

        return [
            'accountNumber' => $accountNumber,
            'bankName' => $bankName,
            'accountReference' => $accountReference,
        ];
    }

    /**
     * Credit from reserved-account webhook when paymentReference is not a checkout DEP- ref.
     */
    public function creditReservedPayment(array $verified): ?WalletFunding
    {
        $accountNumber = (string) data_get($verified, 'destinationAccountInformation.accountNumber')
            ?: (string) data_get($verified, 'accountDetails.accountNumber', '');
        $paymentReference = (string) ($verified['paymentReference'] ?? '');
        $amountPaid = (string) ($verified['amountPaid'] ?? '0');

        if ($paymentReference === '' || bccomp($amountPaid, '0', 2) <= 0) {
            return null;
        }

        $existing = WalletFunding::where('provider_payment_reference', $paymentReference)->first();
        if ($existing) {
            $existingOwner = User::query()->find($existing->user_id);
            if ($existingOwner?->hasRole('agent')) {
                $this->clearReservedDepositRails($existingOwner);

                return null;
            }

            if ($existing->internal_status !== 'completed') {
                $this->wallets->creditFromFunding($existing);
            }

            return $existing;
        }

        $wallet = null;
        if ($accountNumber !== '') {
            $wallet = \App\Models\Wallet::where('reserved_account_number', $accountNumber)->first();
        }

        if (! $wallet) {
            return null;
        }

        $owner = User::query()->find($wallet->user_id);
        if ($owner?->hasRole('agent')) {
            $this->clearReservedDepositRails($owner);

            return null;
        }

        $funding = WalletFunding::create([
            'user_id' => $wallet->user_id,
            'wallet_id' => $wallet->id,
            'method' => 'monnify_reserved',
            'amount' => $amountPaid,
            'currency' => 'NGN',
            'status' => 'processing',
            'internal_status' => 'processing',
            'provider' => 'monnify',
            'provider_payment_reference' => $paymentReference,
            'provider_transaction_reference' => $verified['transactionReference'] ?? null,
            'provider_status' => $verified['paymentStatus'] ?? 'PAID',
            'reference' => $paymentReference,
            'reserved_account_number' => $wallet->reserved_account_number,
            'reserved_bank_name' => $wallet->reserved_bank_name,
            'reserved_account_reference' => $wallet->reserved_account_reference,
        ]);

        PaymentTimelineEvent::record($funding, 'created', 'Reserved account payment');
        $this->wallets->creditFromFunding($funding);

        return $funding;
    }

    /**
     * Agents are withdraw-only — strip any leftover Monnify reserved deposit rails.
     */
    public function clearReservedDepositRails(User $user): void
    {
        $wallet = $user->wallet;
        if (! $wallet) {
            return;
        }

        if (
            blank($wallet->reserved_account_number)
            && blank($wallet->reserved_bank_name)
            && blank($wallet->reserved_account_reference)
        ) {
            return;
        }

        $wallet->update([
            'reserved_account_number' => null,
            'reserved_bank_name' => null,
            'reserved_account_reference' => null,
        ]);
    }
}
