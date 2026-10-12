<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installed gateway drivers
    |--------------------------------------------------------------------------
    |
    | Keyed by gateway key. Credentials live in integration_providers (encrypted),
    | entered on the admin Payment Settings page. Monnify is retired and is not
    | listed here, so it can't be chosen for checkout or payouts.
    |
    */

    'drivers' => [
        'bachs' => App\Modules\Wallet\Payments\Bachs\BachsDriver::class,
        'flutterwave' => App\Modules\Wallet\Payments\Flutterwave\FlutterwaveDriver::class,
    ],

    /* Default checkout order (first one is preselected) and default payout route. */
    'default_collection_order' => ['bachs', 'flutterwave'],
    'default_payout_order' => ['bachs', 'flutterwave'],

    /* A driver is skipped for new checkouts after repeated errors, then retried after a cooldown. */
    'health' => [
        'failure_threshold' => 3,
        'window_minutes' => 10,
        'cooldown_minutes' => 15,
    ],

    'checkout_expiry_minutes' => 40,

    'http_timeout_seconds' => 20,

    /*
    | /webhooks/monnify only logs late deliveries after cutover. Remove the route
    | and MonnifyWebhookController once the grace period ends.
    */
    'monnify_grace_log_only' => true,

    'payout' => [
        'max_accounts' => 3,
        // Highest share of a withdrawal the provider fee may take; sets the fee-share minimum.
        'max_fee_share_percent' => 20,
        // Re-quote at approval: the platform absorbs a higher fee up to max(flat, percent of fee).
        'fee_tolerance_flat' => '1.00',
        'fee_tolerance_percent' => 5,
        // Verified fee entries go stale after this many days.
        'verified_fee_max_age_days' => 90,
        // Agent must accept a higher re-quoted fee within this many days, or the withdrawal is refunded.
        'confirmation_expiry_days' => 7,
        'quote_cache_minutes' => 5,
        // Manual coverage enables expire unless re-confirmed.
        'manual_coverage_days' => 90,
        // Suggest a cheaper method when the fee exceeds this share of the amount.
        'high_fee_hint_percent' => 10,
        // payments:check-fee-quotes flags routes whose live fee drifted more than this from the verified entry.
        'fee_drift_alert_percent' => 10,
    ],

];
