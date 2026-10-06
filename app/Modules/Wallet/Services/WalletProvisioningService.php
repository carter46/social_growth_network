<?php

namespace App\Modules\Wallet\Services;

use App\Models\GatewayOperation;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Contracts\WalletProviderInterface;
use App\Services\Referrals\ReferralCommissionService;
use Illuminate\Support\Facades\Log;

class WalletProvisioningService
{
    public function __construct(
        private WalletProviderInterface $provider
    ) {}

    public function createWallet(User $user): Wallet
    {
        if (! $user->hasApprovedKyc()) {
            throw new \RuntimeException('KYC Level 1 required before creating a wallet.');
        }

        $existing = Wallet::where('user_id', $user->id)->first();
        if ($existing) {
            return $existing;
        }

        $idempotencyKey = 'wallet-create-'.$user->id;

        $operation = GatewayOperation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($operation?->status === 'completed' && $operation->wallet_id) {
            return Wallet::findOrFail($operation->wallet_id);
        }

        $operation = GatewayOperation::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'provider' => config('wallet.default_provider', 'manual'),
                'operation' => 'create_subaccount',
                'status' => 'pending',
                'user_id' => $user->id,
                'request_payload' => ['user_id' => $user->id],
            ]
        );

        try {
            $subaccountId = $this->provider->createSubaccount($user, $idempotencyKey);

            $wallet = Wallet::create([
                'user_id' => $user->id,
                'type' => \App\Enums\WalletType::User->value,
                'balance' => 0,
                'locked_balance' => 0,
                'currency' => 'NGN',
                'gateway_subaccount_id' => $subaccountId,
                'status' => 'active',
            ]);

            $operation->update([
                'status' => 'completed',
                'wallet_id' => $wallet->id,
                'response_payload' => ['gateway_subaccount_id' => $subaccountId],
            ]);

            Log::channel('financial')->info('Wallet created', [
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->releaseReferralCommissions($user);

            return $wallet;
        } catch (\Throwable $e) {
            $operation->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::channel('financial')->error('Wallet creation failed', [
                'user_id' => $user->id,
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function releaseReferralCommissions(User $user): void
    {
        try {
            app(ReferralCommissionService::class)->releasePending($user);
        } catch (\Throwable $e) {
            Log::channel('financial')->error('Referral commission release failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
