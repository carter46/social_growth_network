<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site currency
    |--------------------------------------------------------------------------
    |
    | Every price, wallet, reward, withdrawal and report is held in this one
    | currency. There is no per-user currency switch. During the cutover the
    | site can keep showing NGN (PLATFORM_CURRENCY=NGN) until the one-time
    | currency:convert-to-usd command has run.
    |
    */

    'currency' => env('PLATFORM_CURRENCY', 'USD'),

    'currencies' => [
        'USD' => ['symbol' => '$', 'decimals' => 2, 'locale' => 'en-US', 'name' => 'US dollar'],
        'NGN' => ['symbol' => '₦', 'decimals' => 2, 'locale' => 'en-NG', 'name' => 'Nigerian naira'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage precision
    |--------------------------------------------------------------------------
    |
    | Balances, holds, ledger entries, rewards and budgets keep 4 decimals so
    | sub-cent rewards are not lost to rounding. Per-unit prices keep 6.
    | Orders, deposits and withdrawals are whole cents.
    |
    */

    'ledger_scale' => 4,
    'unit_price_scale' => 6,
    'cash_scale' => 2,

];
