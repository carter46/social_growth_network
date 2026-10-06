<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WalletFunding;
use App\Modules\Wallet\Payments\Contracts\PaymentRailInterface;
use App\Modules\Wallet\Services\WalletProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePaymentRail;
use Tests\TestCase;

class FundingApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FakePaymentRail::reset();
        $this->app->bind(PaymentRailInterface::class, FakePaymentRail::class);
    }

    public function test_admin_cannot_credit_gateway_deposit_the_gateway_has_not_confirmed(): void
    {
        $user = User::factory()->kycApproved()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        app(WalletProvisioningService::class)->createWallet($user);
        $user->refresh();

        $funding = WalletFunding::create([
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'method' => 'monnify_checkout',
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => 'pending',
            'reference' => 'DEP-TEST-003',
            'provider_payment_reference' => 'DEP-TEST-003',
        ]);
        FakePaymentRail::$verifyResult = ['paymentStatus' => 'PENDING', 'amountPaid' => '0'];

        $this->actingAs($this->admin())
            ->post(route('admin.fundings.approve', $funding))
            ->assertSessionHas('error');

        $this->assertEquals(0.0, (float) $user->wallet->fresh()->balance);
        $this->assertNotSame('approved', $funding->fresh()->status);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_cannot_approve_legacy_bank_wallet_deposit(): void
    {
        $user = User::factory()->kycApproved()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        app(WalletProvisioningService::class)->createWallet($user);
        $user->refresh();

        $funding = WalletFunding::create([
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'method' => 'bank',
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => 'pending',
            'reference' => 'DEP-TEST-001',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.fundings.approve', $funding))
            ->assertRedirect()
            ->assertSessionHas('error');

        $user->wallet->refresh();
        $funding->refresh();

        $this->assertSame('pending', $funding->status);
        $this->assertEquals(0.0, (float) $user->wallet->balance);
    }

    public function test_admin_can_reverse_approved_gateway_deposit(): void
    {
        $user = User::factory()->kycApproved()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        app(WalletProvisioningService::class)->createWallet($user);
        $user->refresh();

        $funding = WalletFunding::create([
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'method' => 'monnify_checkout',
            'amount' => 3000,
            'currency' => 'NGN',
            'status' => 'pending',
            'reference' => 'DEP-TEST-002',
            'provider_payment_reference' => 'DEP-TEST-002',
        ]);
        FakePaymentRail::$verifyResult = ['paymentStatus' => 'PAID', 'amountPaid' => '3000.00'];

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.fundings.approve', $funding));
        $user->wallet->refresh();
        $this->assertEquals(3000.0, (float) $user->wallet->balance);

        $this->actingAs($admin)
            ->post(route('admin.fundings.reverse', $funding->fresh()), ['reason' => 'Duplicate deposit'])
            ->assertRedirect();

        $user->wallet->refresh();
        $funding->refresh();

        $this->assertEquals(0.0, (float) $user->wallet->balance);
        $this->assertSame('reversed', $funding->status);
        $this->assertTrue(AuditLog::where('action', 'funding.reversed')->exists());
    }
}
