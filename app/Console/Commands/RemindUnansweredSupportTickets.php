<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RemindUnansweredSupportTickets extends Command
{
    protected $signature = 'support:remind-unanswered';

    protected $description = 'Email users whose latest staff reply has gone 24h without a response';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $cutoff = now()->subDay();
        $sent = 0;

        SupportTicket::query()
            ->whereIn('status', ['open', 'pending', 'awaiting_user'])
            ->whereHas('latestReply', fn ($q) => $q
                ->where('is_staff', true)
                ->whereNull('emailed_at')
                ->where('created_at', '<=', $cutoff))
            ->with(['user', 'latestReply'])
            ->chunkById(100, function ($tickets) use ($dispatcher, &$sent) {
                foreach ($tickets as $ticket) {
                    $reply = $ticket->latestReply;
                    if (! $reply) {
                        continue;
                    }

                    if ($ticket->user && ! $ticket->user->isAnonymized()) {
                        try {
                            $dispatcher->notifyUser(
                                $ticket->user,
                                new NotificationMessage(
                                    type: 'ticket.reply_reminder',
                                    title: __('Support is waiting for your reply'),
                                    body: __('We replied to ticket #:id and are waiting for your response.', ['id' => $ticket->id]),
                                    actionUrl: $ticket->memberUrl(),
                                    meta: ['ticket_id' => $ticket->id],
                                    emailSubject: __('Reminder: support replied to your ticket'),
                                    dedupeKey: 'ticket.'.$ticket->id.'.reminder.'.$reply->id,
                                ),
                                ['mail']
                            );
                            $sent++;
                        } catch (Throwable $e) {
                            Log::warning('support.reminder_failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
                        }
                    }

                    $ticket->replies()
                        ->where('is_staff', true)
                        ->whereNull('emailed_at')
                        ->update(['emailed_at' => now()]);
                }
            });

        $this->info("Sent {$sent} support reply reminder(s).");

        return self::SUCCESS;
    }
}
