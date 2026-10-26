<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Suspended → Trash
    |--------------------------------------------------------------------------
    |
    | Accounts that stay suspended for this many days are soft-deleted (trash)
    | automatically so they become eligible for OTP reactivation.
    |
    */
    'suspend_to_trash_days' => (int) env('USER_SUSPEND_TO_TRASH_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Trash → Historical purge
    |--------------------------------------------------------------------------
    |
    | Trashed accounts older than this many days leave live Trash: a searchable
    | purged_user_records row is written, then the users row is anonymized and
    | kept as a soft-deleted tombstone (not hard-deleted) so wallet/transaction
    | foreign keys are not cascade-wiped. Admins can also purge manually from Trash.
    |
    | Daily processing needs real cron (`schedule:run`) or an admin dashboard hit
    | (poor-man's cron). Without either, transitions will not run.
    |
    */
    'trash_to_purge_days' => (int) env('USER_TRASH_TO_PURGE_DAYS', 180),

    /*
    |--------------------------------------------------------------------------
    | Job batch sizes
    |--------------------------------------------------------------------------
    */
    'trash_batch' => (int) env('USER_LIFECYCLE_TRASH_BATCH', 100),
    'purge_batch' => (int) env('USER_LIFECYCLE_PURGE_BATCH', 50),

    /*
    |--------------------------------------------------------------------------
    | Reactivation OTP
    |--------------------------------------------------------------------------
    */
    'reactivation_otp_expiry_minutes' => (int) env('USER_REACTIVATION_OTP_MINUTES', 15),
    'reactivation_otp_max_attempts' => (int) env('USER_REACTIVATION_OTP_MAX_ATTEMPTS', 5),
];
