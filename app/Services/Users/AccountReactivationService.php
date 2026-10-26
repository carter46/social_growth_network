<?php

namespace App\Services\Users;

use App\Mail\OtpVerificationMail;
use App\Models\PurgedUserRecord;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogService;
use App\Services\Campaigns\CampaignAccountHoldService;
use App\Services\Communications\Email\EmailProfile;
use App\Services\Communications\Email\EmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AccountReactivationService
{
    public function __construct(
        private CampaignAccountHoldService $campaignHolds,
        private AuditLogService $audit,
        private EmailService $email,
    ) {}

    public function findReactivatableByEmail(string $email): ?User
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        $user = User::onlyTrashed()
            ->whereNull('anonymized_at')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user || $user->hasRole('admin')) {
            return null;
        }

        if (PurgedUserRecord::query()->where('original_user_id', $user->id)->exists()) {
            return null;
        }

        return $user;
    }

    /**
     * Send OTP to the account's registered email. Always returns a generic status for non-matches.
     */
    public function requestOtp(string $email, string $ip): void
    {
        $throttleKey = 'account-reactivate:'.$ip.':'.mb_strtolower(trim($email));
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many attempts. Please try again later.'),
            ]);
        }
        RateLimiter::hit($throttleKey, 60 * 15);

        $user = $this->findReactivatableByEmail($email);
        if (! $user) {
            // Avoid account enumeration — look like success.
            return;
        }

        $recentKey = 'account-reactivate-send:'.$user->id;
        if (RateLimiter::tooManyAttempts($recentKey, 3)) {
            throw ValidationException::withMessages([
                'email' => __('Please wait before requesting another code.'),
            ]);
        }
        RateLimiter::hit($recentKey, 60);

        $this->createAndSendOtp($user);
    }

    public function verifyAndRestore(string $email, string $otp, string $ip): User
    {
        $otp = preg_replace('/\D/', '', $otp) ?? '';
        if (strlen($otp) !== 6) {
            throw ValidationException::withMessages([
                'otp' => __('Enter the 6-digit code we emailed you.'),
            ]);
        }

        $verifyKey = 'account-reactivate-verify:'.$ip.':'.mb_strtolower(trim($email));
        if (RateLimiter::tooManyAttempts($verifyKey, 10)) {
            throw ValidationException::withMessages([
                'otp' => __('Too many attempts. Please try again later.'),
            ]);
        }
        RateLimiter::hit($verifyKey, 60 * 15);

        $normalizedEmail = mb_strtolower(trim($email));
        $maxAttempts = max(1, (int) config('user-lifecycle.reactivation_otp_max_attempts', 5));

        $restored = DB::transaction(function () use ($normalizedEmail, $otp, $maxAttempts, $ip) {
            $user = User::onlyTrashed()
                ->whereNull('anonymized_at')
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->lockForUpdate()
                ->first();

            if (! $user || $user->hasRole('admin')) {
                throw ValidationException::withMessages([
                    'otp' => __('The verification code is invalid or has expired.'),
                ]);
            }

            if (PurgedUserRecord::query()->where('original_user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'otp' => __('The verification code is invalid or has expired.'),
                ]);
            }

            $row = DB::table('account_reactivation_codes')
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                throw ValidationException::withMessages([
                    'otp' => __('The verification code has expired. Please request a new one.'),
                ]);
            }

            if ((int) $row->attempts >= $maxAttempts) {
                throw ValidationException::withMessages([
                    'otp' => __('Too many attempts. Please request a new code.'),
                ]);
            }

            if (! Hash::check($otp, $row->code_hash)) {
                DB::table('account_reactivation_codes')->where('id', $row->id)->increment('attempts');

                throw ValidationException::withMessages([
                    'otp' => __('The verification code is invalid.'),
                ]);
            }

            $updated = DB::table('account_reactivation_codes')
                ->where('id', $row->id)
                ->whereNull('used_at')
                ->update(['used_at' => now(), 'updated_at' => now()]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'otp' => __('This code was already used. Please request a new one.'),
                ]);
            }

            DB::table('account_reactivation_codes')
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->delete();

            $user->restore();
            $user->restoreAccess();
            $released = $this->campaignHolds->releaseForCreator($user, null, $ip);

            $this->audit->log(null, 'user.trash_restored', $user, null, [
                'user_id' => $user->id,
                'campaigns_released' => $released,
                'via' => 'otp_reactivation',
            ], $ip, [
                'actor_id' => $user->id,
                'actor_type' => 'user',
                'reason' => 'otp_reactivation',
            ]);

            return $user->fresh();
        });

        RateLimiter::clear($verifyKey);

        return $restored;
    }

    private function createAndSendOtp(User $user): void
    {
        $minutes = max(1, (int) config('user-lifecycle.reactivation_otp_expiry_minutes', 15));
        $code = sprintf('%06d', random_int(0, 999999));

        // Invalidate prior unused codes so only the latest OTP is valid.
        DB::table('account_reactivation_codes')
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        DB::table('account_reactivation_codes')->insert([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($minutes),
            'attempts' => 0,
            'used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailable = new OtpVerificationMail($code);
        $html = view('emails.otp-verification', ['code' => $code])->render();

        try {
            $this->email->sendMailableHtml(
                to: $user->email,
                subject: $mailable->envelope()->subject ?? 'Activate your account',
                html: $html,
                profile: EmailProfile::NoReply,
                templateKey: 'verification',
            );
        } catch (\Throwable $e) {
            Log::warning('account_reactivation.otp_send_failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw ValidationException::withMessages([
                'email' => __('We could not send the code right now. Please try again shortly.'),
            ]);
        }
    }
}
