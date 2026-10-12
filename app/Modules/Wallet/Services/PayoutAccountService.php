<?php

namespace App\Modules\Wallet\Services;

use App\Models\PayoutAccount;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Modules\Admin\Services\AuditLogService;
use App\Modules\Wallet\Payments\Contracts\PreparesBeneficiaries;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Payments\Exceptions\GatewayException;
use App\Modules\Wallet\Payments\GatewayRegistry;
use App\Services\Communications\Email\EmailProfile;
use App\Services\Communications\Email\EmailService;
use App\Support\Payouts\AccountFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Agent payout accounts: up to the configured maximum, one primary, always in the profile name.
 * Every change needs the password + email code step (SecurityVerificationService).
 */
class PayoutAccountService
{
    public const NAME_CHECK_AUTO = 'auto';

    public function __construct(
        private GatewayRegistry $registry,
        private PayoutCoverageService $coverage,
        private BankCatalogService $banks,
        private SecurityVerificationService $security,
        private AuditLogService $audit,
        private EmailService $email,
    ) {}

    public function maxAccounts(): int
    {
        return max(1, (int) config('payments.payout.max_accounts', 3));
    }

    public static function bounceFee(): string
    {
        return number_format(max(0, (float) SystemSetting::get('withdrawal_bounce_fee', '0')), 2, '.', '');
    }

    public function hasOpenWithdrawal(User $user): bool
    {
        return Withdrawal::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereIn('status', Withdrawal::OPEN_STATUSES)
                    ->orWhereIn('internal_status', Withdrawal::OPEN_INTERNAL);
            })
            ->exists();
    }

    public function isVerified(User $user): bool
    {
        try {
            $this->security->assertVerified($user, SecurityVerificationService::PURPOSE_BANK_REPLACE);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function startVerification(User $user, string $password): void
    {
        $code = $this->security->start($user, SecurityVerificationService::PURPOSE_BANK_REPLACE, $password);

        $html = View::make('emails.bank-replace-otp', ['code' => $code, 'user' => $user])->render();
        $this->email->sendMailableHtml(
            to: $user->email,
            subject: 'Payout account change verification code',
            html: $html,
            profile: EmailProfile::NoReply,
            templateKey: 'bank_replace_otp',
        );
    }

    public function verifyCode(User $user, string $otp): void
    {
        $this->security->verify($user, SecurityVerificationService::PURPOSE_BANK_REPLACE, $otp);
    }

    /**
     * Schema fields for a country and method from config/payout_methods.php.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fieldsFor(string $country, string $method): array
    {
        $schema = (string) config('payout_methods.countries.'.strtoupper($country).'.methods.'.$method.'.schema', '');

        return (array) config('payout_methods.schemas.'.$schema.'.fields', []);
    }

    public function nameCheck(string $country, string $method): string
    {
        return (string) config('payout_methods.countries.'.strtoupper($country).'.methods.'.$method.'.name_check', 'confirm');
    }

    /**
     * Validate and normalise the submitted fields for a route.
     *
     * @param  array<string, mixed>  $input
     * @return array{fields: array<string, string>, bank_code: ?string, bank_name: ?string, mask: string}
     */
    public function validateFields(string $country, string $method, array $input): array
    {
        $country = strtoupper($country);
        if (! $this->coverage->isRouteOffered($country, $method)) {
            throw ValidationException::withMessages(['method' => __('Payouts to this country and method are not available.')]);
        }

        $fields = [];
        $errors = [];
        $bankCode = null;
        $bankName = null;
        $mask = '';

        foreach ($this->fieldsFor($country, $method) as $name => $def) {
            $label = (string) ($def['label'] ?? $name);
            $raw = trim((string) ($input[$name] ?? ''));
            $type = (string) ($def['type'] ?? 'text');

            if ($raw === '') {
                if ($def['required'] ?? false) {
                    $errors['fields.'.$name] = __(':field is required.', ['field' => $label]);
                }

                continue;
            }

            $value = match ($type) {
                'digits' => AccountFormat::digits($raw),
                default => $raw,
            };
            if (($def['validator'] ?? null) === 'iban') {
                $value = AccountFormat::normalizeIban($value);
            }
            if (in_array($def['validator'] ?? null, ['swift'], true) || $name === 'bank_address_state') {
                $value = strtoupper($value);
            }

            $len = strlen($value);
            if (isset($def['min']) && $len < (int) $def['min']) {
                $errors['fields.'.$name] = __(':field must be at least :min characters.', ['field' => $label, 'min' => $def['min']]);

                continue;
            }
            if (isset($def['max']) && $len > (int) $def['max']) {
                $errors['fields.'.$name] = __(':field may not be longer than :max characters.', ['field' => $label, 'max' => $def['max']]);

                continue;
            }

            if ($type === 'choice' && ! array_key_exists($value, (array) ($def['options'] ?? []))) {
                $errors['fields.'.$name] = __('Choose a valid :field.', ['field' => mb_strtolower($label)]);

                continue;
            }

            if ($type === 'select') {
                $option = $this->banks->find($country, $method, $value);
                if (! $option) {
                    $errors['fields.'.$name] = __('Choose a :field from the list.', ['field' => mb_strtolower($label)]);

                    continue;
                }
                if ($name === 'bank_code') {
                    $bankCode = $option['code'];
                    $bankName = $option['name'];
                } else {
                    $fields[$name.'_name'] = $option['name'];
                    $bankName ??= $option['name'];
                }
            }

            $validator = $def['validator'] ?? null;
            $valid = match ($validator) {
                'aba' => AccountFormat::validAbaRouting($value),
                'sort_code' => AccountFormat::validSortCode($value),
                'iban' => AccountFormat::validIban($value) && AccountFormat::ibanCountry($value) === $country,
                'swift' => AccountFormat::validSwift($value),
                'ca_institution' => AccountFormat::validCanadianInstitution($value),
                'ca_transit' => AccountFormat::validCanadianTransit($value),
                default => true,
            };
            if (! $valid) {
                $errors['fields.'.$name] = match ($validator) {
                    'aba' => __('Enter a valid 9-digit US routing number.'),
                    'iban' => __('Enter a valid IBAN for :country.', ['country' => config('payout_methods.countries.'.$country.'.name', $country)]),
                    'swift' => __('Enter a valid BIC (8 or 11 characters).'),
                    default => __(':field is not valid.', ['field' => $label]),
                };

                continue;
            }

            if ($name === 'bank_name') {
                $bankName = $value;
            }
            if ($def['mask'] ?? false) {
                $mask = AccountFormat::mask($value);
            }
            $fields[$name] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['fields' => $fields, 'bank_code' => $bankCode, 'bank_name' => $bankName, 'mask' => $mask];
    }

    /**
     * Case, punctuation, spacing and word order are ignored; middle names may be missing.
     */
    public static function namesMatch(string $profileName, string $accountName): bool
    {
        $tokens = function (string $name): array {
            $name = mb_strtolower(Str::ascii($name));
            $name = (string) preg_replace('/[^a-z0-9]+/', ' ', $name);

            return array_values(array_unique(array_filter(explode(' ', $name), fn ($t) => $t !== '')));
        };

        $a = $tokens($profileName);
        $b = $tokens($accountName);
        if ($a === [] || $b === []) {
            return false;
        }

        $sa = $a;
        $sb = $b;
        sort($sa);
        sort($sb);
        if ($sa === $sb) {
            return true;
        }

        [$small, $large] = count($a) <= count($b) ? [$a, $b] : [$b, $a];
        if (count($small) < 2) {
            return false;
        }

        return array_diff($small, $large) === [];
    }

    /**
     * Gateway name lookup for countries that support it. Null when no gateway can look it up.
     *
     * @param  array{fields: array<string, string>, bank_code: ?string, bank_name: ?string}  $validated
     */
    public function lookupName(User $user, string $country, string $method, array $validated): ?string
    {
        if ($this->nameCheck($country, $method) !== self::NAME_CHECK_AUTO) {
            return null;
        }
        $resolver = $this->registry->resolverFor($country, $method);
        if (! $resolver) {
            return null;
        }

        $details = new PayoutAccountDetails(
            country: strtoupper($country),
            currency: (string) config('payout_methods.countries.'.strtoupper($country).'.currency', 'USD'),
            method: $method,
            holderName: (string) $user->name,
            fields: $validated['fields'],
            bankCode: $validated['bank_code'],
            bankName: $validated['bank_name'],
            contactEmail: $user->email,
        );

        try {
            $name = trim($resolver->resolveAccount($details)->accountName);
        } catch (GatewayException $e) {
            throw ValidationException::withMessages(['fields' => $e->transient
                ? __('We could not reach the bank to check this account. Try again in a few minutes.')
                : __('We could not find this account. Check the details and try again.')]);
        }
        if ($name === '') {
            throw ValidationException::withMessages(['fields' => __('We could not find this account. Check the details and try again.')]);
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function add(
        User $user,
        string $country,
        string $method,
        array $input,
        bool $nameConfirmed,
        bool $termsAccepted,
        ?string $ip = null,
        ?string $userAgent = null,
    ): PayoutAccount {
        $this->security->assertVerified($user, SecurityVerificationService::PURPOSE_BANK_REPLACE);
        $country = strtoupper($country);

        if (PayoutAccount::query()->where('user_id', $user->id)->where('active', true)->count() >= $this->maxAccounts()) {
            throw ValidationException::withMessages(['account' => __('You can save up to :max payout accounts. Remove one first.', ['max' => $this->maxAccounts()])]);
        }
        if (blank($user->name)) {
            throw ValidationException::withMessages(['account' => __('Add your full name to your profile first.')]);
        }

        $validated = $this->validateFields($country, $method, $input);

        $dup = PayoutAccount::query()->where('user_id', $user->id)->where('active', true)
            ->where('country', $country)->where('method', $method)->get()
            ->contains(fn (PayoutAccount $a) => ($a->details ?? []) == $validated['fields'] && $a->bank_code === $validated['bank_code']);
        if ($dup) {
            throw ValidationException::withMessages(['account' => __('This account is already saved.')]);
        }

        if (! $termsAccepted) {
            throw ValidationException::withMessages(['accept_terms' => __('Confirm the account holder name and payout terms to continue.')]);
        }

        $resolvedName = $this->lookupName($user, $country, $method, $validated);
        if ($resolvedName !== null) {
            if (! self::namesMatch((string) $user->name, $resolvedName)) {
                throw ValidationException::withMessages(['fields' => __('This account is in the name of :resolved, which doesn\'t match your profile name :profile. Use an account in your own name, or contact support.', [
                    'resolved' => $resolvedName,
                    'profile' => $user->name,
                ])]);
            }
        } elseif (! $nameConfirmed) {
            throw ValidationException::withMessages(['confirm_name' => __('Confirm that the account is in your name.')]);
        }

        $bounceFee = self::bounceFee();
        $termsText = $this->termsText((string) $user->name, $bounceFee);

        $account = DB::transaction(function () use ($user, $country, $method, $validated, $resolvedName, $bounceFee, $termsText) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            $hasPrimary = PayoutAccount::query()->where('user_id', $user->id)->where('active', true)->where('is_primary', true)->exists();

            $account = PayoutAccount::create([
                'user_id' => $user->id,
                'country' => $country,
                'currency' => (string) config('payout_methods.countries.'.$country.'.currency', 'USD'),
                'method' => $method,
                'bank_code' => $validated['bank_code'],
                'bank_name' => $validated['bank_name'],
                'details' => $validated['fields'],
                'display_mask' => $validated['mask'] ?: '****',
                'account_holder_name' => (string) $user->name,
                'verification' => $resolvedName !== null ? PayoutAccount::RESOLVED : PayoutAccount::PENDING_REVIEW,
                'verified_at' => $resolvedName !== null ? now() : null,
                'resolved_by' => $resolvedName !== null ? $this->registry->resolverFor($country, $method)?->key() : null,
                'bounce_terms_accepted_at' => now(),
                'bounce_fee_disclosed' => $bounceFee,
                'bounce_terms_text' => $termsText,
                'is_primary' => ! $hasPrimary,
                'active' => true,
            ]);

            $this->clearVerification($user);

            return $account;
        });

        $this->prepareBeneficiaries($account);

        $this->audit->log(null, 'user.payout_account_added', $account, null, $this->auditView($account), $ip, [
            'actor_id' => $user->id,
            'actor_type' => 'user',
            'user_agent' => $userAgent,
            'module' => 'wallet',
        ]);
        $this->notify($user, __('A payout account was added to your profile.'), $account);

        return $account;
    }

    public function setPrimary(User $user, PayoutAccount $account, ?string $ip = null): void
    {
        $this->assertOwnedActive($user, $account);
        $this->security->assertVerified($user, SecurityVerificationService::PURPOSE_BANK_REPLACE);
        $this->assertNoOpenWithdrawal($user);

        if ($account->isRejected()) {
            throw ValidationException::withMessages(['account' => __('This account was rejected for payouts and cannot be primary.')]);
        }
        if (! $account->routeAvailable()) {
            throw ValidationException::withMessages(['account' => __('Payouts to this method are paused. Choose another account.')]);
        }

        DB::transaction(function () use ($user, $account) {
            PayoutAccount::query()->where('user_id', $user->id)->lockForUpdate()->get();
            PayoutAccount::query()->where('user_id', $user->id)->where('id', '!=', $account->id)->update(['is_primary' => false]);
            $account->update(['is_primary' => true]);
            $this->clearVerification($user);
        });

        $this->audit->log(null, 'user.payout_account_primary', $account, null, $this->auditView($account), $ip, [
            'actor_id' => $user->id, 'actor_type' => 'user', 'module' => 'wallet',
        ]);
        $this->notify($user, __('Your primary payout account was changed.'), $account);
    }

    public function remove(User $user, PayoutAccount $account, ?string $ip = null): void
    {
        $this->assertOwnedActive($user, $account);
        $this->security->assertVerified($user, SecurityVerificationService::PURPOSE_BANK_REPLACE);
        $this->assertNoOpenWithdrawal($user);

        DB::transaction(function () use ($user, $account) {
            PayoutAccount::query()->where('user_id', $user->id)->lockForUpdate()->get();
            $wasPrimary = (bool) $account->is_primary;
            $account->update(['active' => false, 'is_primary' => false]);
            $account->delete();

            if ($wasPrimary) {
                $next = PayoutAccount::query()->where('user_id', $user->id)->where('active', true)
                    ->where('verification', '!=', PayoutAccount::REJECTED)->orderBy('id')->first();
                $next?->update(['is_primary' => true]);
            }
            $this->clearVerification($user);
        });

        $this->audit->log(null, 'user.payout_account_removed', $account, $this->auditView($account), null, $ip, [
            'actor_id' => $user->id, 'actor_type' => 'user', 'module' => 'wallet',
        ]);
        $this->notify($user, __('A payout account was removed from your profile.'), $account);
    }

    /** Admin: mark an account unusable after review or an account-detail failure. */
    public function reject(PayoutAccount $account, string $reason, ?int $adminId = null): void
    {
        DB::transaction(function () use ($account, $reason) {
            $account->update([
                'verification' => PayoutAccount::REJECTED,
                'rejected_reason' => mb_substr($reason, 0, 255),
                'is_primary' => false,
            ]);
            if (! PayoutAccount::query()->where('user_id', $account->user_id)->where('active', true)->where('is_primary', true)->exists()) {
                PayoutAccount::query()->where('user_id', $account->user_id)->where('active', true)
                    ->where('verification', '!=', PayoutAccount::REJECTED)->orderBy('id')->first()
                    ?->update(['is_primary' => true]);
            }
        });

        if ($adminId) {
            $this->audit->log($adminId, 'payout_account.rejected', $account, null, ['reason' => $reason] + $this->auditView($account));
        }
    }

    /** A successful payout proves the account. */
    public function markResolvedByPayout(PayoutAccount $account, string $gatewayKey): void
    {
        if ($account->verification === PayoutAccount::PENDING_REVIEW) {
            $account->update(['verification' => PayoutAccount::RESOLVED, 'verified_at' => now(), 'resolved_by' => $gatewayKey.'_payout']);
        }
    }

    /**
     * Register the payee with gateways that need it, so any provider review runs before the first payout.
     */
    public function prepareBeneficiaries(PayoutAccount $account): void
    {
        foreach ($this->registry->payoutRoute((string) $account->country, (string) $account->method) as $key => $driver) {
            if (! $driver instanceof PreparesBeneficiaries) {
                continue;
            }
            try {
                $result = $driver->prepareBeneficiary(PayoutAccountDetails::fromPayoutAccount($account->fresh() ?? $account));
                $ids = array_replace((array) ($account->provider_beneficiary_ids ?? []), (array) ($result['ids'] ?? []));
                $account->update(['provider_beneficiary_ids' => $ids]);
            } catch (\Throwable $e) {
                Log::warning('payout_account.prepare_failed', ['account_id' => $account->id, 'gateway' => $key, 'error' => $e->getMessage()]);
            }
        }
    }

    public function termsText(string $profileName, string $bounceFee): string
    {
        $text = 'The account holder name is exactly '.$profileName.'. Payouts to an account in another name will fail';
        if (bccomp($bounceFee, '0', 2) > 0) {
            $text .= ', and a '.money($bounceFee, 2).' bounce fee applies';
        }

        return $text.'.';
    }

    private function assertOwnedActive(User $user, PayoutAccount $account): void
    {
        if ((int) $account->user_id !== (int) $user->id || ! $account->active) {
            abort(404);
        }
    }

    private function assertNoOpenWithdrawal(User $user): void
    {
        if ($this->hasOpenWithdrawal($user)) {
            throw ValidationException::withMessages([
                'account' => __('You cannot change payout accounts while a withdrawal is pending or being processed.'),
            ]);
        }
    }

    private function clearVerification(User $user): void
    {
        DB::table('security_verification_codes')
            ->where('user_id', $user->id)
            ->where('purpose', SecurityVerificationService::PURPOSE_BANK_REPLACE)
            ->delete();
    }

    /** @return array<string, mixed> */
    private function auditView(PayoutAccount $account): array
    {
        return [
            'country' => $account->country,
            'method' => $account->method,
            'bank_name' => $account->bank_name,
            'display_mask' => $account->display_mask,
            'holder_name' => $account->account_holder_name,
            'verification' => $account->verification,
            'is_primary' => (bool) $account->is_primary,
        ];
    }

    private function notify(User $user, string $summary, ?PayoutAccount $account): void
    {
        try {
            $html = View::make('emails.bank-replaced', ['user' => $user, 'summary' => $summary, 'account' => $account])->render();
            $this->email->sendMailableHtml(
                to: $user->email,
                subject: 'Your payout accounts were updated',
                html: $html,
                profile: EmailProfile::NoReply,
                templateKey: 'bank_replaced',
            );
        } catch (\Throwable $e) {
            Log::warning('payout_account.notify_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
