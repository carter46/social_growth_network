<?php

namespace Tests\Feature\Wallet;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFunding;
use App\Modules\Wallet\Services\DepositCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class DepositMaxLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeGateway::install(['fake']);
        SystemSetting::set('deposit_max_amount', '1000');
    }

    public function test_checkout_above_max_is_rejected(): void
    {
        $creator = User::factory()->kycApproved()->create();
        $creator->assignRole('user');
        Wallet::factory()->create(['user_id' => $creator->id, 'balance' => 0]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('above the maximum deposit');

        app(DepositCheckoutService::class)->startCheckout($creator->fresh(), 5000, 'https://example.com/return');
    }

    public function test_held_deposit_is_not_credited_until_an_admin_releases_it(): void
    {
        $creator = User::factory()->kycApproved()->create();
        $creator->assignRole('user');
        $wallet = Wallet::factory()->create(['user_id' => $creator->id, 'balance' => 0]);

        $funding = WalletFunding::create([
            'user_id' => $creator->id,
            'wallet_id' => $wallet->id,
            'method' => 'fake_checkout',
            'amount' => '25.00',
            'currency' => 'USD',
            'status' => 'pending',
            'internal_status' => DepositCheckoutService::HELD_OVER_LIMIT,
            'provider' => 'fake',
            'reference' => 'DEP-HELD-1',
            'provider_payment_reference' => 'DEP-HELD-1',
        ]);

        $service = app(DepositCheckoutService::class);
        $service->completeFromReturn('DEP-HELD-1');
        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
        $this->assertSame(DepositCheckoutService::HELD_OVER_LIMIT, $funding->fresh()->internal_status);

        app(\App\Modules\Wallet\Payments\GatewayRegistry::class)->driver('fake')->verifyAmount = '25.00';
        $service->completeFromReturn('DEP-HELD-1', releaseHeld: true);
        $this->assertSame(25.0, (float) $wallet->fresh()->balance);
    }
}
