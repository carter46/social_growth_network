<?php

namespace App\Console\Commands;

use App\Models\CampaignBudget;
use App\Models\User;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Campaigns\CampaignBudgetService;
use App\Support\Money\Money;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Unlock platform-wallet campaign holds that came from sandbox-era test purchases.
 *
 * Unlike a normal cancel, creators are NOT refunded (that would put fake money back
 * into their wallets). Default is dry-run.
 */
class PurgeSandboxCampaignHoldsCommand extends Command
{
    protected $signature = 'campaigns:purge-sandbox-holds
        {--before= : Cutover datetime (Y-m-d). Campaigns created at/before this are treated as sandbox}
        {--execute : Apply changes (default is dry-run)}
        {--claw-platform : Also debit the platform wallet by each unlocked amount (removes fake revenue)}
        {--admin= : Admin user id for ledger attribution}';

    protected $description = 'Cancel sandbox-era campaigns and release held agent budgets without refunding creators';

    public function handle(CampaignBudgetService $budgets, WalletService $wallets): int
    {
        if (! CampaignBudgetService::enabled()) {
            $this->warn('Campaign budgets table is not available.');

            return self::SUCCESS;
        }

        $beforeRaw = (string) $this->option('before');
        if ($beforeRaw === '') {
            $this->error('Pass --before=YYYY-MM-DD (same go-live date as wallets:purge-sandbox-credits).');

            return self::FAILURE;
        }

        try {
            $before = Carbon::parse($beforeRaw)->endOfDay();
        } catch (\Throwable) {
            $this->error('Could not parse --before. Use Y-m-d or an ISO datetime.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $clawPlatform = (bool) $this->option('claw-platform');
        $admin = $this->resolveAdmin();
        if ($clawPlatform && ! $admin) {
            $this->error('No admin user found for --claw-platform. Pass --admin=<id>.');

            return self::FAILURE;
        }

        $rows = CampaignBudget::query()
            ->with('campaign')
            ->where('status', CampaignBudget::STATUS_HELD)
            ->whereHas('campaign', fn ($q) => $q->where('created_at', '<=', $before))
            ->orderBy('campaign_id')
            ->get();

        $this->info(($execute ? 'EXECUTE' : 'DRY RUN').' — sandbox campaign holds at/before '.$before->toDateTimeString());
        if ($rows->isEmpty()) {
            $this->warn('No held sandbox-era campaign budgets matched.');

            return self::SUCCESS;
        }

        $table = [];
        $totalRelease = '0.0000';
        $done = 0;
        $failed = 0;

        foreach ($rows as $budget) {
            $campaign = $budget->campaign;
            if (! $campaign) {
                continue;
            }
            $remaining = $budget->remaining();
            $action = $execute ? 'voided' : 'would void';
            $note = 'cancel + unlock; no creator refund';

            if ($execute) {
                try {
                    $released = $budgets->voidSandboxHold($campaign);
                    $action = 'voided';
                    $remaining = $released;
                    $done++;
                    if ($clawPlatform && Money::isPositive($released, 4)) {
                        $wallets->adminAdjust(
                            $wallets->getPlatformWallet(),
                            Money::sub('0', $released, 4),
                            'Sandbox campaign hold purge #'.$campaign->id,
                            $admin->id,
                        );
                        $note = 'unlocked + platform clawed';
                    }
                    Log::channel('financial')->info('sandbox_campaign_hold.voided', [
                        'campaign_id' => $campaign->id,
                        'released' => $released,
                        'claw_platform' => $clawPlatform,
                    ]);
                } catch (\Throwable $e) {
                    $action = 'failed';
                    $note = $e->getMessage();
                    $failed++;
                }
            }

            $totalRelease = Money::add($totalRelease, $remaining, 4);
            $table[] = [
                $campaign->id,
                mb_substr((string) $campaign->title, 0, 40),
                $campaign->status,
                $remaining,
                $action,
                $note,
            ];
        }

        $this->table(
            ['Campaign', 'Title', 'Status', 'Remaining held', 'Action', 'Note'],
            $table,
        );
        $this->line('Total held matched: '.Money::round($totalRelease, 2)
            .($execute ? " — voided {$done}, failed {$failed}." : ' (dry-run, nothing written).'));

        if ($execute) {
            \App\Support\SandboxCutover::remember($before);
            $this->line('Analytics cutover saved: sandbox orders at/before '.$before->toDateString().' are excluded from revenue.');
        }

        if (! $execute) {
            $this->comment('Re-run with --execute to apply. Add --claw-platform to also remove the unlocked amount from the platform wallet balance.');
        }

        return $failed > 0 && $execute ? self::FAILURE : self::SUCCESS;
    }

    private function resolveAdmin(): ?User
    {
        $adminId = $this->option('admin');
        if ($adminId !== null && $adminId !== '') {
            return User::query()->find((int) $adminId);
        }

        return User::query()->role('admin')->orderBy('id')->first();
    }
}
