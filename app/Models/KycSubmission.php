<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveUser;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycSubmission extends Model
{
    /** @use HasFactory<\Database\Factories\KycSubmissionFactory> */
    use BelongsToActiveUser;
    use HasFactory;

    protected $fillable = [
        'user_id', 'level_requested', 'level_granted', 'documents',
        'status', 'reviewed_by', 'reviewed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'documents' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
