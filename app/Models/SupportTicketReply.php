<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketReply extends Model
{
    /** @use HasFactory<\Database\Factories\SupportTicketReplyFactory> */
    use HasFactory;

    protected $fillable = ['support_ticket_id', 'user_id', 'body', 'is_staff', 'emailed_at'];

    protected function casts(): array
    {
        return [
            'is_staff' => 'boolean',
            'emailed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
