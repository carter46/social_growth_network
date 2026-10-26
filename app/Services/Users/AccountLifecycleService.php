<?php

namespace App\Services\Users;

use App\Models\AuditLog;
use App\Models\CampaignParticipation;
use App\Models\PurgedUserRecord;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletFunding;
use App\Models\Withdrawal;
use App\Modules\Admin\Services\AuditLogService;
use App\Services\Campaigns\CampaignAccountHoldService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AccountLifecycleService
{
    public function __construct(
        private CampaignAccountHoldService $campaignHolds,
        private AuditLogService $audit,
    ) {}

    public function suspendToTrashDays(): int
    {
        return max(1, (int) config('user-lifecycle.suspend_to_trash_days', 30));
    }

    public function trashToPurgeDays(): int
    {
        return max(1, (int) config('user-lifecycle.trash_to_purge_days', 180));
    }

    /**
     * Move long-suspended (non-trashed) accounts into trash.
     *
     * @return array{trashed: int, skipped: int}
     */
    public function trashExpiredSuspensions(?int $limit = null): array
    {
        $limit = $limit ?? (int) config('user-lifecycle.trash_batch', 100);
        $cutoff = now()->subDays($this->suspendToTrashDays());
        $trashed = 0;
        $skipped = 0;

        User::query()
            ->where('is_suspended', true)
            ->whereNotNull('suspended_at')
            ->where('suspended_at', '<=', $cutoff)
            ->whereNull('deleted_at')
            ->whereNull('anonymized_at')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (User $user) use (&$trashed, &$skipped) {
                try {
                    if ($user->trashed() || $user->anonymized_at !== null || ! $user->is_suspended) {
                        $skipped++;

                        return;
                    }

                    $this->moveToTrash($user, null, 'auto_after_suspension');
                    $trashed++;
                } catch (\Throwable $e) {
                    $skipped++;
                    Log::warning('user_lifecycle.auto_trash_failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return ['trashed' => $trashed, 'skipped' => $skipped];
    }

    /**
     * Soft-delete a member account (admin or automated). Preserves suspended_at.
     */
    public function moveToTrash(User $user, ?int $actorId, string $reason): void
    {
        if ($user->hasRole('admin')) {
            throw new \InvalidArgumentException('Administrators cannot be moved to trash.');
        }
        if ($user->trashed()) {
            return;
        }

        $oldEmail = $user->email;

        if (! $user->is_suspended) {
            $user->suspend($actorId);
            $user->refresh();
        }

        $held = $this->campaignHolds->holdForCreator(
            $user,
            CampaignAccountHoldService::REASON_TRASHED,
            $actorId,
            null,
        );

        $user->delete();

        $this->audit->log($actorId, 'user.trashed', $user, [
            'email' => $oldEmail,
        ], [
            'user_id' => $user->id,
            'campaigns_held' => $held,
            'reason' => $reason,
        ], null, [
            'actor_type' => $actorId ? 'admin' : 'system',
            'reason' => $reason,
        ]);
    }

    /**
     * Write historical record then anonymize operational account for expired trash.
     * Leaves a soft-deleted anonymized tombstone so wallet/transaction FKs are not cascaded.
     *
     * @return array{purged: int, skipped: int}
     */
    public function purgeExpiredTrash(?int $limit = null): array
    {
        $limit = $limit ?? (int) config('user-lifecycle.purge_batch', 50);
        $cutoff = now()->subDays($this->trashToPurgeDays());
        $purged = 0;
        $skipped = 0;

        User::onlyTrashed()
            ->whereNull('anonymized_at')
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<=', $cutoff)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
            ->whereNotIn('id', PurgedUserRecord::query()->select('original_user_id'))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (User $user) use (&$purged, &$skipped) {
                try {
                    $this->purgeTrashedUser($user, 'auto_trash_retention', 'users:purge-expired-trash');
                    $purged++;
                } catch (\Throwable $e) {
                    $skipped++;
                    Log::warning('user_lifecycle.auto_purge_failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return ['purged' => $purged, 'skipped' => $skipped];
    }

    /**
     * Atomic purge: lock → recheck → historical snapshot → verify → anonymize tombstone.
     * Never forceDeletes — wallets/transactions cascade on hard delete.
     */
    public function purgeTrashedUser(
        User $user,
        string $reason,
        string $process,
        ?int $actorId = null,
    ): PurgedUserRecord {
        return DB::transaction(function () use ($user, $reason, $process, $actorId) {
            $locked = User::onlyTrashed()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new \RuntimeException('Account is no longer in trash.');
            }
            if ($locked->anonymized_at !== null) {
                throw new \RuntimeException('Account has already been permanently removed.');
            }
            if ($locked->hasRole('admin')) {
                throw new \InvalidArgumentException('Administrators cannot be purged.');
            }
            if (PurgedUserRecord::query()->where('original_user_id', $locked->id)->exists()) {
                throw new \RuntimeException('A historical record already exists for this account.');
            }

            $userId = (int) $locked->id;
            $emailBefore = (string) $locked->email;

            $record = $this->buildHistoricalRecord($locked, $reason, $process);

            $verified = PurgedUserRecord::query()->whereKey($record->id)->first();
            if (
                ! $verified
                || (int) $verified->original_user_id !== $userId
                || (string) $verified->email !== $emailBefore
            ) {
                throw new \RuntimeException('Historical snapshot verification failed.');
            }

            // Scrub PII on the operational row but do not forceDelete: wallet / transaction
            // FKs cascade on hard delete, and the ledger must remain for reconciliation.
            $ok = $locked->anonymize($actorId);
            if (! $ok) {
                throw new \RuntimeException('Could not anonymize account before purge.');
            }

            $locked->refresh();
            if (! $locked->trashed()) {
                $locked->delete();
                $locked->refresh();
            }
            if ($locked->anonymized_at === null) {
                throw new \RuntimeException('Tombstone state was not applied.');
            }

            $this->audit->log($actorId, 'user.purged', null, [
                'email' => $verified->email,
                'name' => $verified->name,
                'original_user_id' => $verified->original_user_id,
            ], [
                'purged_user_record_id' => $verified->id,
                'original_user_id' => $userId,
                'reason' => $reason,
                'process' => $process,
            ], null, [
                'actor_id' => $actorId,
                'actor_type' => $actorId ? 'admin' : 'system',
                'reason' => $reason,
                'model_type' => PurgedUserRecord::class,
                'model_id' => $verified->id,
            ]);

            return $verified->fresh() ?? $verified;
        });
    }

    public function buildHistoricalRecord(User $user, string $reason, string $process): PurgedUserRecord
    {
        $user->loadMissing(['wallet', 'roles']);

        $role = $user->hasRole('agent') ? 'agent' : ($user->hasRole('user') ? 'user' : ($user->roles->first()?->name));

        $wallet = $user->wallet;
        $transactionRefs = $this->collectTransactionRefs($user);
        $loginSecurityRefs = $this->collectLoginSecurityRefs($user);

        return PurgedUserRecord::query()->create([
            'original_user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'username' => $user->username,
            'phone' => $user->phone,
            'role' => $role,
            'registered_at' => $user->created_at,
            'suspended_at' => $user->suspended_at,
            'trashed_at' => $user->deleted_at,
            'purged_at' => now(),
            'wallet_balance_at_purge' => $wallet?->balance,
            'wallet_locked_at_purge' => $wallet?->locked_balance,
            'transaction_refs' => $transactionRefs,
            'login_security_refs' => $loginSecurityRefs,
            'snapshot' => [
                'country' => $user->country,
                'is_suspended' => (bool) $user->is_suspended,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ],
            'purge_reason' => $reason,
            'purge_process' => $process,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectTransactionRefs(User $user): array
    {
        $refs = [
            'wallet_id' => $user->wallet?->id,
            'transaction_ids' => [],
            'funding_ids' => [],
            'withdrawal_ids' => [],
            'campaign_participation_ids' => [],
            'counts' => [],
        ];

        if (Schema::hasTable('transactions')) {
            $refs['transaction_ids'] = Transaction::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(200)
                ->pluck('id')
                ->all();
            $refs['counts']['transactions'] = Transaction::query()->where('user_id', $user->id)->count();
        }

        if (Schema::hasTable('wallet_fundings')) {
            $refs['funding_ids'] = WalletFunding::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(100)
                ->pluck('id')
                ->all();
            $refs['counts']['fundings'] = WalletFunding::query()->where('user_id', $user->id)->count();
        }

        if (Schema::hasTable('withdrawals')) {
            $refs['withdrawal_ids'] = Withdrawal::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(100)
                ->pluck('id')
                ->all();
            $refs['counts']['withdrawals'] = Withdrawal::query()->where('user_id', $user->id)->count();
        }

        if (Schema::hasTable('campaign_participations')) {
            $q = CampaignParticipation::query()->where('agent_id', $user->id);
            $refs['campaign_participation_ids'] = (clone $q)->orderByDesc('id')->limit(100)->pluck('id')->all();
            $refs['counts']['campaign_participations'] = $q->count();
        }

        return $refs;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectLoginSecurityRefs(User $user): array
    {
        $refs = ['audit_log_ids' => [], 'counts' => []];

        if (! Schema::hasTable('audit_logs')) {
            return $refs;
        }

        $query = AuditLog::query()
            ->where(function ($q) use ($user) {
                $q->where('actor_id', $user->id)
                    ->orWhere(function ($inner) use ($user) {
                        $inner->where('model_type', User::class)->where('model_id', $user->id);
                    });
            })
            ->where(function ($q) {
                $q->where('action', 'like', 'user.%')
                    ->orWhere('action', 'like', '%login%')
                    ->orWhere('action', 'like', '%google%');
            });

        $refs['audit_log_ids'] = (clone $query)->orderByDesc('id')->limit(50)->pluck('id')->all();
        $refs['counts']['audit_logs'] = $query->count();

        return $refs;
    }
}
