<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

/**
 * Shared hosting (e.g. Hostinger) often disables proc_open.
 * Schedule::command() shells out via Symfony Process — use in-process Artisan::call instead.
 */
$scheduleCommand = function (string $command, string $name) {
    return Schedule::call(fn () => Artisan::call($command))
        ->name($name);
};

Schedule::call(function () {
    DB::table('email_verification_codes')->where('expires_at', '<', now())->delete();
})->daily()->name('prune-expired-otp-codes');

$scheduleCommand('app:prune-notifications', 'app:prune-notifications')->weekly()->sundays()->at('03:00');
$scheduleCommand('support:prune-attachments', 'support:prune-attachments')->hourly();
$scheduleCommand('cache:prune-stale-tags', 'cache:prune-stale-tags')->daily();

$scheduleCommand('monnify:reconcile', 'monnify:reconcile')->everyFiveMinutes();

$scheduleCommand('analytics:rollup-kpis', 'analytics:rollup-kpis')->hourly();
$scheduleCommand('analytics:prune-activity', 'analytics:prune-activity')->daily()->at('04:00');
$scheduleCommand('analytics:sync-ga', 'analytics:sync-ga')->daily()->at('05:00');
$scheduleCommand('monitoring:heartbeat', 'monitoring:heartbeat')->everyFiveMinutes();
$scheduleCommand('users:purge-anonymized', 'users:purge-anonymized')->hourly();
$scheduleCommand('site-integrations:expire-user-tools', 'site-integrations:expire-user-tools')->everyFiveMinutes();

Schedule::call(function () {
    \Illuminate\Support\Facades\Cache::forget('sitemap.xml.v2');
})->dailyAt('02:30')->name('refresh-sitemap-cache');

// Uncomment when mysqldump is available on the server (e.g. via cPanel cron + SSH):
// Schedule::command('app:backup-database')->daily()->at('02:00');
