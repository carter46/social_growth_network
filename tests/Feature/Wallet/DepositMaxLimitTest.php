<?php

namespace Tests\Feature\Wallet;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFunding;
use App\Modules\Wallet\Payments\Contracts\PaymentRailInterface;
use App\Modules\Wallet\Services\DepositCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\FakePaymentRail;
use Tests\TestCase;

class DepositMaxLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakePaymentRail::reset();
        $this->app->bind(PaymentRailInterface::class, FakePaymentRail::class);
        SystemSetting::set('deposit_max_amount', '1000');
    }

    public function test_reserved_payment_above_max_is_held_and_not_credited(): void
    {
        $creator = User::factory()->kycApproved()->create();
        $creator->assignRole('user');
        $wallet = Wallet::factory()->create([
            'user_id' => $creator->id,
            'balance' => 0,
            'reserved_account_number' => '1122334455',
            'reserved_bank_name' => 'Test Bank',
            'reserved_account_reference' => 'RA-CREATOR-1',
        ]);

        $payload = [
            'paymentReference' => 'RSV-OVER-MAX',
            'amountPaid' => '5000.00',
            'paymentStatus' => 'PAID',
            'destinationAccountInformation' => ['accountNumber' => '1122334455'],
        ];

        $service = app(DepositCheckoutService::class);
        $funding = $service->creditReservedPayment($payload);

        $this->assertNotNull($funding);
        $this->assertSame(DepositCheckoutService::HELD_OVER_LIMIT, $funding->internal_status);
        $this->assertSame('pending', $funding->status);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $creator->id, 'type' => 'wallet.deposit_held']);
        $this->assertDatabaseHas('admin_notifications', ['type' => 'payment.deposit_held']);

        $service->creditReservedPayment($payload);
        $service->completeFromReturn('RSV-OVER-MAX');

        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
        $this->assertSame(1, WalletFunding::query()->where('provider_payment_reference', 'RSV-OVER-MAX')->count());
        $this->assertSame(DepositCheckoutService::HELD_OVER_LIMIT, $funding->fresh()->internal_status);
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
}
