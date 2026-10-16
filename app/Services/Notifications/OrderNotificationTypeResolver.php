<?php

namespace App\Services\Notifications;

use App\Models\Order;

class OrderNotificationTypeResolver
{
    public function resolve(Order $order): string
    {
        return 'order.completed';
    }
}
