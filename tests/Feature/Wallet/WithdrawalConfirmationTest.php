<?php

namespace Tests\Feature\Wallet;

use App\Models\User;
use App\Models\UserBankAccount;
use App\Models\Withdrawal;
use App\Modules\Wallet\Services\SecurityVerificationService;
use App\Modules\Wallet\Services\WalletProvisioningService;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WithdrawalConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithBank(): array
    {
        $user = User::factory()->kycApproved()->create([
            'email_verified_at' => now(),
            'password' => Hash::make('password-123'),
        ]);
        $user->assignRole('agent');
        app(WalletProvisioningService::class)->createWallet($user);
        app(WalletService::class)->adminAdjust($user->fresh()->wallet, 5000, 'Test', 1);

        $bank = UserBankAccount::create([
            'user_id' => $user->id,
            'bank_name' => 'GTBank',
            'bank_code' => '058',
            'account_number' => '0123456789',
            'verified_name' => 'Test User',
            'verified_at' => now(),
            'verified_by' => 'monnify',
            'active' => true,
        ]);

        return [$user->fresh(), $bank];
    }

    public function test_withdrawal_requires_password_and_otp(): void
    {
        [$user, $bank] = $this->userWithBank();

        $this->actingAs($user)
            ->post(route('agent.withdrawal.otp'), [
                'password' => 'wrong-password',
                'amount' => 1000,
                'user_bank_account_id' => $bank->id,
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->post(route('agent.withdrawal.otp'), [
                'password' => 'password-123',
                'amount' => 1000,
                'user_bank_account_id' => $bank->id,
            ])
            ->assertRedirect(route('agent.withdrawal.create'));

        DB::table('security_verification_codes')
            ->where('user_id', $user->id)
            ->where('purpose', SecurityVerificationService::PURPOSE_WITHDRAWAL_REQUEST)
            ->update(['code_hash' => Hash::make('123456')]);

        $this->actingAs($user)
            ->post(route('agent.withdrawal.verify-otp'), ['otp' => '123456'])
            ->assertRedirect();

        $withdrawal = Withdrawal::where('user_id', $user->id)->first();
        $this->assertNotNull($withdrawal);
        $this->assertSame('pending_review', $withdrawal->internal_status);
        $this->assertEquals(1000.0, (float) $withdrawal->amount);

        $user->wallet->refresh();
        $this->assertEquals(1000.0, (float) $user->wallet->locked_balance);
    }

    public function test_direct_store_redirects_to_stepped_flow(): void
    {
        [$user, $bank] = $this->userWithBank();

        $this->actingAs($user)
            ->post(route('agent.withdrawal.store'), [
                'amount' => 500,
                'user_bank_account_id' => $bank->id,
            ])
            ->assertRedirect(route('agent.withdrawal.create'))
            ->assertSessionHas('error');
    }
}
