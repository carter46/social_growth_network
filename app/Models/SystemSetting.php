<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Truthy flags stored as "1"/"0" (or legacy true/false/on/off).
     */
    public static function enabled(string $key, bool $default = false): bool
    {
        $value = static::get($key, $default ? '1' : '0');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function kycRequired(): bool
    {
        return static::enabled('kyc_required', true);
    }

    /**
     * Minimum KYC level for a feature when platform KYC is enabled.
     * Keys: kyc_required_level_{feature} (deposit, withdrawal, reserved_account, marketplace_sell).
     */
    public static function kycRequiredLevel(string $feature, int $default = 1): int
    {
        return max(1, (int) static::get('kyc_required_level_'.$feature, $default));
    }

    public static function manualBankTransferEnabled(): bool
    {
        return static::enabled('manual_bank_transfer_enabled', false);
    }

    public static function manualBankTransferConfigured(): bool
    {
        $details = static::manualBankTransferDetails();

        return filled($details['bank_name'])
            && filled($details['account_number'])
            && filled($details['account_name']);
    }

    /**
     * @return array{enabled: bool, agent_percent: float, creator_percent: float}
     */
    public static function referralSettings(): array
    {
        return [
            'enabled' => static::enabled('referral_enabled', false),
            'agent_percent' => max(0.0, min(100.0, (float) static::get('referral_agent_percent', '0'))),
            'creator_percent' => max(0.0, min(100.0, (float) static::get('referral_creator_percent', '0'))),
        ];
    }

    /**
     * @return array{bank_name: string, account_number: string, account_name: string, instructions: string}
     */
    public static function manualBankTransferDetails(): array
    {
        return [
            'bank_name' => (string) static::get('manual_bank_transfer_bank_name', ''),
            'account_number' => (string) static::get('manual_bank_transfer_account_number', ''),
            'account_name' => (string) static::get('manual_bank_transfer_account_name', ''),
            'instructions' => (string) static::get('manual_bank_transfer_instructions', ''),
        ];
    }
}
