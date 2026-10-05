<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Order;
use App\Services\Campaigns\CampaignFulfillmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CampaignsBackfillFromOrders extends Command
{
    protected $signature = 'campaigns:backfill-from-orders';

    protected $description = 'Idempotently create campaigns for paid platform orders that have none';

    public function handle(CampaignFulfillmentService $fulfillment): int
    {
        if (! Schema::hasTable('campaigns')) {
            $this->error('Campaigns table missing. Run migrations first.');

            return self::FAILURE;
        }

        $before = Campaign::query()->count();

        Order::query()
            ->where('source', 'platform')
            ->where('status', 'paid')
            ->whereHas('items', fn ($q) => $q->where('item_type', 'platform_product'))
            ->with('items.variant')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($fulfillment) {
                foreach ($orders as $order) {
                    $fulfillment->createFromPaidOrder($order);
                }
            });

        $this->info('Campaigns created: '.(Campaign::query()->count() - $before));

        return self::SUCCESS;
    }
}
