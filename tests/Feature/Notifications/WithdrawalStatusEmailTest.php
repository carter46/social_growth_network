<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationDeliveryLog;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Withdrawal;
use App\Modules\Wallet\Services\WalletProvisioningService;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Communications\Email\EmailService;
use App\Services\Communications\Email\SendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WithdrawalStatusEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejecting_withdrawal_emails_the_user(): void
    {
        $this->mockSuccessfulMail();

        $user = User::factory()->kycApproved()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
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
            'reference' => 'WDR-EMAIL-REJ',
        ]);

        app(WalletService::class)->lockForWithdrawal($withdrawal);
        app(WalletService::class)->unlockRejectedWithdrawal($withdrawal, 'Incomplete bank details');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'wallet.withdrawal_rejected',
            'title' => 'Withdrawal rejected',
        ]);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_type' => 'wallet.withdrawal_rejected',
            'recipient' => $user->email,
            'channel' => 'mail',
            'status' => 'sent',
        ]);

        $body = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'wallet.withdrawal_rejected')
            ->value('body');

        $this->assertStringContainsString('Incomplete bank details', (string) $body);
        $this->assertStringContainsString('returned to your wallet', (string) $body);
    }

    public function test_approving_withdrawal_emails_the_user(): void
    {
        $this->mockSuccessfulMail();

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $user = User::factory()->kycApproved()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
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
            'reference' => 'WDR-EMAIL-APP',
        ]);

        app(WalletService::class)->lockForWithdrawal($withdrawal);
        app(WalletService::class)->markWithdrawalApproved($withdrawal, $admin->id);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'wallet.withdrawal_approved',
            'title' => 'Withdrawal approved',
        ]);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_type' => 'wallet.withdrawal_approved',
            'recipient' => $user->email,
            'channel' => 'mail',
            'status' => 'sent',
        ]);

        $this->assertSame(
            1,
            NotificationDeliveryLog::query()
                ->where('notification_type', 'wallet.withdrawal_approved')
                ->where('channel', 'mail')
                ->where('status', 'sent')
                ->count()
        );
    }

    private function mockSuccessfulMail(): void
    {
        $mock = Mockery::mock(EmailService::class);
        $mock->shouldReceive('sendMailableHtml')->andReturn(SendResult::ok('test', 'msg-1'));
        $this->app->instance(EmailService::class, $mock);
    }
}
