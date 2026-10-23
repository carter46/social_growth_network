<?php

namespace App\Support;

use App\Models\SystemSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Go-live cutover used to keep sandbox-era money out of live analytics.
 * Set automatically by wallets:purge-sandbox-credits / campaigns:purge-sandbox-holds --execute.
 */
final class SandboxCutover
{
    public const SETTING = 'sandbox_cutover_at';

    public static function at(): ?CarbonInterface
    {
        $raw = SystemSetting::get(self::SETTING);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function remember(CarbonInterface|string $before): void
    {
        $at = $before instanceof CarbonInterface ? $before->copy() : Carbon::parse((string) $before);
        SystemSetting::set(self::SETTING, $at->toDateString());
    }

    /** Orders at/before cutover (or tagged sandbox_purged) are excluded from live revenue/GMV. */
    public static function excludeSandboxOrders($query): void
    {
        $cutover = self::at();

        $query->where(function ($q) use ($cutover) {
            $q->whereNull('payment_metadata->sandbox_purged_at');
            if ($cutover) {
                $q->where('created_at', '>', $cutover);
            }
        });
    }
}
