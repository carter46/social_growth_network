<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CampaignFulfillmentService
{
    public function createFromPaidOrder(Order $order): void
    {
        $order->loadMissing('items');

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $this->createFromOrderItem($order, $item);
            }
        });
    }

    private function createFromOrderItem(Order $order, OrderItem $item): void
    {
        if ($item->item_type !== 'platform_product' || ! $item->item_id) {
            return;
        }

        if (Campaign::query()->where('order_item_id', $item->id)->exists()) {
            return;
        }

        $product = PlatformProduct::query()->find($item->item_id);
        if (! $product || ! $this->productIsCampaign($product)) {
            return;
        }

        $options = $item->options ?? [];
        $agentReward = $product->agent_reward_per_completion;
        if ($agentReward === null) {
            $agentReward = 0;
        }

        Campaign::query()->create([
            'creator_id' => $order->user_id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'platform_product_id' => $product->id,
            'platform_product_variant_id' => $item->platform_product_variant_id,
            'title' => $product->title,
            'target_url' => $options['target_url'] ?? $options['campaign_url'] ?? null,
            'quantity' => max(1, (int) $item->quantity),
            'completed_count' => 0,
            'locked_creator_price' => $item->unit_price,
            'locked_agent_reward' => $agentReward,
            'status' => Campaign::STATUS_ACTIVE,
            'estimated_minutes' => $product->estimated_minutes,
            'meta' => [
                'product_title' => $product->title,
                'variant_id' => $item->platform_product_variant_id,
                'options' => $options,
            ],
        ]);
    }

    private function productIsCampaign(PlatformProduct $product): bool
    {
        // Opt-in only: missing column or unset flag must not auto-create campaigns.
        if (! Schema::hasColumn('platform_products', 'is_campaign')) {
            return false;
        }

        return (bool) $product->is_campaign;
    }
}
