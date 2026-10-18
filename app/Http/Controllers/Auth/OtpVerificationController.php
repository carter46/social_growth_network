<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpVerificationMail;
use App\Services\Campaigns\YoutubeIdentityService;
use App\Services\Communications\Email\EmailProfile;
use App\Services\Communications\Email\EmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OtpVerificationController extends Controller
{
    private const CODE_LENGTH = 6;
    private const EXPIRY_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    private const ACCEPTED_RECENT_CODES = 3;

    public static function hasRecentCode(\App\Models\User $user, int $seconds = 60): bool
    {
        return DB::table('email_verification_codes')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subSeconds($seconds))
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Show the OTP verification form.
     */
    public function show(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($request->user()->homeRoute());
        }

        return view('auth.verify-email');
    }

    /**
     * Verify the OTP and mark email as verified.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->merge(['otp' => preg_replace('/\D/', '', (string) $request->input('otp'))]);
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($user->homeRoute());
        }

        $otp = (string) $request->input('otp');
        // Any recent unexpired code is accepted, so an email that arrives late or out of order still works after a resend.
        $rows = DB::table('email_verification_codes')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::ACCEPTED_RECENT_CODES)
            ->get();
        $row = $rows->first();

        if (! $row) {
            Log::warning('OTP verification failed: expired or missing code', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'otp' => __('The verification code has expired. Please request a new one.'),
            ]);
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            Log::warning('OTP verification blocked: max attempts', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'attempts' => $row->attempts,
            ]);

            throw ValidationException::withMessages([
                'otp' => __('Too many attempts. Please request a new code.'),
            ]);
        }

        $matched = $rows->contains(fn ($candidate) => Hash::check($otp, $candidate->code_hash));

        if (! $matched) {
            DB::table('email_verification_codes')
                ->where('id', $row->id)
                ->increment('attempts');

            Log::warning('OTP verification failed: invalid code', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'attempts' => $row->attempts + 1,
            ]);

            throw ValidationException::withMessages([
                'otp' => __('The verification code is invalid.'),
            ]);
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        DB::table('email_verification_codes')->where('user_id', $user->id)->delete();

        if ($user->isAgent() && YoutubeIdentityService::enabled()) {
            return redirect()->route('agent.onboarding')->with('status', __('Your email has been verified.'));
        }

        return redirect()->intended($user->homeRoute())->with('status', __('Your email has been verified.'));
    }

    /**
     * Resend OTP email.
     */
    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($request->user()->homeRoute());
        }

        $this->createAndSendOtp($request->user());

        return back()->with('status', 'verification-otp-sent');
    }

    /**
     * Generate OTP, store hash, and send email.
     */
    public static function createAndSendOtp(\App\Models\User $user): void
    {
        $code = sprintf('%06d', random_int(0, 999999));
        $codeHash = Hash::make($code);
        $expiresAt = now()->addMinutes(self::EXPIRY_MINUTES);

        DB::table('email_verification_codes')->insert([
            'user_id' => $user->id,
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailable = new OtpVerificationMail($code);
        $html = view('emails.otp-verification', ['code' => $code])->render();
        app(EmailService::class)->sendMailableHtml(
            to: $user->email,
            subject: $mailable->envelope()->subject ?? 'Verify your email',
            html: $html,
            profile: EmailProfile::NoReply,
            templateKey: 'verification',
        );
    }
}
