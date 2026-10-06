<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\Order;
use App\Models\ReferralCommission;
use App\Models\User;
use App\Services\Referrals\ReferralCommissionService;

class CreditReferralCommissionForOrder
{
    public function __construct(
        private ReferralCommissionService $commissions,
    ) {}

    public function handle(OrderCompleted $event): void
    {
        try {
            $order = Order::query()->find($event->orderId);

            if (! $order || $order->status !== 'paid' || (float) $order->total_amount <= 0) {
                return;
            }

            $buyer = User::query()->find($order->user_id);

            if (! $buyer || ! $buyer->referred_by_id || ! $buyer->isCreator()) {
                return;
            }

            $this->commissions->record(
                $buyer,
                ReferralCommission::KIND_CREATOR_SPEND,
                $order,
                (string) $order->total_amount,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
