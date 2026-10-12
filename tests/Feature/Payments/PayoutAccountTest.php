<?php

namespace Tests\Feature\Payments;

use App\Models\PayoutAccount;
use App\Models\User;
use App\Models\UserBankAccount;
use App\Modules\Wallet\Services\PayoutAccountService;
use App\Modules\Wallet\Services\SecurityVerificationService;
use App\Support\Payouts\AccountFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsPayouts;
use Tests\TestCase;

class PayoutAccountTest extends TestCase
{
    use BuildsPayouts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->installPayoutGateways();
    }

    private function service(): PayoutAccountService
    {
        return app(PayoutAccountService::class);
    }

    private function verifySecurity(User $user): void
    {
        $security = app(SecurityVerificationService::class);
        $code = $security->start($user, SecurityVerificationService::PURPOSE_BANK_REPLACE, 'password');
        $security->verify($user, SecurityVerificationService::PURPOSE_BANK_REPLACE, $code);
    }

    private function assertFieldError(callable $fn, string $key): void
    {
        try {
            $fn();
            $this->fail('Expected a validation error on '.$key);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), json_encode($e->errors()));
        }
    }

    public function test_account_format_validators(): void
    {
        $this->assertTrue(AccountFormat::validAbaRouting('011000015'));
        $this->assertFalse(AccountFormat::validAbaRouting('011000016'));
        $this->assertFalse(AccountFormat::validAbaRouting('12345678'));

        $this->assertTrue(AccountFormat::validSortCode('12-34-56'));
        $this->assertFalse(AccountFormat::validSortCode('12345'));

        $this->assertTrue(AccountFormat::validIban('DE89 3704 0044 0532 0130 00'));
        $this->assertFalse(AccountFormat::validIban('DE89370400440532013001'));
        $this->assertSame('DE', AccountFormat::ibanCountry('de89370400440532013000'));

        $this->assertTrue(AccountFormat::validCanadianInstitution('001'));
        $this->assertFalse(AccountFormat::validCanadianInstitution('01'));
        $this->assertTrue(AccountFormat::validCanadianTransit('12345'));
        $this->assertFalse(AccountFormat::validCanadianTransit('1234'));

        $this->assertSame('******6789', AccountFormat::mask('0123456789'));
    }

    public function test_route_fields_are_validated_per_country(): void
    {
        foreach (['US', 'GB', 'FR', 'CA'] as $country) {
            $this->coverRoute($country);
        }

        $us = [
            'scheme' => 'ach', 'bank_name' => 'Example Bank', 'routing_number' => '011000016', 'account_number' => '123456789',
            'bank_address_line1' => '1 Main St', 'bank_address_city' => 'Boston', 'bank_address_state' => 'ma', 'bank_address_postal_code' => '02110',
        ];
        $this->assertFieldError(fn () => $this->service()->validateFields('US', 'bank_account', $us), 'fields.routing_number');
        $ok = $this->service()->validateFields('US', 'bank_account', ['routing_number' => '011000015'] + $us);
        $this->assertSame('MA', $ok['fields']['bank_address_state']);
        $this->assertSame('*****6789', $ok['mask']);

        $this->assertFieldError(fn () => $this->service()->validateFields('GB', 'bank_account', [
            'bank_name' => 'Example Bank', 'sort_code' => '1234', 'account_number' => '12345678',
        ]), 'fields.sort_code');

        $this->assertFieldError(fn () => $this->service()->validateFields('FR', 'bank_account', [
            'bank_name' => 'Example Bank', 'iban' => 'DE89370400440532013000', 'swift_bic' => 'DEUTDEFF',
        ]), 'fields.iban');

        $this->assertFieldError(fn () => $this->service()->validateFields('CA', 'bank_account', [
            'scheme' => 'eft', 'bank_name' => 'Example Bank', 'institution_number' => '001', 'transit_number' => '12', 'account_number' => '1234567',
        ]), 'fields.transit_number');
    }

    public function test_route_without_verified_coverage_is_refused(): void
    {
        $this->assertFieldError(fn () => $this->service()->validateFields('GB', 'bank_account', [
            'bank_name' => 'Example Bank', 'sort_code' => '123456', 'account_number' => '12345678',
        ]), 'method');
    }

    public function test_names_match_tolerance(): void
    {
        $this->assertTrue(PayoutAccountService::namesMatch('Ada Lovelace', 'LOVELACE ADA'));
        $this->assertTrue(PayoutAccountService::namesMatch('Ada King Lovelace', 'Ada Lovelace'));
        $this->assertTrue(PayoutAccountService::namesMatch("Ada O'Lovelace", 'ada o lovelace'));
        $this->assertFalse(PayoutAccountService::namesMatch('Ada Lovelace', 'Charles Babbage'));
        $this->assertFalse(PayoutAccountService::namesMatch('Ada Lovelace', 'Ada'));
        $this->assertFalse(PayoutAccountService::namesMatch('', 'Ada Lovelace'));
    }

    public function test_auto_name_check_resolves_matching_account_and_refuses_another_name(): void
    {
        $this->coverRoute('NG');
        config(['wallet.allowed_bank_patterns' => ['Example Bank']]);
        $user = $this->agentWithBalance('0', 'Ada Lovelace');
        $input = ['bank_code' => '001', 'account_number' => '0123456789'];

        $this->gateways['bachs']->resolvedName = 'Charles Babbage';
        $this->verifySecurity($user);
        $this->assertFieldError(fn () => $this->service()->add($user, 'NG', 'bank_account', $input, false, true), 'fields');

        $this->gateways['bachs']->resolvedName = 'LOVELACE ADA';
        $account = $this->service()->add($user, 'NG', 'bank_account', $input, false, true);

        $this->assertSame(PayoutAccount::RESOLVED, $account->verification);
        $this->assertTrue($account->is_primary);
        $this->assertSame('******6789', $account->display_mask);
    }

    public function test_confirm_route_needs_confirmation_and_saves_as_pending_review(): void
    {
        $this->coverRoute('GB');
        $user = $this->agentWithBalance('0', 'Ada Lovelace');
        $input = ['bank_name' => 'Example Bank', 'sort_code' => '12-34-56', 'account_number' => '12345678'];

        $this->verifySecurity($user);
        $this->assertFieldError(fn () => $this->service()->add($user, 'GB', 'bank_account', $input, false, true), 'confirm_name');

        $account = $this->service()->add($user, 'GB', 'bank_account', $input, true, true);
        $this->assertSame(PayoutAccount::PENDING_REVIEW, $account->verification);
        $this->assertSame('123456', $account->details['sort_code']);
    }

    public function test_at_most_three_accounts_and_only_one_primary(): void
    {
        $this->coverRoute('GB');
        $user = $this->agentWithBalance('0', 'Ada Lovelace');
        $first = $this->payoutAccount($user);
        $this->payoutAccount($user, ['is_primary' => false, 'details' => ['bank_code' => '001', 'account_number' => '1111111111']]);

        $this->verifySecurity($user);
        $third = $this->service()->add($user, 'GB', 'bank_account', ['bank_name' => 'Example Bank', 'sort_code' => '123456', 'account_number' => '12345678'], true, true);
        $this->assertFalse($third->is_primary);

        $this->verifySecurity($user);
        $this->assertFieldError(fn () => $this->service()->add($user, 'GB', 'bank_account', [
            'bank_name' => 'Example Bank', 'sort_code' => '654321', 'account_number' => '87654321',
        ], true, true), 'account');

        $this->verifySecurity($user);
        $this->service()->setPrimary($user, $third);
        $this->assertSame([$third->id], PayoutAccount::query()->where('user_id', $user->id)->where('is_primary', true)->pluck('id')->all());
        $this->assertFalse($first->fresh()->is_primary);
    }

    public function test_profile_name_is_locked_once_a_payout_account_exists(): void
    {
        $user = $this->agentWithBalance('0', 'Ada Lovelace');
        $this->payoutAccount($user);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Someone Else', 'email' => $user->email])
            ->assertSessionHasErrors('name');

        $this->assertSame('Ada Lovelace', $user->fresh()->name);
    }

    public function test_backfill_turns_active_nigerian_banks_into_primary_payout_accounts(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $bank = UserBankAccount::create([
            'user_id' => $user->id,
            'bank_name' => 'Example Bank',
            'bank_code' => '001',
            'account_number' => '0123456789',
            'verified_name' => 'ADA LOVELACE',
            'verified_at' => now(),
            'verified_by' => 'monnify',
            'active' => true,
        ]);

        $migration = require database_path('migrations/2026_10_13_000300_backfill_payout_accounts_from_user_bank_accounts.php');
        $migration->up();
        $migration->up();

        $accounts = PayoutAccount::query()->where('user_id', $user->id)->get();
        $this->assertCount(1, $accounts);
        $account = $accounts->first();
        $this->assertSame('NG', $account->country);
        $this->assertSame(PayoutAccount::RESOLVED, $account->verification);
        $this->assertTrue($account->is_primary);
        $this->assertSame($bank->id, (int) $account->legacy_user_bank_account_id);
        $this->assertSame('0123456789', $account->details['account_number']);
    }
}
