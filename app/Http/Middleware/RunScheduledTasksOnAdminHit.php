<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared-hosting backup when cPanel cron / queue workers are missing or late.
 *
 * After an admin finishes loading a dashboard page, at most once a minute we:
 *  1. run Laravel's scheduler via schedule:run (only due tasks — including
 *     users:trash-expired-suspensions and users:purge-expired-trash)
 *  2. drain a few queued jobs if the queue is not sync
 *
 * A cache lock prevents concurrent overlapping runs. This middleware never runs
 * lifecycle loops inline; batching lives on the Artisan commands themselves.
 *
 * Real cron is still preferred — this only helps while admins are active.
 */
class RunScheduledTasksOnAdminHit
{
    private const LOCK_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->shouldRun($request)) {
            return;
        }

        if (! Cache::add('poor_mans_cron:lock', 1, self::LOCK_SECONDS)) {
            return;
        }

        try {
            Artisan::call('schedule:run');
        } catch (\Throwable $e) {
            Log::warning('poor_mans_cron.schedule_failed', ['error' => $e->getMessage()]);
        }

        if (config('queue.default') === 'sync') {
            return;
        }

        try {
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-jobs' => 10,
                '--max-time' => 5,
                '--tries' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::warning('poor_mans_cron.queue_failed', ['error' => $e->getMessage()]);
        }
    }

    private function shouldRun(Request $request): bool
    {
        if (! $request->user()) {
            return false;
        }

        if (! $request->user()->hasRole('admin')) {
            return false;
        }

        // Dashboard / admin only — skip assets, webhooks, and public pages.
        $path = trim($request->path(), '/');

        return $path === 'dashboard'
            || str_starts_with($path, 'dashboard/')
            || $path === 'admin'
            || str_starts_with($path, 'admin/');
    }
}
