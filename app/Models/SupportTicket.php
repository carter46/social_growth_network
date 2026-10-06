<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Route;

class SupportTicket extends Model
{
    /** @use HasFactory<\Database\Factories\SupportTicketFactory> */
    use HasFactory;

    public const TYPE_CREATOR = 'creator';

    public const TYPE_AGENT = 'agent';

    public const CREATOR_CATEGORIES = [
        'deposit' => 'Deposit or top-up',
        'payment' => 'Payment or checkout',
        'campaign' => 'Campaign issue',
        'order' => 'Order issue',
        'account' => 'Account access',
        'kyc' => 'KYC verification',
        'technical' => 'Technical problem',
        'other' => 'Other',
    ];

    public const AGENT_CATEGORIES = [
        'withdrawal' => 'Withdrawal issue',
        'task' => 'Task or campaign issue',
        'earnings' => 'Earnings or rewards',
        'bank_account' => 'Bank account',
        'account' => 'Account access',
        'kyc' => 'KYC verification',
        'technical' => 'Technical problem',
        'other' => 'Other',
    ];

    /**
     * @return array<string, string>
     */
    public static function categoriesForType(string $type): array
    {
        return $type === self::TYPE_AGENT ? self::AGENT_CATEGORIES : self::CREATOR_CATEGORIES;
    }

    public static function typeForUser(?User $user): string
    {
        return $user && $user->hasRole('agent') ? self::TYPE_AGENT : self::TYPE_CREATOR;
    }

    public static function categoryLabel(?string $category): string
    {
        $category = (string) $category;

        return self::CREATOR_CATEGORIES[$category]
            ?? self::AGENT_CATEGORIES[$category]
            ?? ucfirst(str_replace('_', ' ', $category));
    }

    protected $fillable = [
        'user_id',
        'category',
        'subject',
        'body',
        'status',
        'priority',
        'assigned_to',
        'user_read_at',
    ];

    protected function casts(): array
    {
        return ['user_read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class);
    }

    public function memberUrl(): ?string
    {
        if ($this->user?->hasRole('agent') && Route::has('agent.support.show')) {
            return route('agent.support.show', $this);
        }

        return Route::has('dashboard.support.show') ? route('dashboard.support.show', $this) : null;
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(SupportTicketReply::class)->latestOfMany();
    }

    /** Staff replies the ticket owner has not opened yet. */
    public function scopeWithUnreadStaffReplies(Builder $query): Builder
    {
        return $query->withCount(['replies as unread_staff_replies_count' => function (Builder $q) {
            $q->where('is_staff', true)
                ->where(function (Builder $w) {
                    $w->whereNull('support_tickets.user_read_at')
                        ->orWhereColumn('support_ticket_replies.created_at', '>', 'support_tickets.user_read_at');
                });
        }]);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportAttachment::class);
    }
}
