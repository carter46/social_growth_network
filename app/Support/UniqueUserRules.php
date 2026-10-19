<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Soft-deleted users still occupy email/username in the database. Unique rules must
 * include them so a trashed account cannot be used to create a second signup.
 */
class UniqueUserRules
{
    public static function email(?int $ignoreId = null): Unique
    {
        $rule = Rule::unique('users', 'email');

        return $ignoreId ? $rule->ignore($ignoreId) : $rule;
    }

    public static function username(?int $ignoreId = null): Unique
    {
        $rule = Rule::unique('users', 'username');

        return $ignoreId ? $rule->ignore($ignoreId) : $rule;
    }
}
