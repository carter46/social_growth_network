<?php

namespace App\Modules\Wallet\Services;

use App\Events\WithdrawalRequested;
use App\Models\PayoutAccount;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Services\Communications\Email\EmailProfile;
use App\Services\Communications\Email\EmailService;
use App\Support\Money\Currency;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Withdrawal requests: always to the primary payout account, at the fee the agent confirmed.
 */
class WithdrawalConfirmationService
{
    public function __construct(
        private SecurityVerificationService $security,
        private WalletService $wallets,
        private EmailService $email,
        private WithdrawalFeeService $fees,
    ) {}

    public function assertCanRequest(User $user): void
    {
        if (! $user->hasPasswordSet()) {
            throw ValidationException::withMessages([
                'password' => __('Set a password on your account before requesting a withdrawal.'),
            ]);
        }

        $account = $user->primaryPayoutAccount;
        if (! $account) {
            throw ValidationException::withMessages([
                'bank' => __('Add a payout account before requesting a withdrawal.'),
            ]);
        }
        if ($account->isRejected()) {
            throw ValidationException::withMessages([
                'bank' => __('Your primary payout account was rejected. Choose another primary account.'),
            ]);
        }
        if (! $account->routeAvailable()) {
            throw ValidationException::withMessages([
                'bank' => __('Payouts to your primary account\'s method are paused. Choose another primary account.'),
            ]);
        }
        $disclosed = $account->acceptedBounceFee();
        if (Money::isPositive(PayoutAccountService::bounceFee(), 2) && Money::cmp($disclosed, PayoutAccountService::bounceFee(), 2) < 0) {
            throw ValidationException::withMessages([
                'terms' => __('The payout terms changed. Review and accept them for your primary account.'),
            ]);
        }

        if (SystemSetting::kycRequired()) {
            $minLevel = SystemSetting::kycRequiredLevel('withdrawal', 1);
            if (! $user->hasApprovedKyc($minLevel)) {
                throw ValidationException::withMessages([
                    'amount' => __('Complete KYC verification (level :level) before withdrawing.', [
                        'level' => $minLevel,
                    ]),
                ]);
            }
        }

        $hasOpen = Withdrawal::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereIn('status', Withdrawal::OPEN_STATUSES)
                    ->orWhereIn('internal_status', Withdrawal::OPEN_INTERNAL);
            })
            ->exists();

        if ($hasOpen) {
            throw ValidationException::withMessages([
                'amount' => __('You already have a withdrawal in progress.'),
            ]);
        }
    }

    /**
     * @return array<string, mixed> fee breakdown (see WithdrawalFeeService::quote)
     */
    public function quoteFor(User $user, string $amount): array
    {
        $account = $user->primaryPayoutAccount;
        if (! $account) {
            throw ValidationException::withMessages(['bank' => __('Add a payout account before requesting a withdrawal.')]);
        }

        $amount = Money::round($amount, 2);
        $wallet = $user->wallet;
        if (! $wallet || Money::cmp($wallet->withdrawableBalance(), $amount, 4) < 0) {
            throw ValidationException::withMessages([
                'amount' => __('Insufficient available balance. You can withdraw up to :max.', [
                    'max' => money($wallet?->withdrawableBalance() ?? '0', 2, 2),
                ]),
            ]);
        }

        return $this->fees->quote($amount, $account);
    }

    public function sendOtp(User $user, string $password, string $amount, int $payoutAccountId): void
    {
        $this->assertCanRequest($user);

        $account = $user->primaryPayoutAccount;
        if (! $account || (int) $account->id !== $payoutAccountId) {
            throw ValidationException::withMessages([
                'payout_account_id' => __('Withdrawals go to your primary payout account. Refresh the page and try again.'),
            ]);
        }

        $amount = Money::round($amount, 2);
        $breakdown = $this->fees->quote($amount, $account);

        $wallet = $user->wallet;
        if (! $wallet || Money::cmp($wallet->withdrawableBalance(), $amount, 4) < 0) {
            throw ValidationException::withMessages([
                'amount' => __('Insufficient available balance. You can withdraw up to :max.', [
                    'max' => money($wallet?->withdrawableBalance() ?? '0', 2, 2),
                ]),
            ]);
        }

        $code = $this->security->start(
            $user,
            SecurityVerificationService::PURPOSE_WITHDRAWAL_REQUEST,
            $password,
            [
                'amount' => $amount,
                'payout_account_id' => $account->id,
                'breakdown' => $breakdown,
            ],
        );

        $html = View::make('emails.withdrawal-request-otp', ['code' => $code, 'user' => $user])->render();
        $this->email->sendMailableHtml(
            to: $user->email,
            subject: 'Withdrawal request verification code',
            html: $html,
            profile: EmailProfile::NoReply,
            templateKey: 'withdrawal_request_otp',
        );
    }

    public function verifyOtpAndCreate(User $user, string $otp): Withdrawal
    {
        $this->security->verify($user, SecurityVerificationService::PURPOSE_WITHDRAWAL_REQUEST, $otp);
        $payload = $this->security->consumeVerifiedPayload($user, SecurityVerificationService::PURPOSE_WITHDRAWAL_REQUEST);

        $amount = Money::round((string) ($payload['amount'] ?? '0'), 2);
        $accountId = (int) ($payload['payout_account_id'] ?? 0);
        $breakdown = (array) ($payload['breakdown'] ?? []);
        $account = $user->primaryPayoutAccount;

        if (! $account instanceof PayoutAccount || (int) $account->id !== $accountId || ! Money::isPositive($amount, 2) || $breakdown === []) {
            throw ValidationException::withMessages([
                'otp' => __('Your verification session expired. Please start again.'),
            ]);
        }

        $this->assertCanRequest($user);

        $wallet = $user->wallet;
        if (! $wallet) {
            throw ValidationException::withMessages([
                'amount' => __('Create a wallet first.'),
            ]);
        }

        $destination = PayoutAccountDetails::fromPayoutAccount($account);
        $legacyNg = $account->country === 'NG' && $account->method === 'bank_account';

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'payout_account_id' => $account->id,
            'user_bank_account_id' => $account->legacy_user_bank_account_id,
            'destination_country' => $account->country,
            'destination_currency' => $account->currency,
            'method' => $account->method,
            'destination_details' => $destination->toSnapshot(),
            'amount' => $amount,
            'fee_amount' => $breakdown['fee'] ?? '0',
            'provider_fee' => $breakdown['provider_fee'] ?? '0',
            'platform_fee' => $breakdown['platform_fee'] ?? '0',
            'net_amount' => $breakdown['net'] ?? $amount,
            'fee_rule_id' => $breakdown['fee_rule_id'] ?? null,
            'quote_snapshot' => $breakdown['quote'] ?? null,
            'currency' => Currency::current()->code,
            'bank_name' => $account->bank_name ?: $account->methodLabel(),
            'bank_code' => $legacyNg ? $account->bank_code : null,
            'account_number' => (string) $destination->accountNumber(),
            'account_name' => $account->account_holder_name,
            'status' => 'pending',
            'internal_status' => 'pending_review',
            'reference' => 'WDR-'.strtoupper(Str::random(10)),
        ]);

        try {
            $this->wallets->lockForWithdrawal($withdrawal);
        } catch (\InvalidArgumentException $e) {
            $withdrawal->delete();
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        DB::afterCommit(function () use ($withdrawal) {
            WithdrawalRequested::dispatch(
                (int) $withdrawal->id,
                (int) $withdrawal->user_id,
                (float) $withdrawal->amount,
                (string) $withdrawal->currency
            );
        });

        return $withdrawal;
    }
}
