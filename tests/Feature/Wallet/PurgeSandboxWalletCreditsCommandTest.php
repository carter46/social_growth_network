<?php

namespace Tests\Feature\Wallet;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletFunding;
use App\Modules\Wallet\Services\WalletProvisioningService;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeSandboxWalletCreditsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_change_balances(): void
    {
        [$admin, $user, $funding] = $this->seedSandboxDeposit('5000.00', now()->subDay());

        $this->artisan('wallets:purge-sandbox-credits', [
            '--before' => now()->toDateString(),
            '--admin' => $admin->id,
        ])->assertSuccessful();

        $this->assertSame('5000.0000', $user->wallet->fresh()->balance);
        $this->assertSame('approved', $funding->fresh()->status);
    }

    public function test_execute_reverses_sandbox_era_deposit(): void
    {
        [$admin, $user, $funding] = $this->seedSandboxDeposit('5000.00', now()->subDay());

        $this->artisan('wallets:purge-sandbox-credits', [
            '--before' => now()->toDateString(),
            '--execute' => true,
            '--admin' => $admin->id,
        ])->assertSuccessful();

        $this->assertSame('0.0000', $user->wallet->fresh()->balance);
        $this->assertSame('reversed', $funding->fresh()->status);
        $this->assertTrue(
            Transaction::query()
                ->where('wallet_id', $user->wallet->id)
                ->where('type', TransactionType::Reversal->value)
                ->exists()
        );
    }

    public function test_live_tagged_deposit_after_cutover_is_left_alone(): void
    {
        [$admin, $user] = $this->adminAndUser();
        $wallets = app(WalletService::class);

        $funding = WalletFunding::create([
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'method' => 'bachs_checkout',
            'provider' => 'bachs',
            'amount' => 25,
            'currency' => 'USD',
            'status' => 'pending',
            'reference' => 'DEP-LIVE-KEEP',
            'provider_payment_reference' => 'DEP-LIVE-KEEP',
            'metadata' => ['environment' => 'live'],
        ]);
        $wallets->creditFromFunding($funding, $admin->id);
        $funding->update(['approved_at' => now()]);

        $this->artisan('wallets:purge-sandbox-credits', [
            '--before' => now()->subDay()->toDateString(),
            '--execute' => true,
            '--admin' => $admin->id,
        ])->assertSuccessful();

        $this->assertSame('25.0000', $user->wallet->fresh()->balance);
        $this->assertSame('approved', $funding->fresh()->status);
    }

    public function test_spent_deposit_claws_remaining_available(): void
    {
        [$admin, $user, $funding] = $this->seedSandboxDeposit('100.00', now()->subDay());
        $wallets = app(WalletService::class);
        $wallets->adminAdjust($user->wallet, '-40', 'spent on watch hours', $admin->id);

        $this->artisan('wallets:purge-sandbox-credits', [
            '--before' => now()->toDateString(),
            '--execute' => true,
            '--admin' => $admin->id,
        ])->assertSuccessful();

        $this->assertSame('0.0000', $user->wallet->fresh()->balance);
        // Full reverse failed; funding stays approved, leftover was adjusted off.
        $this->assertSame('approved', $funding->fresh()->status);
        $this->assertTrue(
            Transaction::query()
                ->where('wallet_id', $user->wallet->id)
                ->where('type', TransactionType::AdminAdjustment->value)
                ->where('description', 'like', 'Sandbox purge leftover%')
                ->exists()
        );
    }

    /** @return array{0: User, 1: User, 2: WalletFunding} */
    private function seedSandboxDeposit(string $amount, $approvedAt): array
    {
        [$admin, $user] = $this->adminAndUser();
        $wallets = app(WalletService::class);

        $funding = WalletFunding::create([
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'method' => 'bachs_checkout',
            'provider' => 'bachs',
            'amount' => $amount,
            'currency' => 'USD',
            'status' => 'pending',
            'reference' => 'DEP-SBX-'.uniqid(),
            'provider_payment_reference' => 'DEP-SBX-'.uniqid(),
            'metadata' => ['environment' => 'sandbox'],
        ]);
        $wallets->creditFromFunding($funding, $admin->id);
        $funding->update(['approved_at' => $approvedAt]);

        return [$admin, $user->fresh(), $funding->fresh()];
    }

    /** @return array{0: User, 1: User} */
    private function adminAndUser(): array
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $user = User::factory()->kycApproved()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        app(WalletProvisioningService::class)->createWallet($user);
        $user->refresh();

        return [$admin, $user];
    }
}
