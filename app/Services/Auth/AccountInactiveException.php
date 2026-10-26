<?php

namespace App\Services\Auth;

use RuntimeException;

/** Soft-deleted (trash) account tried to sign in — eligible for OTP reactivation. */
class AccountInactiveException extends RuntimeException
{
    public function __construct(
        public readonly string $email,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : __(
            'Your account is currently inactive. Activate it to sign in again.'
        ));
    }
}
