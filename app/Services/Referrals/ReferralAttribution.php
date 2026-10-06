<?php

namespace App\Services\Referrals;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class ReferralAttribution
{
    public const SESSION_KEY = 'referral_code';

    public const COOKIE_NAME = 'referral_code';

    private const COOKIE_MINUTES = 60 * 24 * 30;

    /**
     * Store a valid `?ref=` from a referral link so it survives until signup, and
     * return the code to pre-fill (the link's code, else a previously stored one).
     */
    public function remember(Request $request): ?string
    {
        $code = ReferralCodeGenerator::normalize($request->query('ref'));

        if ($code !== '' && strlen($code) <= 16 && $this->findReferrer($code)) {
            $request->session()->put(self::SESSION_KEY, $code);
            Cookie::queue(self::COOKIE_NAME, $code, self::COOKIE_MINUTES);

            return $code;
        }

        $remembered = $this->rememberedCode($request);

        return $remembered && $this->findReferrer($remembered) ? $remembered : null;
    }

    public function rememberedCode(?Request $request = null): ?string
    {
        $request ??= request();
        $code = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
        $code = ReferralCodeGenerator::normalize($code ?: $request->cookie(self::COOKIE_NAME));

        return $code !== '' ? $code : null;
    }

    /** Only active agents can refer. */
    public function findReferrer(?string $code): ?User
    {
        $code = ReferralCodeGenerator::normalize($code);

        if ($code === '') {
            return null;
        }

        $user = User::query()
            ->where('referral_code', $code)
            ->where('is_suspended', false)
            ->whereNull('anonymized_at')
            ->first();

        return $user && $user->isAgent() ? $user : null;
    }

    /**
     * Link a brand-new account to its referrer. The link is permanent: an account
     * that already has a referrer is never re-attributed.
     */
    public function attach(User $newUser, ?string $code): ?User
    {
        $referrer = $this->findReferrer($code);

        if (! $referrer || $referrer->is($newUser) || $newUser->referred_by_id !== null) {
            $this->forget();

            return null;
        }

        $newUser->forceFill(['referred_by_id' => $referrer->id])->save();
        $this->forget();

        return $referrer;
    }

    public function forget(): void
    {
        $request = request();

        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_KEY);
        }

        Cookie::queue(Cookie::forget(self::COOKIE_NAME));
    }
}
