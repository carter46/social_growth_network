<?php

namespace App\Support\Demo;

use App\Models\DemoBatch;
use App\Models\DemoBatchRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoBatchTracker
{
    private ?DemoBatch $batch = null;

    /** @var array<string, true> */
    private array $seen = [];

    public function start(string $name, string $source = 'demo:seed'): DemoBatch
    {
        $this->batch = DemoBatch::query()->create([
            'name' => $name,
            'source' => $source,
        ]);
        $this->seen = [];

        return $this->batch;
    }

    public function batch(): ?DemoBatch
    {
        return $this->batch;
    }

    public function attach(DemoBatch $batch): void
    {
        $this->batch = $batch;
        $this->seen = [];
    }

    public function track(Model $model): void
    {
        if (! $this->batch || ! $model->getKey()) {
            return;
        }

        $type = $model::class;
        $id = (int) $model->getKey();
        $key = $type.'#'.$id;
        if (isset($this->seen[$key])) {
            return;
        }
        $this->seen[$key] = true;

        DemoBatchRecord::query()->firstOrCreate(
            [
                'demo_batch_id' => $this->batch->id,
                'record_type' => $type,
                'record_id' => $id,
            ]
        );
    }

    /**
     * Delete tracked demo rows in FK-safe order. Leaves roles, permissions,
     * catalog, system settings, and non-demo admins intact.
     *
     * @return array{batches: int, records: int}
     */
    public function clearAllActiveBatches(): array
    {
        $batches = DemoBatch::query()->active()->orderBy('id')->get();
        $recordsDeleted = 0;

        foreach ($batches as $batch) {
            $recordsDeleted += $this->clearBatch($batch);
        }

        return [
            'batches' => $batches->count(),
            'records' => $recordsDeleted,
        ];
    }

    public function clearBatch(DemoBatch $batch): int
    {
        $deleted = 0;

        // Children / dependents first, users last.
        $order = [
            \App\Models\SupportTicketReply::class,
            \App\Models\SupportTicket::class,
            \App\Models\UserNotification::class,
            \App\Models\UserActivity::class,
            \App\Models\Favorite::class,
            \App\Models\OrderItem::class,
            \App\Models\Transaction::class,
            \App\Models\Order::class,
            \App\Models\KycSubmission::class,
            \App\Models\WalletFunding::class,
            \App\Models\Withdrawal::class,
            \App\Models\AuditLog::class,
            \App\Models\AnalyticsKpiSnapshot::class,
            \App\Models\ProductMetricDaily::class,
            \App\Models\Wallet::class,
            \App\Models\User::class,
        ];

        DB::transaction(function () use ($batch, $order, &$deleted) {
            $demoUserIds = DemoBatchRecord::query()
                ->where('demo_batch_id', $batch->id)
                ->where('record_type', \App\Models\User::class)
                ->pluck('record_id')
                ->all();

            if ($demoUserIds !== [] && Schema::hasTable('user_activities')) {
                $deleted += \App\Models\UserActivity::query()->whereIn('user_id', $demoUserIds)->delete();
            }

            if ($demoUserIds !== [] && Schema::hasTable('product_metric_daily')) {
                $dims = array_map('strval', $demoUserIds);
                $deleted += \App\Models\ProductMetricDaily::query()->whereIn('dimension', $dims)->delete();
            }

            if (Schema::hasTable('analytics_kpi_snapshots')) {
                $deleted += \App\Models\AnalyticsKpiSnapshot::query()
                    ->where('meta->demo', true)
                    ->delete();
            }

            foreach ($order as $type) {
                $ids = DemoBatchRecord::query()
                    ->where('demo_batch_id', $batch->id)
                    ->where('record_type', $type)
                    ->pluck('record_id')
                    ->all();

                if ($ids === []) {
                    continue;
                }

                if (! class_exists($type) || ! Schema::hasTable((new $type)->getTable())) {
                    continue;
                }

                // Soft-deleted models if needed
                if (method_exists($type, 'withTrashed') && $type !== \App\Models\User::class) {
                    $deleted += $type::withTrashed()->whereIn('id', $ids)->forceDelete();
                } elseif ($type === \App\Models\User::class) {
                    $users = $type::query()->whereIn('id', $ids)->get();
                    foreach ($users as $user) {
                        $user->roles()->detach();
                        $user->permissions()->detach();
                        $user->delete();
                        $deleted++;
                    }
                } else {
                    $deleted += $type::query()->whereIn('id', $ids)->delete();
                }
            }

            DemoBatchRecord::query()->where('demo_batch_id', $batch->id)->delete();
            $batch->forceFill(['cleared_at' => now()])->save();
        });

        return $deleted;
    }
}
