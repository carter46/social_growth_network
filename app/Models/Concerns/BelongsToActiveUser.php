<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Hide rows owned by soft-deleted or anonymized ("Deleted User") accounts on operational lists.
 */
trait BelongsToActiveUser
{
    public function scopeForActiveUsers(Builder $query): Builder
    {
        return $query->whereHas('user', fn (Builder $user) => $user->notAnonymized());
    }
}
