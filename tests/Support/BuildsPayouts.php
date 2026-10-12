<?php

namespace Tests\Support;

use App\Models\PayoutAccount;
use App\Models\PayoutCoverage;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Payments\Data\PayoutQuote;
use App\Modules\Wallet\Payments\PayoutGate;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait BuildsPayouts
{
    /** @var array<string, FakeGateway> */
    protected array $gateways = [];

    /**
     * Two fake payout gateways in route order (bachs first), with live quotes.
     *
     * @param  list<string>  $keys
     */
    protected function installPayoutGateways(array $keys = ['bachs', 'flutterwave'], string $providerFee = '1.00'): void
    {
        $this->gateways = FakeGateway::install($keys);
        foreach ($this->gateways as $key => $gateway) {
            $gateway->quote = $this->liveQuote($providerFee);
        }
        config(['payments.payout.quote_cache_minutes' => 0]);
        Cache::flush();
        $this->openPayoutGate();
    }

    protected function openPayoutGate(): void
    {
        config(['platform.currency' => 'USD']);
        SystemSetting::set(PayoutGate::SETTING_CUTOVER_VERIFIED_AT, now()->toIso8601String());
        SystemSetting::set(PayoutGate::SETTING_ENABLED, true);
    }

    protected function liveQuote(string $providerFee, string $currency = 'NGN', string $fxRate = '1500'): PayoutQuote
    {
        return new PayoutQuote($providerFee, 'USD', $currency, $fxRate);
    }

    protected function setQuote(string $gateway, string $providerFee): void
    {
        $this->gateways[$gateway]->quote = $this->liveQuote($providerFee);
        Cache::flush();
    }

    /**
     * @param  list<string>  $gateways
     */
    protected function coverRoute(string $country = 'NG', string $method = 'bank_account', array $gateways = ['bachs', 'flutterwave'], string $state = PayoutCoverage::VERIFIED_API): void
    {
        foreach ($gateways as $key) {
            PayoutCoverage::query()->updateOrCreate(
                ['gateway_key' => $key, 'country' => $country, 'method' => $method],
                [
                    'currency' => (string) config('payout_methods.countries.'.$country.'.currency', 'USD'),
                    'state' => $state,
                    'verified_at' => now(),
                ],
            );
        }
    }

    protected function agentWithBalance(string $balance = '100.00', string $name = 'Ada Lovelace'): User
    {
        $agent = User::factory()->agent()->kycApproved()->create([
            'name' => $name,
            'email_verified_at' => now(),
        ]);
        Wallet::factory()->create(['user_id' => $agent->id, 'balance' => $balance, 'locked_balance' => 0]);

        return $agent->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function payoutAccount(User $user, array $overrides = []): PayoutAccount
    {
        return PayoutAccount::query()->create(array_merge([
            'user_id' => $user->id,
            'country' => 'NG',
            'currency' => 'NGN',
            'method' => 'bank_account',
            'bank_code' => '001',
            'bank_name' => 'Example Bank',
            'details' => ['bank_code' => '001', 'account_number' => '0123456789'],
            'display_mask' => '******6789',
            'account_holder_name' => $user->name,
            'verification' => PayoutAccount::RESOLVED,
            'verified_at' => now(),
            'is_primary' => true,
            'active' => true,
            'bounce_terms_accepted_at' => now(),
            'bounce_fee_disclosed' => '0',
        ], $overrides));
    }

    /**
     * A withdrawal in pending_review with the full amount held, as WithdrawalConfirmationService creates it.
     */
    protected function pendingWithdrawal(User $user, PayoutAccount $account, string $amount = '50.00', string $providerFee = '1.00', string $platformFee = '0.00'): Withdrawal
    {
        $destination = PayoutAccountDetails::fromPayoutAccount($account);
        $fee = bcadd($providerFee, $platformFee, 2);

        $withdrawal = Withdrawal::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'payout_account_id' => $account->id,
            'destination_country' => $account->country,
            'destination_currency' => $account->currency,
            'method' => $account->method,
            'destination_details' => $destination->toSnapshot(),
            'amount' => $amount,
            'fee_amount' => $fee,
            'provider_fee' => $providerFee,
            'platform_fee' => $platformFee,
            'net_amount' => bcsub($amount, $fee, 2),
            'currency' => 'USD',
            'bank_name' => $account->bank_name ?: $account->methodLabel(),
            'account_number' => (string) $destination->accountNumber(),
            'account_name' => $account->account_holder_name,
            'status' => 'pending',
            'internal_status' => 'pending_review',
            'reference' => 'WDR-'.strtoupper(Str::random(10)),
        ]);

        app(WalletService::class)->lockForWithdrawal($withdrawal);

        return $withdrawal->fresh();
    }

    protected function adminUser(): User
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        return $admin;
    }
}
