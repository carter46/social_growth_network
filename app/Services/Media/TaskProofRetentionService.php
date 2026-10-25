<?php

namespace App\Services\Media;

use App\Models\AgentSocialFollowProof;
use App\Models\AgentSocialFollowSubmission;
use App\Models\CampaignParticipation;
use App\Models\CampaignParticipationTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Uploaded task screenshots (campaign proofs + platform follow proofs) are kept
 * for 7 days after approval, then deleted from the public disk.
 * External URLs (e.g. YouTube comment links) are left alone.
 */
class TaskProofRetentionService
{
    public const RETENTION_DAYS = 7;

    /** @var list<string> */
    private const LOCAL_PREFIXES = [
        'task-proofs/',
        'social-follow-proofs/',
    ];

    public function retentionEndsAt(?CarbonImmutable $from = null): CarbonImmutable
    {
        return ($from ?? CarbonImmutable::now())->addDays(self::RETENTION_DAYS);
    }

    public function isLocalProofUrl(?string $url): bool
    {
        return $this->relativePathFromUrl($url) !== null;
    }

    public function relativePathFromUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $path = $url;
        if (preg_match('#^https?://#i', $url)) {
            $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        foreach (self::LOCAL_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $path;
            }
        }

        // Relative disk path without /storage prefix.
        foreach (self::LOCAL_PREFIXES as $prefix) {
            if (str_starts_with(ltrim($url, '/'), $prefix)) {
                return ltrim(str_replace('\\', '/', $url), '/');
            }
        }

        return null;
    }

    public function scheduleForParticipation(CampaignParticipation $participation): void
    {
        if (! Schema::hasColumn('campaign_participations', 'proof_purge_at')) {
            return;
        }

        $at = $this->retentionEndsAt();

        if ($this->isLocalProofUrl($participation->proof_url) && blank($participation->proof_purge_at)) {
            $participation->forceFill(['proof_purge_at' => $at])->save();
        }

        if (! Schema::hasColumn('campaign_participation_tasks', 'proof_purge_at')) {
            return;
        }

        CampaignParticipationTask::query()
            ->where('participation_id', $participation->id)
            ->whereNotNull('proof_url')
            ->whereNull('proof_purge_at')
            ->orderBy('id')
            ->each(function (CampaignParticipationTask $step) use ($at) {
                if ($this->isLocalProofUrl($step->proof_url)) {
                    $step->forceFill(['proof_purge_at' => $at])->save();
                }
            });
    }

    public function scheduleForSocialFollow(AgentSocialFollowSubmission $submission): void
    {
        if (! Schema::hasColumn('agent_social_follow_proofs', 'proof_purge_at')) {
            return;
        }

        $at = $this->retentionEndsAt();

        $submission->proofs()
            ->whereNotNull('proof_url')
            ->whereNull('proof_purge_at')
            ->orderBy('id')
            ->each(function (AgentSocialFollowProof $proof) use ($at) {
                if ($this->isLocalProofUrl($proof->proof_url)) {
                    $proof->forceFill(['proof_purge_at' => $at])->save();
                }
            });
    }

    /** Delete due proof files and clear their URLs. Returns number of files removed. */
    public function purgeDue(int $limit = 200): int
    {
        $removed = 0;
        $now = now();

        if (Schema::hasColumn('campaign_participations', 'proof_purge_at')) {
            CampaignParticipation::query()
                ->whereNotNull('proof_purge_at')
                ->where('proof_purge_at', '<=', $now)
                ->whereNotNull('proof_url')
                ->orderBy('proof_purge_at')
                ->limit($limit)
                ->get()
                ->each(function (CampaignParticipation $row) use (&$removed) {
                    if ($this->deleteStoredProof($row->proof_url)) {
                        $removed++;
                    }
                    $row->forceFill(['proof_url' => null, 'proof_purge_at' => null])->save();
                });
        }

        if (Schema::hasColumn('campaign_participation_tasks', 'proof_purge_at')) {
            CampaignParticipationTask::query()
                ->whereNotNull('proof_purge_at')
                ->where('proof_purge_at', '<=', $now)
                ->whereNotNull('proof_url')
                ->orderBy('proof_purge_at')
                ->limit($limit)
                ->get()
                ->each(function (CampaignParticipationTask $row) use (&$removed) {
                    if ($this->deleteStoredProof($row->proof_url)) {
                        $removed++;
                    }
                    $row->forceFill(['proof_url' => null, 'proof_purge_at' => null])->save();
                });
        }

        if (Schema::hasColumn('agent_social_follow_proofs', 'proof_purge_at')) {
            AgentSocialFollowProof::query()
                ->whereNotNull('proof_purge_at')
                ->where('proof_purge_at', '<=', $now)
                ->whereNotNull('proof_url')
                ->orderBy('proof_purge_at')
                ->limit($limit)
                ->get()
                ->each(function (AgentSocialFollowProof $row) use (&$removed) {
                    if ($this->deleteStoredProof($row->proof_url)) {
                        $removed++;
                    }
                    $row->forceFill(['proof_url' => null, 'proof_purge_at' => null])->save();
                });
        }

        return $removed;
    }

    private function deleteStoredProof(?string $url): bool
    {
        $relative = $this->relativePathFromUrl($url);
        if ($relative === null) {
            return false;
        }

        try {
            if (Storage::disk('public')->exists($relative)) {
                return Storage::disk('public')->delete($relative);
            }
        } catch (\Throwable $e) {
            Log::warning('task_proof.purge_failed', ['path' => $relative, 'error' => $e->getMessage()]);
        }

        return false;
    }
}
