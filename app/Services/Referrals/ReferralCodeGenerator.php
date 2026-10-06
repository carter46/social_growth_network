<?php

namespace App\Services\Referrals;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReferralCodeGenerator
{
    /** No 0/O or 1/I so codes are easy to read and type. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LENGTH = 8;

    private const MAX_ATTEMPTS = 10;

    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = $this->randomCode();

            if (! DB::table('users')->where('referral_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not generate a unique referral code.');
    }

    public static function normalize(?string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code) ?? '');
    }

    private function randomCode(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
