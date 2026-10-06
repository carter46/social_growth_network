<?php

namespace Tests\Feature\Admin;

use App\Enums\TransactionType;
use App\Models\User;
use App\Models\WalletFunding;
use App\Modules\Wallet\Payments\Contracts\PaymentRailInterface;
use App\Modules\Wallet\Services\WalletProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePaymentRail;
use Tests\TestCase;

class FundingIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FakePaymentRail::reset();
        $this->app->bind(PaymentRailInterface::class, FakePaymentRail::class);
    }

    public function test_double_funding_approve_does_not_double_credit(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $user = User::factory()->kycApproved()->create(['email_verified_at' => now()]);
        $wallet = app(WalletProvisioningService::class)->createWallet($user);

        $funding = WalletFunding::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'method' => 'monnify_checkout',
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => 'pending',
            'reference' => 'DEP-IDEM-001',
            'provider_payment_reference' => 'DEP-IDEM-001',
        ]);
        FakePaymentRail::$verifyResult = ['paymentStatus' => 'PAID', 'amountPaid' => '5000.00'];

        $this->actingAs($admin)->post(route('admin.fundings.approve', $funding));
        $this->actingAs($admin)->post(route('admin.fundings.approve', $funding));

        $wallet->refresh();
        $this->assertEquals(5000.0, (float) $wallet->balance);
        $this->assertEquals(1, $wallet->transactions()->where('type', TransactionType::Funding->value)->count());
    }
}
