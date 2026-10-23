<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Withdrawal;
use App\Modules\Wallet\Services\WalletProvisioningService;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalRejectTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejecting_completed_withdrawal_does_not_credit_again(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $user = User::factory()->kycApproved()->create(['email_verified_at' => now()]);
        $wallet = app(WalletProvisioningService::class)->createWallet($user);
        app(WalletService::class)->adminAdjust($wallet, 5000, 'seed', 1);

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 1000,
            'currency' => 'USD',
            'bank_name' => 'GTBank',
            'bank_code' => '058',
            'account_number' => '0123456789',
            'account_name' => $user->name,
            'status' => 'pending',
            'internal_status' => 'pending_review',
            'reference' => 'WDR-REJ-001',
        ]);

        app(WalletService::class)->lockForWithdrawal($withdrawal);
        $this->actingAs($admin)->post(route('admin.withdrawals.approve', $withdrawal), [
            'confirm_send' => '1',
        ]);

        $balanceAfterApprove = (float) $wallet->fresh()->balance;

        $this->actingAs($admin)->post(route('admin.withdrawals.reject', $withdrawal->fresh()), [
            'notes' => 'Already paid out; cannot reject again.',
        ]);

        $this->assertEquals($balanceAfterApprove, (float) $wallet->fresh()->balance);
    }

    public function test_rejecting_pending_withdrawal_returns_funds(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $user = User::factory()->kycApproved()->create(['email_verified_at' => now()]);
        $wallet = app(WalletProvisioningService::class)->createWallet($user);
        app(WalletService::class)->adminAdjust($wallet, 5000, 'seed', 1);

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 1000,
            'currency' => 'USD',
            'bank_name' => 'GTBank',
            'bank_code' => '058',
            'account_number' => '0123456789',
            'account_name' => $user->name,
            'status' => 'pending',
            'internal_status' => 'pending_review',
            'reference' => 'WDR-REJ-002',
        ]);

        app(WalletService::class)->lockForWithdrawal($withdrawal);
        $wallet->refresh();
        $this->assertEquals(5000.0, (float) $wallet->balance);
        $this->assertEquals(1000.0, (float) $wallet->locked_balance);
        $this->assertSame('4000.0000', $wallet->availableBalance());

        $this->actingAs($admin)->post(route('admin.withdrawals.reject', $withdrawal), [
            'notes' => 'Incomplete or mismatched bank details.',
        ]);

        $wallet->refresh();
        $this->assertEquals(5000.0, (float) $wallet->balance);
        $this->assertEquals(0.0, (float) $wallet->locked_balance);
        $this->assertSame('rejected', $withdrawal->fresh()->status);
        $this->assertSame('Incomplete or mismatched bank details.', $withdrawal->fresh()->admin_notes);
    }

    public function test_reject_requires_a_reason(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $user = User::factory()->kycApproved()->create(['email_verified_at' => now()]);
        $wallet = app(WalletProvisioningService::class)->createWallet($user);
        app(WalletService::class)->adminAdjust($wallet, 5000, 'seed', 1);

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 1000,
            'currency' => 'USD',
            'bank_name' => 'GTBank',
            'bank_code' => '058',
            'account_number' => '0123456789',
            'account_name' => $user->name,
            'status' => 'pending',
            'internal_status' => 'pending_review',
            'reference' => 'WDR-REJ-003',
        ]);

        app(WalletService::class)->lockForWithdrawal($withdrawal);

        $this->actingAs($admin)
            ->from(route('admin.withdrawals'))
            ->post(route('admin.withdrawals.reject', $withdrawal))
            ->assertRedirect(route('admin.withdrawals'))
            ->assertSessionHas('error');

        $this->assertSame('pending', $withdrawal->fresh()->status);
        $this->assertEquals(1000.0, (float) $wallet->fresh()->locked_balance);
    }
}
