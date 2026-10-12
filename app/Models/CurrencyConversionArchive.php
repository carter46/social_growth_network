<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Original NGN value of every figure converted by currency:convert-to-usd.
 */
class CurrencyConversionArchive extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'currency_conversion_archive';

    protected $guarded = ['id'];
}
