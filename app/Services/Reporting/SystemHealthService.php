<?php

namespace App\Services\Reporting;

use App\Models\AnalyticsProvider;
use App\Models\MonitoringHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real platform health signals for Overview (honest N/A when unavailable).
 */
class SystemHealthService
{
    /**
     * @return array{rings: list<array<string, mixed>>, metrics: list<array<string, mixed>>, checked_at: string}
     */
    public function snapshot(): array
    {
        $diskTotal = @disk_total_space(base_path()) ?: 0;
        $diskFree = @disk_free_space(base_path()) ?: 0;
        $diskUsedPct = $diskTotal > 0 ? (float) round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : null;

        $cacheOk = $this->cacheHealthy();
        $failedJobs = $this->failedJobsCount();
        $dbSizeMb = $this->databaseSizeMb();

        $heartbeats = MonitoringHeartbeat::query()
            ->whereIn('key', ['monitoring', 'schedule'])
            ->orderByDesc('recorded_at')
            ->get()
            ->keyBy('key');

        $monitoringAt = $heartbeats->get('monitoring')?->recorded_at;
        $scheduleAt = $heartbeats->get('schedule')?->recorded_at;

        $ga = AnalyticsProvider::forProvider(AnalyticsProvider::PROVIDER_GOOGLE_ANALYTICS);
        $clarity = AnalyticsProvider::forProvider(AnalyticsProvider::PROVIDER_MICROSOFT_CLARITY);

        $rings = [
            [
                'label' => 'Disk',
                'value' => $diskUsedPct !== null ? $diskUsedPct.'%' : 'N/A',
                'pct' => $diskUsedPct ?? 0,
                'color' => $diskUsedPct !== null && $diskUsedPct > 85 ? '#ef4444' : '#3b82f6',
                'ok' => $diskUsedPct === null || $diskUsedPct < 90,
            ],
            [
                'label' => 'Heartbeats',
                'value' => $monitoringAt ? 'OK' : 'N/A',
                'pct' => $monitoringAt && $monitoringAt->gt(now()->subMinutes(15)) ? 92 : ($monitoringAt ? 40 : 0),
                'color' => '#006243',
                'ok' => $monitoringAt !== null && $monitoringAt->gt(now()->subHour()),
            ],
        ];

        $metrics = [
            $this->row('Application', 'PHP '.PHP_VERSION, true, now()->toDateTimeString()),
            $this->row('Laravel', app()->version(), true, now()->toDateTimeString()),
            $this->row(
                'Database',
                $dbSizeMb !== null ? number_format($dbSizeMb, 1).' MB' : 'Not Available',
                $dbSizeMb !== null,
                now()->toDateTimeString()
            ),
            $this->row(
                'Cache',
                $cacheOk === null ? 'Not Available' : ($cacheOk ? 'Healthy' : 'Failing'),
                $cacheOk === true,
                now()->toDateTimeString(),
                $cacheOk === false
            ),
            $this->row(
                'Queue',
                config('queue.default') === 'sync' ? 'N/A (sync)' : (string) config('queue.default'),
                true,
                now()->toDateTimeString(),
                false,
                config('queue.default') === 'sync'
            ),
            $this->row(
                'Failed jobs',
                $failedJobs === null ? 'Not Available' : (string) $failedJobs,
                ($failedJobs ?? 0) === 0,
                now()->toDateTimeString(),
                ($failedJobs ?? 0) > 0
            ),
            $this->row(
                'Mail',
                (string) config('mail.default'),
                true,
                now()->toDateTimeString(),
                false,
                true
            ),
            $this->row(
                'Cron / schedule',
                $scheduleAt?->diffForHumans() ?? 'Not Available',
                $scheduleAt !== null && $scheduleAt->gt(now()->subHour()),
                $scheduleAt?->toDateTimeString()
            ),
            $this->row(
                'Monitoring sync',
                $monitoringAt?->diffForHumans() ?? 'Not Available',
                $monitoringAt !== null && $monitoringAt->gt(now()->subMinutes(20)),
                $monitoringAt?->toDateTimeString()
            ),
            $this->row(
                'Google Analytics',
                $ga->enabled ? ucfirst((string) $ga->status) : 'Disabled',
                $ga->enabled && $ga->status === 'connected',
                $ga->updated_at?->toDateTimeString(),
                false,
                ! $ga->enabled
            ),
            $this->row(
                'Microsoft Clarity',
                $clarity?->enabled ? ucfirst((string) $clarity->status) : 'Not Available',
                (bool) ($clarity?->enabled && $clarity->status === 'connected'),
                $clarity?->updated_at?->toDateTimeString(),
                false,
                ! $clarity
            ),
            $this->row(
                'Disk free',
                $diskFree > 0 ? round($diskFree / 1073741824, 2).' GB' : 'Not Available',
                $diskUsedPct === null || $diskUsedPct < 90,
                now()->toDateTimeString()
            ),
        ];

        return [
            'rings' => $rings,
            'metrics' => $metrics,
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @return array{label: string, value: string, ok: bool, checked_at: ?string, alert: bool, na: bool}
     */
    private function row(
        string $label,
        string $value,
        bool $ok,
        ?string $checkedAt = null,
        bool $alert = false,
        bool $na = false,
    ): array {
        return [
            'label' => $label,
            'value' => $value,
            'ok' => $ok,
            'checked_at' => $checkedAt,
            'alert' => $alert,
            'na' => $na || str_contains(strtolower($value), 'not available') || str_starts_with($value, 'N/A'),
        ];
    }

    private function cacheHealthy(): ?bool
    {
        try {
            Cache::put('monitoring.ping', 'ok', 60);

            return Cache::get('monitoring.ping') === 'ok';
        } catch (\Throwable) {
            return null;
        }
    }

    private function failedJobsCount(): ?int
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return null;
            }

            return (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return null;
        }
    }

    private function databaseSizeMb(): ?float
    {
        try {
            if (DB::getDriverName() !== 'mysql') {
                return null;
            }
            $database = DB::getDatabaseName();
            $row = DB::selectOne(
                'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                 FROM information_schema.tables WHERE table_schema = ?',
                [$database]
            );

            return isset($row->size_mb) ? (float) $row->size_mb : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
