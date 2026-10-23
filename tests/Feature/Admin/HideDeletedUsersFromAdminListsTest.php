<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletFunding;
use App\Models\Withdrawal;
use App\Modules\Wallet\Services\WalletProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HideDeletedUsersFromAdminListsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_money_lists_hide_anonymized_users(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');
        $admin->givePermissionTo(['finance.manage', 'support.manage', 'users.manage']);

        $live = User::factory()->kycApproved()->create(['email_verified_at' => now(), 'name' => 'Live Member']);
        $live->assignRole('user');
        $wallet = app(WalletProvisioningService::class)->createWallet($live);

        $gone = User::factory()->kycApproved()->create(['email_verified_at' => now(), 'name' => 'Gone Member']);
        $gone->assignRole('user');
        $goneWallet = app(WalletProvisioningService::class)->createWallet($gone);

        WalletFunding::query()->create([
            'user_id' => $live->id,
            'wallet_id' => $wallet->id,
            'method' => 'gateway',
            'provider' => 'bachs',
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'approved',
            'reference' => 'DEP-LIVE-1',
        ]);
        WalletFunding::query()->create([
            'user_id' => $gone->id,
            'wallet_id' => $goneWallet->id,
            'method' => 'gateway',
            'provider' => 'bachs',
            'amount' => 999,
            'currency' => 'USD',
            'status' => 'processing',
            'reference' => 'DEP-GONE-1',
            'provider_payment_reference' => 'pay_gone_1',
        ]);

        Order::factory()->platform()->create([
            'user_id' => $live->id,
            'reference' => 'PLT-LIVE',
            'status' => 'paid',
            'total_amount' => 50,
        ]);
        Order::factory()->platform()->create([
            'user_id' => $gone->id,
            'reference' => 'PLT-GONE',
            'status' => 'paid',
            'total_amount' => 50,
        ]);

        Transaction::query()->create([
            'user_id' => $live->id,
            'wallet_id' => $wallet->id,
            'type' => 'adjustment',
            'label' => 'Live txn',
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'completed',
            'reference' => 'TXN-LIVE',
        ]);
        Transaction::query()->create([
            'user_id' => $gone->id,
            'wallet_id' => $goneWallet->id,
            'type' => 'adjustment',
            'label' => 'Gone txn',
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'completed',
            'reference' => 'TXN-GONE',
        ]);

        Withdrawal::query()->create([
            'user_id' => $live->id,
            'wallet_id' => $wallet->id,
            'amount' => 25,
            'currency' => 'USD',
            'bank_name' => 'GTBank',
            'account_number' => '0123456789',
            'account_name' => $live->name,
            'status' => 'pending',
            'internal_status' => 'pending_review',
            'reference' => 'WDR-LIVE',
        ]);
        Withdrawal::query()->create([
            'user_id' => $gone->id,
            'wallet_id' => $goneWallet->id,
            'amount' => 25,
            'currency' => 'USD',
            'bank_name' => 'GTBank',
            'account_number' => '0123456789',
            'account_name' => $gone->name,
            'status' => 'processing',
            'internal_status' => 'processing',
            'reference' => 'WDR-GONE',
        ]);

        $this->assertTrue($gone->anonymize());

        $this->actingAs($admin)->get(route('admin.fundings'))
            ->assertOk()
            ->assertSee('DEP-LIVE-1', false)
            ->assertDontSee('DEP-GONE-1', false)
            ->assertDontSee('Deleted User', false);

        $this->actingAs($admin)->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('PLT-LIVE', false)
            ->assertDontSee('PLT-GONE', false);

        $this->actingAs($admin)->get(route('admin.transactions'))
            ->assertOk()
            ->assertSee('TXN-LIVE', false)
            ->assertDontSee('TXN-GONE', false);

        $this->actingAs($admin)->get(route('admin.withdrawals'))
            ->assertOk()
            ->assertSee('WDR-LIVE', false)
            ->assertDontSee('WDR-GONE', false);

        $this->actingAs($admin)->get(route('admin.reconciliation'))
            ->assertOk()
            ->assertDontSee('DEP-GONE-1', false)
            ->assertDontSee('WDR-GONE', false);
    }
}
