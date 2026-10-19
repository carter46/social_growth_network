<?php

namespace App\Services\Referrals;

use App\Models\AgentInviteCode;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary admin-issued invite codes for agent signup when no agent exist yet.
 * Valid for 24 hours, create no referral downline, then deleted.
 */
class AgentInviteCodeService
{
    public const LIFETIME_HOURS = 24;

    public function __construct(
        private readonly ReferralCodeGenerator $codes,
    ) {}

    public function pruneExpired(): int
    {
        if (! Schema::hasTable('agent_invite_codes')) {
            return 0;
        }

        return AgentInviteCode::query()->where('expires_at', '<=', now())->delete();
    }

    public function findActive(?string $code): ?AgentInviteCode
    {
        $code = ReferralCodeGenerator::normalize($code);

        if ($code === '' || ! Schema::hasTable('agent_invite_codes')) {
            return null;
        }

        $this->pruneExpired();

        return AgentInviteCode::query()->active()->where('code', $code)->first();
    }

    public function current(): ?AgentInviteCode
    {
        if (! Schema::hasTable('agent_invite_codes')) {
            return null;
        }

        $this->pruneExpired();

        return AgentInviteCode::query()->active()->latest('id')->first();
    }

    public function issue(User $admin): AgentInviteCode
    {
        $this->pruneExpired();

        // One active bootstrap code at a time keeps the settings page simple.
        AgentInviteCode::query()->active()->delete();

        return AgentInviteCode::query()->create([
            'code' => $this->uniqueCode(),
            'created_by' => $admin->id,
            'expires_at' => now()->addHours(self::LIFETIME_HOURS),
        ]);
    }

    public function revoke(?AgentInviteCode $invite = null): void
    {
        if (! Schema::hasTable('agent_invite_codes')) {
            return;
        }

        if ($invite) {
            $invite->delete();

            return;
        }

        AgentInviteCode::query()->delete();
    }

    private function uniqueCode(): string
    {
        return $this->codes->generate();
    }
}
