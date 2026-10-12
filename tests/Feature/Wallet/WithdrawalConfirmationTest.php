<?php

namespace Tests\Feature\Wallet;

use App\Models\PayoutAccount;
use App\Models\User;
use App\Models\Withdrawal;
use App\Modules\Wallet\Services\SecurityVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsPayouts;
use Tests\TestCase;

class WithdrawalConfirmationTest extends TestCase
{
    use BuildsPayouts;
    use RefreshDatabase;

    /** @return array{0: User, 1: PayoutAccount} */
    private function userWithPayoutAccount(): array
    {
        $this->installPayoutGateways(providerFee: '1.50');
        $this->coverRoute();

        $user = $this->agentWithBalance('500.00', 'Test User');
        $user->forceFill(['password' => Hash::make('password-123')])->save();

        return [$user->fresh(), $this->payoutAccount($user)];
    }

    public function test_withdrawal_requires_password_and_otp(): void
    {
        [$user, $account] = $this->userWithPayoutAccount();

        $this->actingAs($user)
            ->post(route('agent.withdrawal.otp'), [
                'password' => 'wrong-password',
                'amount' => 100,
                'payout_account_id' => $account->id,
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->post(route('agent.withdrawal.otp'), [
                'password' => 'password-123',
                'amount' => 100,
                'payout_account_id' => $account->id,
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
        $this->assertSame('USD', $withdrawal->currency);
        $this->assertSame($account->id, (int) $withdrawal->payout_account_id);
        $this->assertEquals(100.0, (float) $withdrawal->amount);
        $this->assertEquals(1.5, (float) $withdrawal->provider_fee);
        $this->assertEquals(98.5, (float) $withdrawal->net_amount);

        $user->wallet->refresh();
        $this->assertEquals(100.0, (float) $user->wallet->locked_balance);
    }

    public function test_withdrawal_to_a_non_primary_account_is_refused(): void
    {
        [$user] = $this->userWithPayoutAccount();
        $other = $this->payoutAccount($user, ['is_primary' => false, 'details' => ['bank_code' => '001', 'account_number' => '9999999999']]);

        $this->actingAs($user)
            ->post(route('agent.withdrawal.otp'), [
                'password' => 'password-123',
                'amount' => 100,
                'payout_account_id' => $other->id,
            ])
            ->assertSessionHasErrors('payout_account_id');
    }

    public function test_direct_store_redirects_to_stepped_flow(): void
    {
        [$user, $account] = $this->userWithPayoutAccount();

        $this->actingAs($user)
            ->post(route('agent.withdrawal.store'), [
                'amount' => 50,
                'payout_account_id' => $account->id,
            ])
            ->assertRedirect(route('agent.withdrawal.create'))
            ->assertSessionHas('error');
    }
}
