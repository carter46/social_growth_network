<?php

namespace App\Modules\Wallet\Payments\Data;

use App\Models\PayoutAccount;
use App\Models\Withdrawal;

/**
 * A payout destination, as stored on a payout account or snapshotted on a withdrawal.
 */
final class PayoutAccountDetails
{
    /**
     * @param  array<string, string>  $fields  Method fields from config/payout_methods.php (account_number, routing_number, iban, …)
     * @param  array<string, mixed>  $beneficiaryIds  Provider-side ids keyed by gateway
     */
    public function __construct(
        public readonly string $country,
        public readonly string $currency,
        public readonly string $method,
        public readonly string $holderName,
        public readonly array $fields = [],
        public readonly ?string $bankCode = null,
        public readonly ?string $bankName = null,
        public readonly array $beneficiaryIds = [],
        public readonly ?int $payoutAccountId = null,
        public readonly ?string $contactEmail = null,
        public readonly ?int $userId = null,
        public readonly bool $isPrimary = false,
    ) {}

    public static function fromPayoutAccount(PayoutAccount $account): self
    {
        return new self(
            country: (string) $account->country,
            currency: (string) $account->currency,
            method: (string) $account->method,
            holderName: (string) $account->account_holder_name,
            fields: array_map('strval', (array) ($account->details ?? [])),
            bankCode: $account->bank_code,
            bankName: $account->bank_name,
            beneficiaryIds: (array) ($account->provider_beneficiary_ids ?? []),
            payoutAccountId: $account->id,
            contactEmail: $account->user?->email,
            userId: $account->user_id !== null ? (int) $account->user_id : null,
            isPrimary: (bool) $account->is_primary,
        );
    }

    public static function fromWithdrawal(Withdrawal $withdrawal): self
    {
        $snapshot = (array) ($withdrawal->destination_details ?? []);
        $account = $withdrawal->payoutAccount;

        if ($snapshot === [] && filled($withdrawal->account_number)) {
            $snapshot = [
                'fields' => ['account_number' => (string) $withdrawal->account_number],
                'holder_name' => (string) $withdrawal->account_name,
                'bank_code' => $withdrawal->bank_code,
                'bank_name' => $withdrawal->bank_name,
            ];
        }

        return new self(
            country: (string) ($withdrawal->destination_country ?: 'NG'),
            currency: (string) ($withdrawal->destination_currency ?: 'NGN'),
            method: (string) ($withdrawal->method ?: 'bank_account'),
            holderName: (string) ($snapshot['holder_name'] ?? $withdrawal->account_name ?? ''),
            fields: array_map('strval', (array) ($snapshot['fields'] ?? [])),
            bankCode: $snapshot['bank_code'] ?? $withdrawal->bank_code,
            bankName: $snapshot['bank_name'] ?? $withdrawal->bank_name,
            beneficiaryIds: (array) ($account?->provider_beneficiary_ids ?? []),
            payoutAccountId: $withdrawal->payout_account_id,
            contactEmail: $withdrawal->user?->email,
            userId: $withdrawal->user_id !== null ? (int) $withdrawal->user_id : null,
            isPrimary: (bool) ($account?->is_primary ?? false),
        );
    }

    public function field(string $name, ?string $default = null): ?string
    {
        $value = $this->fields[$name] ?? null;

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public function accountNumber(): ?string
    {
        return $this->field('account_number') ?? $this->field('iban') ?? $this->field('phone_number');
    }

    /**
     * Snapshot stored on the withdrawal (encrypted column).
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'country' => $this->country,
            'currency' => $this->currency,
            'method' => $this->method,
            'holder_name' => $this->holderName,
            'fields' => $this->fields,
            'bank_code' => $this->bankCode,
            'bank_name' => $this->bankName,
        ];
    }

    /**
     * Masked form for logs and attempt snapshots.
     *
     * @return array<string, mixed>
     */
    public function masked(): array
    {
        $fields = [];
        foreach ($this->fields as $name => $value) {
            $value = (string) $value;
            $fields[$name] = strlen($value) > 4 ? str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4) : $value;
        }

        return [
            'country' => $this->country,
            'currency' => $this->currency,
            'method' => $this->method,
            'bank_code' => $this->bankCode,
            'fields' => $fields,
        ];
    }

    public function withBeneficiaryIds(array $ids): self
    {
        return new self(
            $this->country, $this->currency, $this->method, $this->holderName, $this->fields,
            $this->bankCode, $this->bankName, $ids, $this->payoutAccountId, $this->contactEmail,
            $this->userId, $this->isPrimary,
        );
    }
}
