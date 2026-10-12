<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin override of the payout gateway order for a country, or a country and method.
 */
class PayoutRoute extends Model
{
    protected $fillable = ['country', 'method', 'gateway_keys', 'updated_by'];

    protected function casts(): array
    {
        return [
            'gateway_keys' => 'array',
        ];
    }
}
