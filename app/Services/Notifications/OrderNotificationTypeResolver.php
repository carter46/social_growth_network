<?php

namespace App\Services\Notifications;

use App\Models\Order;
use App\Models\OrderItem;

class OrderNotificationTypeResolver
{
    public function resolve(Order $order): string
    {
        $order->loadMissing('items');

        $hasDomain = false;
        $hasWebsite = false;

        foreach ($order->items as $item) {
            if ($this->isDomainItem($item)) {
                $hasDomain = true;
            }
            if ($this->isWebsiteItem($item)) {
                $hasWebsite = true;
            }
        }

        if ($hasDomain && ! $hasWebsite) {
            return 'order.domain_purchased';
        }

        if ($hasWebsite && ! $hasDomain) {
            return 'order.website_purchased';
        }

        if ($hasDomain || $hasWebsite) {
            return 'order.completed';
        }

        return 'order.completed';
    }

    private function isDomainItem(OrderItem $item): bool
    {
        $options = $item->options ?? [];

        if (($options['domain_mode'] ?? '') === 'buy') {
            return true;
        }

        if (filled($options['domain_quote_id'] ?? null)) {
            return true;
        }

        return false;
    }

    private function isWebsiteItem(OrderItem $item): bool
    {
        return false;
    }
}
