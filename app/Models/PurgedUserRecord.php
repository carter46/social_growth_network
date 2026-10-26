<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable historical record after a trashed account is purged from the live users table.
 * Insert-only from lifecycle jobs — not updated or deleted via app UI.
 */
class PurgedUserRecord extends Model
{
    protected $fillable = [
        'original_user_id',
        'email',
        'name',
        'username',
        'phone',
        'role',
        'registered_at',
        'suspended_at',
        'trashed_at',
        'purged_at',
        'wallet_balance_at_purge',
        'wallet_locked_at_purge',
        'transaction_refs',
        'login_security_refs',
        'snapshot',
        'purge_reason',
        'purge_process',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'suspended_at' => 'datetime',
            'trashed_at' => 'datetime',
            'purged_at' => 'datetime',
            'wallet_balance_at_purge' => 'decimal:4',
            'wallet_locked_at_purge' => 'decimal:4',
            'transaction_refs' => 'array',
            'login_security_refs' => 'array',
            'snapshot' => 'array',
        ];
    }
}
