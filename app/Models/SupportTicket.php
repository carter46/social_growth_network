<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

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

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportAttachment::class);
    }
}
