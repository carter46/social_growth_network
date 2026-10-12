<?php

namespace App\Services\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Services\Branding\SiteBrandingRepository;
use App\Services\Communications\Contact\PlatformContactRepository;

class NotificationEmailRenderer
{
    public function __construct(
        private SiteBrandingRepository $branding,
        private PlatformContactRepository $contact,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderAdmin(NotificationMessage $message, User $recipient, array $context = []): string
    {
        return view('emails.layouts.admin', [
            'message' => $message,
            'notifiable' => $recipient,
            'branding' => $this->branding->all(),
            'context' => $context,
        ])->render();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderUser(NotificationMessage $message, User $recipient, array $context = []): string
    {
        return view('emails.layouts.user', [
            'message' => $message,
            'notifiable' => $recipient,
            'branding' => $this->branding->all(),
            'contact' => $this->contact->all(),
            'context' => $context,
        ])->render();
    }

    public function orderContext(Order $order): array
    {
        $order->loadMissing(['items', 'user']);

        $lines = $order->items->map(function ($item) {
            $title = $item->options['product_title'] ?? $item->options['domain_fqdn'] ?? 'Item';

            return [
                'title' => (string) $title,
                'quantity' => (int) $item->quantity,
                'line_total' => (float) $item->line_total,
            ];
        })->values()->all();

        return [
            'order_reference' => $order->reference,
            'buyer_name' => $order->user?->name,
            'buyer_email' => $order->user?->email,
            'total_amount' => (float) ($order->total_amount ?? $order->amount),
            'currency' => $order->currency ?: config('platform.currency'),
            'lines' => $lines,
            'completed_at' => now()->toDateTimeString(),
        ];
    }
}
