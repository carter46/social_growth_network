<?php

namespace Tests\Feature\Admin;

use App\Enums\TransactionType;
use App\Models\User;
use App\Models\WalletFunding;
use App\Modules\Wallet\Services\WalletProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class FundingIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = FakeGateway::install(['fake'])['fake'];
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
            'method' => 'fake_checkout',
            'amount' => 50,
            'currency' => 'USD',
            'status' => 'pending',
            'provider' => 'fake',
            'reference' => 'DEP-IDEM-001',
            'provider_payment_reference' => 'DEP-IDEM-001',
        ]);
        $this->gateway->verifyAmount = '50.00';

        $this->actingAs($admin)->post(route('admin.fundings.approve', $funding));
        $this->actingAs($admin)->post(route('admin.fundings.approve', $funding));

        $wallet->refresh();
        $this->assertEquals(50.0, (float) $wallet->balance);
        $this->assertEquals(1, $wallet->transactions()->where('type', TransactionType::Funding->value)->count());
    }
}
