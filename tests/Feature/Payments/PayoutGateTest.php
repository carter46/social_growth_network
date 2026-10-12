<?php

namespace Tests\Feature\Payments;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WithdrawalPayoutAttempt;
use App\Modules\Wallet\Payments\PayoutGate;
use App\Modules\Wallet\Services\WithdrawalPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsPayouts;
use Tests\TestCase;

class PayoutGateTest extends TestCase
{
    use BuildsPayouts;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();

        $this->installPayoutGateways();
        $this->coverRoute();
        $this->admin = $this->adminUser();
    }

    /** @return array<string, array{0: \Closure(): void}> */
    public static function closedGates(): array
    {
        return [
            'site still in NGN' => [fn () => config(['platform.currency' => 'NGN'])],
            'cutover not verified' => [fn () => SystemSetting::set(PayoutGate::SETTING_CUTOVER_VERIFIED_AT, '')],
            'payouts switched off' => [fn () => SystemSetting::set(PayoutGate::SETTING_ENABLED, false)],
        ];
    }

    #[DataProvider('closedGates')]
    public function test_closed_gate_keeps_the_withdrawal_waiting_without_sending_or_refunding(\Closure $close): void
    {
        $agent = $this->agentWithBalance('100.00');
        $withdrawal = $this->pendingWithdrawal($agent, $this->payoutAccount($agent));
        $before = $agent->wallet()->first()->only(['balance', 'locked_balance']);

        $close();
        $this->assertFalse(PayoutGate::isOpen());

        try {
            app(WithdrawalPayoutService::class)->approveAndSend($withdrawal, $this->admin->id);
            $this->fail('A payout was approved while the gate was closed.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Payouts are paused', $e->getMessage());
        }

        $this->assertSame('pending', $withdrawal->fresh()->status);
        $this->assertSame($before, $agent->wallet()->first()->only(['balance', 'locked_balance']));
        $this->assertSame(0, WithdrawalPayoutAttempt::query()->count());
        $this->assertCount(0, $this->gateways['bachs']->transfers);
        $this->assertCount(0, $this->gateways['flutterwave']->transfers);
    }

    public function test_admin_cannot_switch_payouts_on_before_the_cutover_is_verified(): void
    {
        SystemSetting::set(PayoutGate::SETTING_CUTOVER_VERIFIED_AT, '');
        SystemSetting::set(PayoutGate::SETTING_ENABLED, false);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.payout-gate'), ['action' => 'enable'])
            ->assertSessionHasErrors('payout_gate');
        $this->assertFalse(SystemSetting::enabled(PayoutGate::SETTING_ENABLED, false));

        $this->actingAs($this->admin)
            ->post(route('admin.settings.payout-gate'), ['action' => 'verify_cutover'])
            ->assertSessionHasErrors('payout_gate');
        $this->assertNull(PayoutGate::cutoverVerifiedAt());

        $this->actingAs($this->admin)
            ->post(route('admin.settings.payout-gate'), ['action' => 'verify_cutover', 'confirm_report' => '1'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('admin.settings.payout-gate'), ['action' => 'enable'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(PayoutGate::isOpen());

        $this->actingAs($this->admin)
            ->post(route('admin.settings.payout-gate'), ['action' => 'disable'])
            ->assertSessionHasNoErrors();
        $this->assertFalse(PayoutGate::isOpen());
    }
}
