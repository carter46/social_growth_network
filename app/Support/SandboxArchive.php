<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Sandbox-era deposits/orders tagged by wallets:purge-sandbox-credits --archive.
 * Admin money lists hide them unless ?sandbox=1.
 */
final class SandboxArchive
{
    public static function includeSandbox(Request $request): bool
    {
        return $request->boolean('sandbox');
    }

    public static function excludePurgedFundings(Builder $query, Request $request): Builder
    {
        if (self::includeSandbox($request)) {
            return $query;
        }

        return $query->where(function (Builder $q) {
            $q->whereNull('metadata->sandbox_purged_at');
        });
    }

    public static function excludePurgedOrders(Builder $query, Request $request): Builder
    {
        if (self::includeSandbox($request)) {
            return $query;
        }

        return $query->where(function (Builder $q) {
            $q->whereNull('payment_metadata->sandbox_purged_at');
        });
    }

    public static function excludePurgedTransactions(Builder $query, Request $request): Builder
    {
        if (self::includeSandbox($request)) {
            return $query;
        }

        return $query
            ->where(function (Builder $q) {
                $q->whereNull('description')
                    ->orWhere('description', 'not like', 'Sandbox purge%');
            })
            ->where(function (Builder $q) {
                $q->whereNull('wallet_funding_id')
                    ->orWhereHas('walletFunding', fn (Builder $f) => $f->whereNull('metadata->sandbox_purged_at'));
            })
            ->where(function (Builder $q) {
                $q->whereNull('order_id')
                    ->orWhereHas('order', fn (Builder $o) => $o->whereNull('payment_metadata->sandbox_purged_at'));
            });
    }
}
