<?php

namespace Tests\Feature;

use App\Models\PurgedUserRecord;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Users\AccountLifecycleService;
use App\Services\Users\AccountReactivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'password' => 'password',
        ], $attrs));
        $user->assignRole('user');

        return $user;
    }

    public function test_suspended_login_shows_support_message_without_activate(): void
    {
        $user = $this->member(['is_suspended' => true, 'suspended_at' => now()]);

        $this->from(route('login'))
            ->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('account_inactive');

        $this->assertGuest();
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Your account has been suspended')
            ->assertDontSee('Activate account');
    }

    public function test_trashed_login_shows_inactive_and_activate_cta_with_correct_password(): void
    {
        $user = $this->member();
        $user->delete();

        $response = $this->from(route('login'))
            ->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $response->assertSessionHasErrors('email')
            ->assertSessionHas('account_inactive')
            ->assertSessionHas('account_inactive_email', $user->email);

        $this->assertGuest();

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Activate account')
            ->assertSee('account is currently inactive', false);
    }

    public function test_trashed_login_with_wrong_password_does_not_disclose_inactive(): void
    {
        $user = $this->member();
        $user->delete();

        $this->from(route('login'))
            ->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('account_inactive');
    }

    public function test_otp_reactivates_trashed_account(): void
    {
        $user = $this->member();
        $user->suspend();
        $user->delete();

        $service = app(AccountReactivationService::class);
        $service->requestOtp($user->email, '127.0.0.1');

        $row = DB::table('account_reactivation_codes')->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($row);

        DB::table('account_reactivation_codes')->where('id', $row->id)->update([
            'code_hash' => Hash::make('123456'),
        ]);

        $restored = $service->verifyAndRestore($user->email, '123456', '127.0.0.1');
        $this->assertFalse($restored->trashed());
        $this->assertFalse($restored->is_suspended);
        $this->assertNotNull(DB::table('account_reactivation_codes')->where('id', $row->id)->value('used_at'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->verifyAndRestore($user->email, '123456', '127.0.0.1');
    }

    public function test_otp_resend_invalidates_prior_unused_codes(): void
    {
        $user = $this->member();
        $user->delete();

        $service = app(AccountReactivationService::class);
        $service->requestOtp($user->email, '127.0.0.1');
        $firstId = DB::table('account_reactivation_codes')->where('user_id', $user->id)->value('id');

        // Clear per-user send throttle for the second request in this test.
        \Illuminate\Support\Facades\RateLimiter::clear('account-reactivate-send:'.$user->id);

        $service->requestOtp($user->email, '127.0.0.1');

        $this->assertSame(0, DB::table('account_reactivation_codes')->where('id', $firstId)->whereNull('used_at')->count());
        $this->assertSame(1, DB::table('account_reactivation_codes')->where('user_id', $user->id)->whereNull('used_at')->count());
    }

    public function test_suspended_only_account_cannot_use_reactivation_otp(): void
    {
        $user = $this->member(['is_suspended' => true, 'suspended_at' => now()]);

        app(AccountReactivationService::class)->requestOtp($user->email, '127.0.0.1');
        $this->assertSame(0, DB::table('account_reactivation_codes')->where('user_id', $user->id)->count());
    }

    public function test_expired_suspension_is_moved_to_trash(): void
    {
        config(['user-lifecycle.suspend_to_trash_days' => 30]);

        $due = $this->member([
            'is_suspended' => true,
            'suspended_at' => now()->subDays(31),
        ]);
        $fresh = $this->member([
            'is_suspended' => true,
            'suspended_at' => now()->subDays(5),
        ]);
        $active = $this->member(['is_suspended' => false]);

        $result = app(AccountLifecycleService::class)->trashExpiredSuspensions();

        $this->assertSame(1, $result['trashed']);
        $this->assertTrue($due->fresh()->trashed());
        $this->assertNotNull($due->fresh()->suspended_at);
        $this->assertFalse($fresh->fresh()->trashed());
        $this->assertFalse($active->fresh()->trashed());
    }

    public function test_expired_trash_writes_historical_record_and_keeps_ledger(): void
    {
        config(['user-lifecycle.trash_to_purge_days' => 180]);

        $user = $this->member([
            'is_suspended' => true,
            'suspended_at' => now()->subMonths(7),
            'name' => 'Traceable User',
            'username' => 'trace_user',
        ]);
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => '42.5000',
            'locked_balance' => '1.0000',
        ]);
        $tx = Transaction::factory()->funding()->completed()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => '10.0000',
        ]);
        $userId = $user->id;
        $email = $user->email;
        $user->delete();
        User::onlyTrashed()->whereKey($userId)->update(['deleted_at' => now()->subDays(181)]);

        $result = app(AccountLifecycleService::class)->purgeExpiredTrash();

        $this->assertSame(1, $result['purged']);

        $tombstone = User::withTrashed()->find($userId);
        $this->assertNotNull($tombstone);
        $this->assertNotNull($tombstone->anonymized_at);
        $this->assertTrue($tombstone->trashed());
        $this->assertTrue(Wallet::query()->where('user_id', $userId)->exists());
        $this->assertTrue(Transaction::query()->whereKey($tx->id)->where('user_id', $userId)->exists());
        $this->assertEquals('42.5000', (string) Wallet::query()->where('user_id', $userId)->value('balance'));

        $record = PurgedUserRecord::query()->where('original_user_id', $userId)->first();
        $this->assertNotNull($record);
        $this->assertSame($email, $record->email);
        $this->assertSame('Traceable User', $record->name);
        $this->assertSame('trace_user', $record->username);
        $this->assertEquals('42.5000', (string) $record->wallet_balance_at_purge);
        $this->assertSame('auto_trash_retention', $record->purge_reason);
        $this->assertContains($tx->id, $record->transaction_refs['transaction_ids'] ?? []);

        $this->from(route('login'))
            ->post(route('login'), ['email' => $email, 'password' => 'password'])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('account_inactive');
    }

    public function test_admin_permanent_delete_writes_historical_record_and_hides_from_trash(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $user = $this->member([
            'email' => 'purge-me@example.com',
            'name' => 'Purge Me',
        ]);
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => '15.2500',
            'locked_balance' => '0.0000',
        ]);
        Transaction::factory()->funding()->completed()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => '5.0000',
        ]);
        $user->delete();
        $userId = $user->id;

        $destroyUrl = route('admin.trash.destroy', $userId);
        $trashPage = $this->actingAs($admin)->get(route('admin.trash'));
        $trashPage->assertOk()
            ->assertSee('Delete forever')
            ->assertSee('Permanently remove this account?', false)
            ->assertSee($destroyUrl, false)
            ->assertSee('name="_method"', false)
            ->assertSee('value="DELETE"', false);

        $this->actingAs($admin)
            ->from(route('admin.trash'))
            ->delete(route('admin.trash.destroy', $userId))
            ->assertRedirect(route('admin.trash'))
            ->assertSessionHas('status');

        $this->assertNotNull(PurgedUserRecord::query()->where('original_user_id', $userId)->first());
        $tombstone = User::withTrashed()->find($userId);
        $this->assertNotNull($tombstone?->anonymized_at);
        $this->assertTrue(Wallet::query()->where('user_id', $userId)->where('balance', '15.2500')->exists());

        $this->actingAs($admin)
            ->get(route('admin.trash'))
            ->assertOk()
            ->assertDontSee('purge-me@example.com');

        $this->actingAs($admin)
            ->post(route('admin.trash.restore', $userId))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue(User::withTrashed()->find($userId)->trashed());
        $this->assertNotNull(User::withTrashed()->find($userId)->anonymized_at);
    }

    public function test_historical_search_shows_purged_generation_and_live_reregistration(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $old = $this->member([
            'email' => 'same@example.com',
            'name' => 'Old Generation',
            'username' => 'old_gen',
        ]);
        $oldId = $old->id;
        $old->delete();
        app(AccountLifecycleService::class)->purgeTrashedUser(
            User::onlyTrashed()->findOrFail($oldId),
            'admin_manual_purge',
            'test',
        );

        $live = $this->member([
            'email' => 'same@example.com',
            'name' => 'New Registration',
            'username' => 'new_gen',
        ]);

        $this->assertNotSame($oldId, $live->id);

        $this->actingAs($admin)
            ->get(route('admin.purged-accounts', ['q' => 'same@example.com']))
            ->assertOk()
            ->assertSee('Old Generation')
            ->assertSee('Original ID '.$oldId)
            ->assertSee('Current live accounts')
            ->assertSee('New Registration')
            ->assertSee((string) $live->id);
    }

    public function test_admin_can_browse_historical_records(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        PurgedUserRecord::query()->create([
            'original_user_id' => 999001,
            'email' => 'gone@example.com',
            'name' => 'Gone User',
            'username' => 'gone_user',
            'role' => 'user',
            'purged_at' => now(),
            'purge_reason' => 'auto_trash_retention',
            'purge_process' => 'users:purge-expired-trash',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.purged-accounts', ['q' => 'gone@example.com']))
            ->assertOk()
            ->assertSee('Gone User')
            ->assertSee('gone@example.com');
    }

    public function test_self_delete_moves_to_trash_not_anonymize(): void
    {
        $user = $this->member(['password' => 'password']);
        Wallet::factory()->create(['user_id' => $user->id, 'balance' => '9.0000']);

        $this->actingAs($user)
            ->delete(route('dashboard.account.destroy'), ['password' => 'password'])
            ->assertRedirect(route('home'));

        $fresh = User::withTrashed()->find($user->id);
        $this->assertTrue($fresh->trashed());
        $this->assertNull($fresh->anonymized_at);
        $this->assertTrue(Wallet::query()->where('user_id', $user->id)->exists());
    }

    public function test_purge_anonymized_skips_users_with_wallet(): void
    {
        $member = User::factory()->create([
            'email_verified_at' => now(),
            'is_suspended' => true,
            'anonymized_at' => now()->subHours(25),
            'name' => 'Deleted User',
            'email' => 'deleted+ledger@invalid.local',
            'username' => 'deleted_ledger',
        ]);
        $member->assignRole('user');
        Wallet::factory()->create(['user_id' => $member->id, 'balance' => '3.0000']);
        $id = $member->id;

        $result = User::purgeAnonymizedOlderThanHours(24);

        $this->assertSame(0, $result['purged']);
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
        $this->assertDatabaseHas('users', ['id' => $id]);
        $this->assertTrue(Wallet::query()->where('user_id', $id)->exists());
    }
}
